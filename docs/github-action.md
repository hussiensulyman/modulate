# GitHub Action: Modulate Lint

Modulate ships a reusable composite GitHub Action at:

`.github/actions/modulate-lint/action.yml`

This action runs `php artisan modulate:lint --ci` and supports both installation methods:

- Packagist (`composer require hussiensulyman/modulate`)
- Composer path repository (local package path)

As long as `composer install` has completed in CI, the action behavior is identical.

## Inputs

- `working-directory` (default: `./`)
- `config-path` (default: `config/modulate.php`)
- `fail-on-violations` (default: `true`)

## Manual Validation Checklist

1. Validate action syntax with `actionlint`.

```bash
actionlint
```

2. Run in a sample Laravel project with intentional violations.

```yaml
- name: Run Modulate lint (expect failure)
  uses: hussiensulyman/modulate/.github/actions/modulate-lint@v1.0.0
```

Expected result: workflow step exits with code `1`.

3. Run again in a clean sample project.

Expected result: workflow step exits with code `0`.

4. Optional non-blocking mode:

```yaml
- name: Run Modulate lint (non-blocking)
  uses: hussiensulyman/modulate/.github/actions/modulate-lint@v1.0.0
  with:
    fail-on-violations: 'false'
```

Expected result: warnings are emitted, but the step succeeds.