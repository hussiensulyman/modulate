# Modulate VS Code Extension (MVP)

The Modulate extension highlights architecture violations in your editor using inline diagnostics (red squiggles).

## Features

- Runs `php artisan modulate:check --json` automatically when PHP files are saved.
- Adds diagnostics for each violation with exact file, line, and column mapping.
- Provides detailed hover messages that include violation type and suggested fix.
- Includes the `Modulate: Run Architecture Check` command for manual checks.

## Requirements

- PHP available on your system path.
- A Laravel workspace with `artisan` in the workspace root.
- `hussiensulyman/modulate` installed in the Laravel app.

## Usage

1. Open your Laravel project in VS Code.
2. Save any PHP file, or run `Modulate: Run Architecture Check` from the command palette.
3. Review red squiggles in impacted files.
4. Hover each squiggle to see violation details and suggested fixes.

## Development

```bash
npm install
npm run compile
npm test
```

Then press `F5` in VS Code to launch an Extension Development Host.
