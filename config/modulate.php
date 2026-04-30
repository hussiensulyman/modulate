<?php

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