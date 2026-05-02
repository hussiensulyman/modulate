# Commands Reference

Full reference for all Modulate artisan commands, organized by category.

---

## Setup

### `modulate:install`

Publishes config and stubs to your project. Run once after installation.

```bash
php artisan modulate:install
```

Publishes:
- `config/modulate.php`
- `stubs/modulate/` — all stub files, fully editable

---

### `modulate:publish-stubs`

Re-publishes stubs only. Use after upgrading Modulate when new stubs are added.

```bash
php artisan modulate:publish-stubs
```

---

### `modulate:init`

Cleans up Laravel's default boilerplate and bootstraps the modular structure. Run once on a fresh Laravel project.

```bash
php artisan modulate:init
```

**What it does (in order):**

1. Generates `Shared/` module with base infrastructure classes
2. Generates `Auth/` module with `User` model already inside
3. Moves `app/Models/User.php` into `Auth` module, keeps compatibility alias in `app/Models/`
4. Replaces `routes/web.php` and `routes/api.php` with thin comment stubs
5. Deletes empty `app/Http/Controllers/` folder
6. Keeps `app/Http/Middleware/` — adds README explaining global-only rule
7. Adds `.gitkeep` + README in any emptied folders

**Options:**

| Option | Effect |
|---|---|
| `--strict` | Moves `User` with no alias + updates `config/auth.php`. Clean architecture, no backward compat shim. |
| `--skip=shared` | Skips generating the `Shared/` module |
| `--dry-run` | Shows every change without applying anything |

```bash
php artisan modulate:init --dry-run       # preview first (recommended)
php artisan modulate:init                 # apply
php artisan modulate:init --strict        # no User alias
php artisan modulate:init --skip=shared   # no Shared/ module
```

> Always run `--dry-run` on an existing project before applying.

---

## Scaffolding

### `modulate:make`

Scaffolds a complete new module.

```bash
php artisan modulate:make {Name}
```

**Preset flags:**

| Flag | Effect |
|---|---|
| `--minimal` | Bare minimum: no Events, Listeners, Resources. Single `routes.php` instead of web/api split. |
| `--api-only` | No `Controllers/Web/`, no `Routes/web.php`. |

**Granular flags:**

| Flag | Effect |
|---|---|
| `--add=x,y,z` | Add optional folders on top of defaults |
| `--skip=x,y,z` | Remove specific folders from defaults |

**`--add` values:**

| Value | Generates |
|---|---|
| `repositories` | `Repositories/` + `{Name}RepositoryInterface.php` |
| `actions` | `Actions/` |
| `dtos` | `DTOs/` — immutable `readonly` classes |
| `enums` | `Enums/` |
| `policies` | `Policies/` + `{Name}Policy.php` |
| `dusk` | Dusk browser test stubs in `Tests/E2E/` |

**`--skip` values:** `events`, `listeners`, `resources`, `requests`, `migrations`, `tests`, `e2e`

**Default generated structure:**

```
app/Modules/Course/
├── Contracts/
│   └── CourseServiceInterface.php
├── Controllers/
│   ├── Web/
│   └── Api/
├── Models/
├── Migrations/
├── Requests/
├── Resources/
├── Services/
│   └── CourseService.php
├── Events/
├── Listeners/
├── Routes/
│   ├── web.php
│   └── api.php
├── Tests/
│   ├── Unit/
│   ├── Feature/
│   └── E2E/                   ← HTTP-based E2E tests per module
└── CourseServiceProvider.php
```

On the **first** `modulate:make` in a project, a root-level `tests/E2E/` folder is also created for cross-module journey tests.

---

### `modulate:make-service`

Adds a Service class + Contract interface to an existing module.

```bash
php artisan modulate:make-service {Module} {Name}
```

```bash
php artisan modulate:make-service Course Lesson
# Modules/Course/Services/LessonService.php
# Modules/Course/Contracts/LessonServiceInterface.php
```

---

### `modulate:make-event`

Adds an Event class + Listener to an existing module.

```bash
php artisan modulate:make-event {Module} {Name}
```

```bash
php artisan modulate:make-event Auth SubscriptionActivated
# Modules/Auth/Events/SubscriptionActivated.php
# Modules/Auth/Listeners/HandleSubscriptionActivated.php
```

> Name events in past tense — `SubscriptionActivated`, not `ActivateSubscription`.

---

### `modulate:make-contract`

Adds a Contract interface to an existing module without a concrete implementation.

```bash
php artisan modulate:make-contract {Module} {Name}
```

```bash
php artisan modulate:make-contract Course CourseRepository
# Modules/Course/Contracts/CourseRepositoryInterface.php
```

---

### `modulate:make-migration`

Creates a migration scoped to a module, placed inside the module's `Migrations/` folder.

```bash
php artisan modulate:make-migration {Module} {Name}
```

```bash
php artisan modulate:make-migration Course create_courses_table
# Modules/Course/Migrations/2026_03_30_000000_create_courses_table.php

php artisan modulate:make-migration Course add_published_at_to_courses
# Modules/Course/Migrations/2026_03_30_000001_add_published_at_to_courses.php
```

---

## Module Management

### `modulate:list`

Lists all registered modules, their contracts, and load status.

```bash
php artisan modulate:list
```

```
┌─────────────┬──────────────────────────────────────┬────────────┐
│ Module      │ Contracts                            │ Status     │
├─────────────┼──────────────────────────────────────┼────────────┤
│ Shared      │ —                                    │ ✓ Loaded   │
├─────────────┼──────────────────────────────────────┼────────────┤
│ Auth        │ UserServiceInterface                 │ ✓ Loaded   │
│             │ UserSubscriptionInterface            │            │
├─────────────┼──────────────────────────────────────┼────────────┤
│ Course      │ CourseServiceInterface               │ ✓ Loaded   │
│             │ LessonServiceInterface               │            │
└─────────────┴──────────────────────────────────────┴────────────┘
```

