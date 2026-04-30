# Microservices Extraction Guide

How to extract a Modulate module into a standalone microservice.

If you followed Modulate's conventions — contracts for queries, events for side effects, module-owned migrations — this process is defined and predictable. You are not rewriting. You are relocating.

---

## When to Actually Do This

Before extracting, make sure the pain justifies the complexity. Extract a module into a service when:

- That specific module needs to **scale independently** (different traffic patterns from the rest of the app)
- A **separate team** needs to own it with full autonomy over deployment
- It has **fundamentally different runtime requirements** (e.g. CPU-intensive video processing vs. standard web requests)
- You need **independent deployability** for compliance or organizational reasons

Do not extract because it "feels cleaner" or because someone read a blog post. The operational overhead of a new service is real: separate deployments, health checks, service discovery, distributed tracing, network failure handling. Make sure the benefit is worth it.

---

## Prerequisites

Before extracting a module, verify it follows Modulate's rules:

- [ ] No other module imports its models directly
- [ ] All cross-module reads go through its `Contracts/` interfaces
- [ ] All cross-module side effects go through `Events/`
- [ ] Its migrations are inside the module folder (`Migrations/`)
- [ ] Its tests run in isolation without depending on other modules

Run this to check for cross-module model imports:

```bash
# Check if any other module imports Course models directly
grep -r "App\\Modules\\Course\\Models" app/Modules/ --include="*.php" \
  | grep -v "app/Modules/Course/"
```

If you find any, fix them before extracting. See the [Migration Guide](migration-guide.md) for how.

---

## The Extraction Process

We will use a `Course` module as the example throughout.

### Step 1: Create the new service

Create a new Laravel application for the service:

```bash
composer create-project laravel/laravel course-service
cd course-service
```

### Step 2: Move the module code

Copy the module folder into the new service:

```bash
cp -r ../your-monolith/app/Modules/Course course-service/app/
```

In the new service, this becomes the application itself rather than a module:

```
course-service/app/
├── Contracts/
├── Controllers/
├── Models/
├── Migrations/ → move to database/migrations/
├── Services/
├── Events/
├── Listeners/
├── Routes/ → move to routes/
└── Tests/ → move to tests/
```

Adjust namespaces from `App\Modules\Course\` to `App\` (or keep as-is if you prefer).

### Step 3: Expose an HTTP API

The module's `Contracts/` interfaces defined what the service exposes. Now make those available over HTTP.

For each method in `CourseServiceInterface`:

```php
// Before (in-process interface)
interface CourseServiceInterface
{
    public function find(int $id): CourseDTO;
    public function unlockAllForUser(int $userId): void;
}

// After (HTTP API endpoints)
// GET  /api/courses/{id}
// POST /api/users/{userId}/unlock-courses
```

### Step 4: Replace the contract binding in the monolith

In the monolith, the `CourseServiceProvider` bound `CourseServiceInterface` to a local `CourseService`. Replace this with an HTTP client adapter:

```php
// Before — local implementation
$this->app->bind(CourseServiceInterface::class, CourseService::class);

// After — HTTP client pointing to the new service
$this->app->bind(CourseServiceInterface::class, function () {
    return new CourseServiceHttpClient(
        baseUrl: config('services.course.url'),
        apiKey: config('services.course.key'),
    );
});
```

The `CourseServiceHttpClient` implements `CourseServiceInterface` exactly, but makes HTTP calls instead of hitting the database:

```php
class CourseServiceHttpClient implements CourseServiceInterface
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey
    ) {}

    public function find(int $id): CourseDTO
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/api/courses/{$id}");

        return CourseDTO::fromArray($response->json());
    }

    public function unlockAllForUser(int $userId): void
    {
        Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/api/users/{$userId}/unlock-courses");
    }
}
```

No other module changes. They still depend on `CourseServiceInterface`. They do not know or care that it now goes over the network.

### Step 5: Replace events with a message broker

Events that cross module boundaries need to move from Laravel's in-process event system to a message broker (Kafka, RabbitMQ, AWS SQS, etc.).

**In the monolith (publishing side):**

```php
// Before — in-process Laravel event
event(new SubscriptionActivated($userId, $planId));

// After — publish to message broker
$this->messageBroker->publish('subscription.activated', [
    'user_id' => $userId,
    'plan_id' => $planId,
    'occurred_at' => now()->toIso8601String(),
]);
```

**In the Course service (consuming side):**

```php
// A consumer process subscribes to the topic
// and calls the local handler
class SubscriptionActivatedConsumer
{
    public function handle(array $payload): void
    {
        $event = SubscriptionActivated::fromPayload($payload);
        app(UnlockCoursesListener::class)->handle($event);
    }
}
```

The `SubscriptionActivated` event class and `UnlockCoursesListener` are unchanged. Only the delivery mechanism is different.

### Step 6: Move the database

The module's migrations define its schema. In the new service, run them against a separate database:

```bash
# In course-service/
php artisan migrate
```

In the monolith, remove the Course module's migrations (they no longer apply to the monolith's database).

If the Course tables had foreign keys to other modules' tables (e.g. `user_id` referencing the `users` table), remove the database-level foreign key constraints. The relationship is now enforced at the application level through contracts, not at the database level.

### Step 7: Remove the module from the monolith

```bash
rm -rf app/Modules/Course/
```

The monolith now uses `CourseServiceHttpClient` for all Course operations. Everything else is unchanged.

---

## Data Consistency

Moving to separate services means you lose database transactions across modules. Where you previously had:

```php
DB::transaction(function () use ($userId, $courseId) {
    $this->billingService->charge($userId);
    $this->courseService->unlock($userId, $courseId);
});
```

You now need a different approach. The pragmatic options:

**Sagas / Process Managers** — a coordinator fires a sequence of events and handles compensation (rollback actions) if any step fails. Good for complex workflows.

**Eventual consistency** — accept that the two operations may not complete at exactly the same time. Design the system to handle partial states gracefully (e.g. show a "processing" state while the course unlock is pending).

**Outbox pattern** — write the event to a local `outbox` table in the same transaction as the business operation, then a background process reliably delivers it to the broker.

For most extraction scenarios, eventual consistency with good UX handling is the right starting point.

---

## Keeping Contracts in Sync

Once the contract is implemented as an HTTP API, you need to keep both sides in sync. Options:

- **OpenAPI spec** — define the API spec as the source of truth, generate client code from it
- **Shared package** — publish the DTO classes and interface as a small Composer package that both the monolith and the service depend on
- **Contract testing** — use Pact or a similar tool to verify the client and server agree on the API shape

---

## Rollback

If extraction goes wrong:

1. Restore the module folder to the monolith
2. Rebind `CourseServiceInterface` back to the local `CourseService`
3. Re-run the module migrations against the monolith database
4. Restore Laravel event listeners in `CourseServiceProvider`
5. Shut down the separate service

Because the interface never changed, the rollback is clean.
