# Contributing to Modulate

Thank you for your interest in contributing. This document explains how to get started, what we expect, and how to get your changes merged.

---

## Before You Start

- Check [open issues](../../issues) to see if your idea or bug is already being discussed
- For significant changes, open an issue first to discuss the approach — this avoids wasted effort
- For small fixes (typos, docs, minor bugs), a PR is fine without an issue

---

## Local Setup

### Requirements

- PHP 8.1+
- Composer
- Laravel 10 or 11 (for testing inside a project)

### Clone and install

```bash
git clone https://github.com/hussiensulyman/modulate.git
cd modulate
composer install
```

### Run tests

```bash
./vendor/bin/pest
```

### Test inside a Laravel project

In your Laravel project's `composer.json`, add a local path repository:

```json
"repositories": [
    {
        "type": "path",
        "url": "../modulate"
    }
]
```

Then:

```bash
composer require hussiensulyman/modulate:@dev
```

---

## Project Structure

```
src/
├── Commands/           — Artisan commands (one file per command)
├── Stubs/              — Default stub templates
├── Support/            — Internal helpers (ModuleRegistry, StubPublisher)
└── ModulateServiceProvider.php
config/
└── modulate.php
tests/
├── Unit/
└── Feature/
```

---

## Code Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style
- All new features must include tests
- All public methods must have docblocks
- Keep commands focused — one command does one thing
- Stub files use `{{ ModuleName }}` as the placeholder pattern (double curly braces, spaces inside)

---

## Adding a New Command

1. Create `src/Commands/YourCommand.php` extending `Illuminate\Console\Command`
2. Register it in `ModulateServiceProvider::$commands`
3. Add its stub(s) to `src/Stubs/` if needed
4. Add tests in `tests/Feature/Commands/`
5. Document it in `docs/commands.md`

---

## Adding a New Stub

Stubs live in `src/Stubs/`. Available placeholders:

| Placeholder | Replaced with |
|---|---|
| `{{ ModuleName }}` | The module name, e.g. `Course` |
| `{{ ModuleNameLower }}` | Lowercase, e.g. `course` |
| `{{ ModuleNamespace }}` | Full namespace, e.g. `App\Modules\Course` |
| `{{ ClassName }}` | The generated class name |
| `{{ Timestamp }}` | Current timestamp for migrations |

---

## Pull Request Process

1. Fork the repo and create a branch: `git checkout -b feat/your-feature`
2. Make your changes with tests
3. Run `./vendor/bin/pest` — all tests must pass
4. Run `./vendor/bin/pint` to fix code style
5. Update `CHANGELOG.md` under `[Unreleased]`
6. Open a PR with a clear description of what and why

---

## Commit Style

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add modulate:make-contract command
fix: resolve stub path on Windows
docs: update migration guide
refactor: extract StubPublisher to its own class
test: add feature test for --api-only flag
```

---

## Reporting Bugs

Open an issue with:
- Laravel version
- PHP version
- Modulate version
- The command you ran
- The full error output
- What you expected vs what happened

---

## Code of Conduct

Be respectful. Constructive criticism of code is welcome. Personal attacks are not.
