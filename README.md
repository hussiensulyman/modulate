<div align="center">

# modulate

**The pragmatic middle ground between monolith spaghetti and microservices complexity.**

A Laravel package that scaffolds a clean modular monolith architecture — with enforced boundaries, self-contained modules, a built-in violation checker, and a natural extraction path to microservices when you actually need it.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP: ^8.2](https://img.shields.io/badge/PHP-%5E8.2-777BB4)](https://php.net)
[![Laravel: ^10|^11](https://img.shields.io/badge/Laravel-%5E10%7C%5E11-FF2D20)](https://laravel.com)
[![Packagist Version](https://img.shields.io/packagist/v/hussiensulyman/modulate)](https://packagist.org/packages/hussiensulyman/modulate)
[![Packagist Downloads](https://img.shields.io/packagist/dt/hussiensulyman/modulate)](https://packagist.org/packages/hussiensulyman/modulate)
[![Tests](https://github.com/hussiensulyman/modulate/actions/workflows/tests.yml/badge.svg)](https://github.com/hussiensulyman/modulate/actions/workflows/tests.yml)

</div>

---

## Why Modulate?

Every Laravel project eventually faces the same crossroads:

- **Stay monolithic** → controllers call models call models, no boundaries, refactoring becomes archaeology
- **Go microservices** → Kubernetes, Docker, service meshes, distributed tracing — for an app with 200 users

Modulate is the third option. Your app stays a single deployable unit, but internally it's divided into strict, self-contained modules that can be extracted into real microservices later — without rewriting everything.

```
app/
└── Modules/
    ├── Shared/          ← base classes, DTOs, exceptions, enums
    ├── Auth/            ← owns User model, authentication
    ├── Course/
    ├── Billing/
    └── Notification/
```

Each module owns its routes, models, migrations, services, events, and tests. Modules talk to each other only through published contracts and events. When a module needs to become its own service, you move the folder and swap the bindings. That's it.

---

## Requirements

- PHP 8.2+
- Laravel 10 or 11

---

## Installation

```bash
composer require yourname/modulate
```

### New project

Run `modulate:init` immediately after creating a fresh Laravel project. It cleans up the default boilerplate and sets up the modular structure:

```bash
php artisan modulate:init
```

This will:

- Generate a `Shared/` module with base classes (DTOs, Exceptions, Traits, Enums, Http, Support)
- Generate an `Auth/` module with `User` model already inside
- Move `app/Models/User.php` to `Auth` module + keep a compatibility alias in `app/Models/`
- Update `config/auth.php` to reflect the new `User` location (with `--strict`)
- Replace root `routes/web.php` and `routes/api.php` with thin comment stubs
- Delete the now-empty `app/Http/Controllers/` folder
- Keep `app/Http/Middleware/` with a README explaining it is for global middleware only
- Add `.gitkeep` + README in any emptied folders

**Options:**

```bash
php artisan modulate:init --strict        # move User with no alias (clean, no backward compat)
php artisan modulate:init --skip=shared   # skip generating the Shared/ module
php artisan modulate:init --dry-run       # preview all changes without applying them
```

### Existing project

See [docs/migration-guide.md](docs/migration-guide.md) for a step-by-step incremental adoption guide.

---

## Quickstart

### Create a module

```bash
php artisan modulate:make Course
```

Generated structure:

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
│   └── E2E/
└── CourseServiceProvider.php
```

Plus a root-level `tests/E2E/` folder for cross-module journey tests (generated once on first `modulate:make`).

---

## Flags

### Preset flags

```bash
php artisan modulate:make Course --minimal    # bare bones, single routes.php, no Events/Listeners/Resources
php artisan modulate:make Course --api-only   # no web routes or Web/ controllers
```

### Granular control

```bash
# Add optional folders
php artisan modulate:make Course --add=repositories,actions,dtos,enums,policies

# Remove specific defaults
php artisan modulate:make Course --skip=events,listeners,e2e

# Add Dusk E2E stubs alongside default HTTP E2E stubs
php artisan modulate:make Course --add=dusk

# Combine freely
php artisan modulate:make Course --api-only --add=actions,dtos --skip=resources
```

### `--add` available values

| Value          | Generates                                                         |
| -------------- | ----------------------------------------------------------------- |
| `repositories` | `Repositories/` + `CourseRepositoryInterface.php` in `Contracts/` |
| `actions`      | `Actions/` — single-responsibility action classes                 |
| `dtos`         | `DTOs/` — immutable `readonly` DTO classes                        |
| `enums`        | `Enums/`                                                          |
| `policies`     | `Policies/` + `CoursePolicy.php`                                  |
| `dusk`         | Dusk browser test stubs alongside HTTP E2E tests                  |

### `--skip` available values

`events`, `listeners`, `resources`, `requests`, `migrations`, `tests`, `e2e`

---

## All Commands

### Setup

```bash
php artisan modulate:install        # publish config and stubs
php artisan modulate:publish-stubs  # re-publish stubs only (after package upgrade)
php artisan modulate:init           # clean up Laravel boilerplate for a fresh project
```

### Scaffolding

```bash
php artisan modulate:make {Name}
php artisan modulate:make-service {Module} {Name}
php artisan modulate:make-event {Module} {Name}
php artisan modulate:make-contract {Module} {Name}
php artisan modulate:make-migration {Module} {Name}
```

### Module management

```bash
php artisan modulate:list
php artisan modulate:rename {OldName} {NewName} [--dry-run]
php artisan modulate:delete {Name} [--dry-run]
php artisan modulate:move {Name} {NewPath} [--dry-run]
```

### Health & integrity

```bash
php artisan modulate:health    # verify all modules load without errors
php artisan modulate:check     # detect coupling violations
php artisan modulate:lint      # alias for modulate:check (CI-friendly)
php artisan modulate:graph     # visualize module dependency graph
```

### CI with GitHub Actions

```yaml
name: Modulate Lint

on:
    pull_request:
    push:
        branches: [main, master]

jobs:
    modulate-lint:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v4

            - uses: shivammathur/setup-php@v2
                with:
                    php-version: '8.3'
                    tools: composer:v2

            - name: Install dependencies
                run: composer install --prefer-dist --no-progress --no-interaction

            - name: Run Modulate lint
                uses: hussiensulyman/modulate/.github/actions/modulate-lint@v0.2.2
                with:
                    working-directory: ./
                    config-path: config/modulate.php
                    fail-on-violations: 'true'
```

The reusable action works the same whether `hussiensulyman/modulate` is installed from Packagist or via a Composer path repository, as long as dependencies are installed before running the action.

### Microservices

```bash
php artisan modulate:extract {Name}   # generate tailored extraction checklist
```

> `modulate:doctor` — package compatibility scanner — is coming in Phase 2.

---

## The Three Rules

### 1. Modules never touch each other's internals

```php
// ❌ Never — direct cross-module model access
$course = \App\Modules\Course\Models\Course::find($id);

// ✅ Always — through a published contract
$course = app(CourseRepositoryInterface::class)->find($id);
```

### 2. Queries use contracts. Side effects use events.

```php
// Need data from another module → contract
$user = app(UserServiceInterface::class)->find($userId);

// Something happened → event
event(new SubscriptionActivated($userId, $planId));
```

### 3. Each module is self-contained

A module registers its own routes, migrations, and bindings through its `ServiceProvider`. No central wiring required.

---

## The Shared Module

`modulate:init` generates a `Shared/` module that provides base infrastructure for all other modules:

```
app/Modules/Shared/
├── DTOs/           ← abstract BaseDTO, other modules extend this
├── Exceptions/     ← AppException, ValidationException, NotFoundException
├── Traits/         ← shared traits across modules
├── Enums/          ← global enums: Status, Locale, Environment
├── Http/
│   ├── BaseController.php
│   ├── BaseRequest.php
│   └── BaseResource.php
└── Support/        ← helper classes, value objects
```

**Rule:** Shared contains no business logic, no routes, and no module-specific code — only infrastructure primitives.

---

## Coupling Violation Detection

```bash
php artisan modulate:check
```

Detects:

- Direct Model imports across module boundaries
- Direct Service class imports not going through a contract
- Cross-module DB queries hitting another module's tables
- Missing contract bindings in a module's ServiceProvider
- Facade usage bypassing contracts (e.g. `Auth::user()` in a non-Auth module)
- Direct access to another module's config keys

Runs automatically on `php artisan optimize` when enabled. Uses regex scanning in v1, AST-based analysis planned for a future phase.

---

## Third-Party Package Compatibility

Most Laravel packages work with Modulate without issues. The ones that generate code (Breeze, Jetstream, Filament) will place files in default Laravel locations — move them into the correct module afterward.

Packages that extend the `User` model (Sanctum, Spatie Permission, JWT) work fine as long as they reference the correct `User` class location. See [docs/compatibility.md](docs/compatibility.md) for a curated list of popular packages and their setup notes.

> `modulate:doctor` — an automated compatibility scanner — is coming in Phase 2.

---

## Versioning

Modulate follows [Semantic Versioning](https://semver.org). Breaking changes are always a major version bump and always come with an upgrade guide in [UPGRADING.md](UPGRADING.md).

---

## Further Reading

- [docs/architecture.md](docs/architecture.md) — philosophy, Ports & Adapters, bounded contexts
- [docs/commands.md](docs/commands.md) — full command reference
- [docs/migration-guide.md](docs/migration-guide.md) — adopting Modulate in an existing project
- [docs/microservices-extraction.md](docs/microservices-extraction.md) — extracting a module into a standalone service
- [docs/compatibility.md](docs/compatibility.md) — third-party package compatibility notes
- [docs/github-action.md](docs/github-action.md) — reusable `modulate:lint` GitHub Action and manual CI validation
- [UPGRADING.md](UPGRADING.md) — upgrade guides between major versions

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Package compatibility entries in `docs/compatibility.md` are especially welcome as community contributions.

---

## License

Modulate is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
