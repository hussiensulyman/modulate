<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modulate\Support\CompatibilityScanner;
use Tests\TestCase;

class CompatibilityScannerTest extends TestCase
{
    public function test_parse_compatibility_list_extracts_yaml_comment_blocks(): void
    {
        $markdown = <<<'MD'
# Compatibility

```yaml
# package: laravel/sanctum
# status: needs-setup
# notes: Sanctum user model integration required.
# action: "Add HasApiTokens to User model in Auth module"
```

```yaml
# package: spatie/laravel-permission
# status: compatible
# notes: Works as-is.
# action: "-"
```
MD;

        $scanner = new CompatibilityScanner();
        $parsed = $scanner->parseCompatibilityList($markdown);

        $this->assertArrayHasKey('laravel/sanctum', $parsed);
        $this->assertSame('needs-setup', $parsed['laravel/sanctum']['status']);
        $this->assertSame(
            'Add HasApiTokens to User model in Auth module',
            $parsed['laravel/sanctum']['action']
        );

        $this->assertArrayHasKey('spatie/laravel-permission', $parsed);
        $this->assertSame('compatible', $parsed['spatie/laravel-permission']['status']);
    }

    public function test_check_installed_packages_maps_known_and_unknown_statuses(): void
    {
        $scanner = new CompatibilityScanner();

        $compatibility = [
            'laravel/sanctum' => [
                'package' => 'laravel/sanctum',
                'status' => 'needs-setup',
                'notes' => 'Requires auth model update.',
                'action' => 'Add HasApiTokens.',
            ],
        ];

        $results = $scanner->checkInstalledPackages(
            ['laravel/sanctum', 'vendor/unknown-package'],
            $compatibility
        );

        $mapped = [];
        foreach ($results as $result) {
            $mapped[$result['package']] = $result;
        }

        $this->assertSame('warning', $mapped['laravel/sanctum']['status']);
        $this->assertSame('unknown', $mapped['vendor/unknown-package']['status']);
    }
}