# Architecture Deep Dive

This document explains the philosophy behind Modulate, the patterns it draws from, and the decisions made in its design.

---

## The Problem With Standard Laravel

A default Laravel project has no enforced structure beyond MVC. This is a feature when you're moving fast — you can put logic anywhere and it works. It becomes a liability when the project grows.

The typical failure mode looks like this:

- Controllers grow to 300+ lines because "it's easier to just add it here"
- Models accumulate scopes, relationships, and business logic until they're load-bearing walls
- A bug in the User model breaks the Billing feature because they're tightly coupled
- Onboarding a new developer means explaining the entire codebase before they can touch anything safely

This is **monolith hell**. Not because of the single deployment — that's fine — but because of the missing boundaries.

---

## Why Not Microservices?

Microservices solve the boundary problem but introduce a different set of problems:

- Every feature now requires coordinating multiple services, deployments, and network calls
- Local development needs Docker Compose with 8 services running
- Debugging a bug that crosses service boundaries requires distributed tracing
- A team of 2-3 developers spends more time on infrastructure than on product

For most projects, this complexity is not justified by the scale. Microservices are an operational model, not an architectural purity choice.

---

## The Modulate Answer: Modular Monolith

A modular monolith is a single deployable application with enforced internal boundaries. Each module is a self-contained vertical slice of the application — it owns its routes, business logic, data schema, events, and tests.

The key word is **enforced**. Without tooling and conventions, "modules" are just folders that developers ignore under deadline pressure. Modulate provides the scaffolding, the patterns, and the violation checker that make boundaries natural to follow.

---

## Ports and Adapters (Hexagonal Architecture)

Modulate's structure is based on the Ports and Adapters pattern, applied at the module level.

**Ports** are the interfaces a module exposes to the outside world. In Modulate, these live in `Contracts/` (configurable). A contract defines *what* a module can do, not *how* it does it.

**Adapters** are the concrete implementations. In Modulate, these live in `Services/`. They implement the contracts and contain the actual business logic.

This means a module can be tested in complete isolation by swapping out real adapters for fakes. It also means the implementation can change without affecting any module that depends on the contract.

```
Module A                    Module B
─────────────────           ──────────────────────────────
Controllers/                Contracts/
  CourseController            UserServiceInterface   ← Port
Services/                   Services/
  CourseService               UserService           ← Adapter
    └─ depends on ──────────► UserServiceInterface
```

---

## Bounded Contexts

The concept of a **bounded context** comes from Domain-Driven Design. A bounded context is a boundary within which a particular domain model applies and has consistent meaning.

In practical terms: a `User` in the Auth module and a `User` in the Billing module might be different things, with different data and different behavior. Forcing them to share a single `User` model creates hidden coupling.

Each Modulate module is its own bounded context. It has its own models, its own vocabulary, and ideally its own database schema. If the Billing module needs user data, it asks for it through a contract — it gets back a DTO or a value object, not a live Eloquent model with 40 relationships.

---

## Two Patterns for Cross-Module Communication

### Contract Pattern — for queries

When Module A needs to **read** data from Module B, it depends on a contract published by Module B.

```
Billing ──► UserServiceInterface::find($id) ──► Auth
```

This is synchronous and direct. The calling module gets an answer immediately. Use this when you genuinely need the data to continue processing.

### Event Pattern — for side effects

When something **happens** in Module A and other modules need to react, Module A fires a domain event. It does not know which modules are listening.

```
Auth ──► SubscriptionActivated ──► [Course, Notification, Analytics, ...]
```

This is the more powerful pattern. Adding a new reaction to an event (a new listener in a new module) requires zero changes to the module that fired it. This is what makes the architecture extensible.

**Rule of thumb:**
- Need data? → Contract
- Something happened? → Event

---

## DTOs — Immutable Data Across Boundaries

When a module returns data through a contract, it should return a DTO (Data Transfer Object), not a live Eloquent model. This is critical because an Eloquent model carries relationships, scopes, and database connection state — sharing it across module boundaries is subtle coupling.

Modulate generates DTOs as `readonly` classes (PHP 8.2+), which enforces immutability:

```php
readonly class CourseDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public string $status,
        public \DateTimeImmutable $publishedAt,
    ) {}

    public static function fromModel(Course $course): self
    {
        return new self(
            id: $course->id,
            title: $course->title,
            status: $course->status,
            publishedAt: $course->published_at,
        );
    }
}
```

Once created, a DTO cannot be mutated. The receiving module gets a snapshot of the data — not a live object that could trigger lazy-loaded queries.

---

## Why Each Module Owns Its Migrations

Standard Laravel keeps all migrations in `database/migrations/`. This works fine until you try to extract a module into a separate service — at that point you need to figure out which migrations belong to which module.

Modulate defaults to placing migrations inside the module folder (`Modules/Course/Migrations/`). The module's `ServiceProvider` loads them via `$this->loadMigrationsFrom()`. `php artisan migrate` still works exactly the same way.

Projects that prefer centralized migrations can configure this per module — both approaches are supported.

The benefit of module-owned migrations: when you extract a module, its schema comes with it.

---

## Auto-Discovery of Modules

Modulate's `ModulateServiceProvider` supports three discovery modes:

**Auto (default):** Scans `app/Modules/*/` at boot and registers each module's `ServiceProvider` automatically. Creating a new module with `modulate:make` is enough — no manual registration needed.

**Manual:** Only modules listed explicitly in `config/modulate.php` are registered. Full control, useful when you want to disable a module without deleting it.

**Hybrid:** Auto-scan runs, plus any additional providers listed in config are also registered. Useful for modules that live outside the standard modules path.

---

## The Coupling Violation Checker

Architecture rules only work if they are enforced. Modulate ships with `modulate:check` (aliased as `modulate:lint` for CI pipelines) that scans the codebase for violations:

- **Direct model imports** across module boundaries
- **Direct service imports** not going through a contract
- **Cross-module DB queries** hitting another module's tables
- **Missing contract bindings** in a module's ServiceProvider
- **Facade bypasses** — calling `Auth::user()` from a non-Auth module
- **Cross-module config access** — reading `config('course.*')` from inside Billing

The checker runs on demand and optionally on `php artisan optimize`. Warnings are on by default and can be disabled in config when needed (e.g. during a migration period).

---

## The Microservices Path

Because modules follow these rules from the start, extracting one into a microservice is a defined process rather than a rewrite:

| Monolith component | Microservice equivalent |
|---|---|
| `Contracts/` interface binding | HTTP/gRPC client behind the same interface |
| Laravel Events | Message broker topic (Kafka, RabbitMQ, SQS) |
| Module `Migrations/` | Service-owned database |
| Module `Routes/` | Service API endpoints |
| Module `Tests/` | Service test suite |

No other module needs to know or change. The contract stays the same — only what is behind it changes.

Run `php artisan modulate:extract {Name}` for a checklist tailored to your specific module's contracts, events, and dependencies.

---

## What Modulate Is Not

**Not DDD.** Modulate borrows concepts from DDD (bounded contexts, domain events) but does not enforce aggregates, repositories, or value objects. You can add those patterns inside a module using `--add=repositories` if you want them.

**Not nWidart.** nWidart/laravel-modules is a powerful package but has a steep learning curve and an opinionated structure that fights you on retrofitting. Modulate is simpler, more pragmatic, and designed to match how Laravel developers already think.

**Not a microservices framework.** Modulate does not handle service discovery, distributed tracing, or inter-service networking. It is a monolith architecture tool that makes future microservices adoption easier.