---

### `modulate:rename`

Renames a module — updates the folder, all internal namespaces, and all cross-module imports.

```bash
php artisan modulate:rename Course Curriculum --dry-run   # always preview first
php artisan modulate:rename Course Curriculum
```

`--dry-run` shows every file that would be touched and every namespace that would change before anything is written to disk.

---

### `modulate:delete`

Deletes a module and warns about broken dependencies in other modules.

```bash
php artisan modulate:delete Course --dry-run   # always preview first
php artisan modulate:delete Course             # prompts for confirmation
```

`--dry-run` output lists every file that would be deleted and every cross-module import or listener that would break.

---

### `modulate:move`

Moves a module to a different base path. Updates all namespaces and imports.

```bash
php artisan modulate:move Course src/Modules/Course --dry-run
php artisan modulate:move Course src/Modules/Course
```

---

## Health & Integrity

### `modulate:health`

Boots each module in isolation and verifies it loads without errors.

```bash
php artisan modulate:health
```

Checks per module:
- ServiceProvider loads without exceptions
- All contract bindings resolve
- Route files exist and load without syntax errors
- Migration folder exists if configured as module-owned
- Test directories exist

```
  ✓ Shared      — OK
  ✓ Auth        — OK
  ✗ Billing     — ContainerBindingException: InvoiceServiceInterface has no binding
  ✓ Course      — OK
```

---

### `modulate:check` / `modulate:lint`

Scans all modules for architectural violations. `modulate:lint` is an identical alias for CI pipelines.

```bash
php artisan modulate:check
php artisan modulate:lint
```

**Detects:**

| Violation | Example |
|---|---|
| Direct Model import across modules | `Billing` importing `Course\Models\Course` |
| Direct Service import not through contract | `Billing` importing `CourseService` directly |
| Cross-module DB query | `Billing` querying `courses` table directly |
| Missing contract binding | `CourseServiceInterface` declared but not bound |
| Facade bypassing contract | `Auth::user()` called inside `Course` module |
| Cross-module config access | `config('course.*')` read from `Billing` |

Detection combines **regex scanning** and AST analysis via `nikic/php-parser` for higher accuracy.

Runs automatically on `php artisan optimize` when `check_on_optimize` is enabled in config.

---

### `modulate:graph`

Outputs a visual dependency graph of all modules.

```bash
php artisan modulate:graph                # ASCII (default)
php artisan modulate:graph --format=dot   # Graphviz .dot file
php artisan modulate:graph --format=html  # interactive HTML page
```

ASCII example:

```
Module Dependency Graph
═══════════════════════════════════════════
Course
  depends on (contracts):
    ← Auth        via UserServiceInterface
  listens to (events):
    ← Auth        SubscriptionActivated

Billing
  depends on (contracts):
    ← Auth        via UserServiceInterface
    ← Course      via CourseServiceInterface
  fires (events):
    → Notification  PaymentCompleted
═══════════════════════════════════════════
```

---

## Microservices

### `modulate:extract`

Analyzes a module and generates a tailored microservices extraction checklist based on its actual contracts, events, and dependencies.

```bash
php artisan modulate:extract Course
```

```
Microservices Extraction Checklist — Course Module
════════════════════════════════════════════════════

Contracts this module exposes (need HTTP/gRPC endpoints):
  [ ] CourseServiceInterface::find(int $id): CourseDTO
        → GET /api/courses/{id}
  [ ] CourseServiceInterface::unlockAllForUser(int $userId): void
        → POST /api/users/{userId}/unlock-courses

Contracts this module depends on (need HTTP clients):
  [ ] UserServiceInterface (from Auth)
        → Replace binding with AuthServiceHttpClient

Events this module fires (need message broker publishing):
  [ ] CoursePublished → topic: course.published

Events this module listens to (need broker consumers):
  [ ] SubscriptionActivated (from Auth)

Migrations to move:
  [ ] Modules/Course/Migrations/ → course-service/database/migrations/

Foreign keys to remove:
  [ ] courses.user_id → users.id (enforce at app level instead)

Tests to verify:
  [ ] Tests/Unit/    (12 tests)
  [ ] Tests/Feature/ (8 tests)
  [ ] Tests/E2E/     (3 tests)
════════════════════════════════════════════════════
```

`modulate:doctor` is available and scans your installed packages against known compatibility guidance.

---

## Config Reference

```php
// config/modulate.php

return [

    // Path to modules directory, relative to app/
    'path' => 'Modules',

    // Base namespace for all modules
    'namespace' => 'App\\Modules',

    // 'auto'   — scan modules path and register all providers
    // 'manual' — only register providers listed below
    // Both modes always register providers listed in 'providers'
    'discovery' => 'auto',

    'providers' => [
        // \App\Modules\Legacy\LegacyServiceProvider::class,
    ],

    // Contracts folder name inside each module
    'contracts_folder' => 'Contracts',

    // 'split' — Controllers/Web/ + Controllers/Api/ (default)
    // 'flat'  — Controllers/ with no subfolders
    // 'http'  — Http/Controllers/ (default Laravel style)
    'controllers_structure' => 'split',

    // 'pest'    — generate Pest test stubs
    // 'phpunit' — generate PHPUnit test stubs
    'testing' => 'pest',

    // Generate E2E test folders by default
    'e2e' => true,

    // Warn on coupling violations
    'check_violations' => true,

    // Auto-run modulate:check on php artisan optimize
    'check_on_optimize' => true,

];
```
