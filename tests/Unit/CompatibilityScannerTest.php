<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modulate\Support\CompatibilityScanner;
use Tests\TestCase;

class CompatibilityScannerTest extends TestCase
{
    public function test_parse_compatibility_list_extracts_yaml_frontmatter_blocks(): void
    {
        $markdown = <<<'MD'
# Compatibility

---
package: laravel/sanctum
status: needs-setup
notes: Sanctum user model integration required.
action: "Add HasApiTokens to User model in Auth module"
---

Some prose in between.

---
package: spatie/laravel-permission
status: compatible
notes: Works as-is.
action: "-"
---
MD;

        $scanner = new CompatibilityScanner();
        $parsed = $scanner->parseCompatibilityList($markdown);

        $this->assertArrayHasKey('laravel/sanctum', $parsed);
        $this->assertSame('needs-setup', $parsed['laravel/sanctum']['status']);
        $this->assertSame(
            'Add HasApiTokens to User model in Auth module',
            $parsed['laravel/sanctum']['action']
        );
        $this->assertSame('Sanctum user model integration required.', $parsed['laravel/sanctum']['notes']);

        $this->assertArrayHasKey('spatie/laravel-permission', $parsed);
        $this->assertSame('compatible', $parsed['spatie/laravel-permission']['status']);
    }

    public function test_parse_compatibility_list_ignores_non_yaml_hr_dividers(): void
    {
        $markdown = "# Compatibility\n\n---\n\n## Section title\n\n---\n";

        $scanner = new CompatibilityScanner();
        $parsed = $scanner->parseCompatibilityList($markdown);

        $this->assertEmpty($parsed);
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

        $this->assertSame('needs-setup', $mapped['laravel/sanctum']['status']);
        $this->assertSame('unknown', $mapped['vendor/unknown-package']['status']);
    }

    public function test_check_installed_packages_returns_ok_for_compatible_status(): void
    {
        $scanner = new CompatibilityScanner();

        $compatibility = [
            'laravel/horizon' => [
                'package' => 'laravel/horizon',
                'status' => 'compatible',
                'notes' => 'Works out of the box.',
                'action' => '-',
            ],
        ];

        $results = $scanner->checkInstalledPackages(['laravel/horizon'], $compatibility);

        $this->assertCount(1, $results);
        $this->assertSame('compatible', $results[0]['status']);
    }

    public function test_check_installed_packages_returns_sorted_results(): void
    {
        $scanner = new CompatibilityScanner();

        $results = $scanner->checkInstalledPackages(
            ['vendor/z-package', 'vendor/a-package', 'vendor/m-package'],
            []
        );

        $packages = array_column($results, 'package');
        $this->assertSame(['vendor/a-package', 'vendor/m-package', 'vendor/z-package'], $packages);
    }
}
