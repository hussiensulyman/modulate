# Migration Guide

How to adopt Modulate in an existing Laravel project without breaking anything.

The key principle: **migrate incrementally**. Do not try to move everything at once. Modulate is designed to coexist with standard Laravel code during the transition.

---

## Before You Start

### Assess your project

Take stock of what you have:
- List all your controllers and group them by domain (auth, billing, courses, etc.)
- Identify which models are "core" (shared by many features) vs "owned" (clearly belong to one feature)
- Note where business logic lives — controllers, models, or already in service classes

### Install Modulate

```bash
composer require hussiensulyman/modulate:^1.0
php artisan modulate:install
```

Your existing code is untouched. Modulate only adds the auto-discovery mechanism — it will not find any modules yet because you have none.

---

## Phase 1: Pick One Module

Do not start with your most complex feature. Pick one that is:
- Relatively self-contained (few dependencies on other features)
- Not critical path (so you can take your time)
- Small enough to finish in a few days

Good first candidates: `Notification`, `Tag`, `Category`, `Media`, `Settings`.

### Create the module scaffold

```bash
php artisan modulate:make Notification
```

### Move existing code in

Move your existing files into the module, one by one:

```bash
# Before
app/Http/Controllers/NotificationController.php
app/Models/Notification.php
app/Services/NotificationService.php

# After
app/Modules/Notification/Controllers/Web/NotificationController.php
app/Modules/Notification/Models/Notification.php
app/Modules/Notification/Services/NotificationService.php
```

Update namespaces in each file:

```php
// Before
namespace App\Http\Controllers;

// After
namespace App\Modules\Notification\Controllers\Web;
```

### Move routes

Cut the notification routes from `routes/web.php` and `routes/api.php` into the module's route files:

```php
// app/Modules/Notification/Routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
});
```

The module's `ServiceProvider` loads these automatically.

### Move migrations

Copy the relevant migration files into `app/Modules/Notification/Migrations/`. The module's `ServiceProvider` loads them via `loadMigrationsFrom()`.

> **Note:** Do not delete the originals from `database/migrations/` until you have verified the module migrations load correctly. Duplicate migration detection will warn you.

### Test

Run your full test suite. Fix any namespace or import errors. The module should behave identically to before.

---

## Phase 2: Extract More Modules

Repeat Phase 1 for each domain area. Suggested order — from least to most dependencies:

1. Simple lookup/config modules (Tags, Categories, Settings)
2. Feature modules with few cross-feature dependencies (Media, Notifications)
3. Core feature modules (Courses, Products, Posts)
4. Auth and User management
5. Billing (usually has the most cross-module dependencies)

---

## Phase 3: Clean Up Cross-Module Coupling

This is where it gets real. After moving code into modules, you will find places where modules directly reference each other's internals. Fix these one at a time.

### Finding coupling

Search for direct cross-module imports:

```bash
# Find any file in Course that imports from another module
grep -r "App\\Modules\\Auth" app/Modules/Course/
grep -r "App\\Modules\\Billing" app/Modules/Course/
```

### Fixing with contracts

For each piece of coupling you find, create a contract:

```bash
# Course module needs to know about subscriptions
php artisan modulate:make-contract Auth UserSubscriptionInterface
```

Define the interface in `Auth/Contracts/UserSubscriptionInterface.php`:

```php
interface UserSubscriptionInterface
{
    public function isActive(int $userId): bool;
    public function getActivePlan(int $userId): ?SubscriptionDTO;
}
```

Implement it in `Auth/Services/`:

```php
class UserSubscriptionService implements UserSubscriptionInterface
{
    public function isActive(int $userId): bool
    {
        return Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }
}
```

Bind it in `AuthServiceProvider`:

```php
$this->app->bind(
    UserSubscriptionInterface::class,
    UserSubscriptionService::class
);
```

Now the Course module can use it without knowing Auth exists:

```php
class CourseService
{
    public function __construct(
        private UserSubscriptionInterface $subscriptions
    ) {}

    public function canAccess(int $userId, int $courseId): bool
    {
        return $this->subscriptions->isActive($userId);
    }
}
```

### Fixing with events

For side effects (one module causing something to happen in another), replace direct calls with events:

```bash
php artisan modulate:make-event Auth SubscriptionActivated
```

```php
// Before — Auth directly calls Course
class SubscriptionService
{
    public function activate(int $userId): void
    {
        // ...
        $this->courseService->unlockAllFor($userId); // ❌ direct coupling
    }
}

// After — Auth fires an event, Course listens
class SubscriptionService
{
    public function activate(int $userId): void
    {
        // ...
        event(new SubscriptionActivated($userId)); // ✅ decoupled
    }
}

// Course/Listeners/UnlockCoursesOnSubscription.php
class UnlockCoursesOnSubscription
{
    public function handle(SubscriptionActivated $event): void
    {
        $this->courseService->unlockAllFor($event->userId);
    }
}
```

Register the listener in `CourseServiceProvider`:

```php
Event::listen(SubscriptionActivated::class, UnlockCoursesOnSubscription::class);
```

---

## Handling Shared Models

The hardest part of migration is dealing with models that are referenced everywhere — typically `User`.

**The practical approach:**

Do not try to eliminate the shared `User` model immediately. Instead:

1. Keep `App\Models\User` as a shared model for now
2. In each module's contracts, accept `int $userId` instead of a `User` object
3. The module that owns user data (Auth) provides it through a contract
4. Other modules never `use App\Models\User` — they get what they need through contracts

Over time, you can replace the shared model entirely if needed. But for most projects, a shared `User` model with access controlled through contracts is a pragmatic and acceptable solution.

---

## Rollback Plan

Because migration is incremental and Modulate coexists with standard Laravel code, rolling back a single module is straightforward:

1. Move the files back to their original locations
2. Restore the original namespaces
3. Move routes back to `routes/web.php` and `routes/api.php`
4. Remove the module folder

The rest of the application is unaffected.

---

## Common Issues

### "Class not found" after moving files

You moved a file but the namespace in the class declaration still points to the old location. Update the `namespace` line at the top of each file.

### Routes not loading

Check that your module's `ServiceProvider` is being discovered. Run `php artisan modulate:list` to see if the module appears. If not, check that the `ServiceProvider` class name matches the file name exactly.

### Duplicate migration error

You moved a migration into the module but left the original in `database/migrations/`. Delete the original once you have confirmed the module version loads correctly.

### Tests failing after moving controllers

If your tests reference controller class names directly, update the imports to the new namespaces. Feature tests that hit HTTP routes will not need changes since the routes themselves have not changed.
