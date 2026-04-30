<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Modulate\Support\ViolationScanner;
use Tests\TestCase;

class ViolationScannerTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();

        parent::tearDown();
    }

    public function test_it_detects_cross_module_imports_facade_bypasses_and_missing_bindings(): void
    {
        config()->set('modulate.check_violations', true);
        $this->seedViolationFixtures();

        $violations = (new ViolationScanner())->scan();
        $types = array_column($violations, 'type');

        $this->assertContains('cross_module_import', $types);
        $this->assertContains('facade_bypass', $types);
        $this->assertContains('missing_binding', $types);
    }

    public function test_it_supports_custom_regex_patterns_from_config(): void
    {
        config()->set('modulate.check_violations', [
            'forbidden_call' => [
                'pattern' => '/ForbiddenCall\s*\(/',
                'message' => 'Forbidden call detected.',
            ],
        ]);

        $this->files->ensureDirectoryExists(app_path('Modules/Billing/Services'));
        $this->files->put(
            app_path('Modules/Billing/Services/UnsafeService.php'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

class UnsafeService
{
    public function run(): void
    {
        ForbiddenCall();
    }
}
PHP
        );

        $violations = (new ViolationScanner())->scan();

        $this->assertNotEmpty($violations);
        $this->assertSame('forbidden_call', $violations[0]['type']);
    }

    public function test_it_returns_empty_when_checks_are_disabled(): void
    {
        config()->set('modulate.check_violations', false);

        $this->assertSame([], (new ViolationScanner())->scan());
    }

    private function seedViolationFixtures(): void
    {
        $this->files->ensureDirectoryExists(app_path('Modules/Course/Contracts'));
        $this->files->ensureDirectoryExists(app_path('Modules/Billing/Services'));

        $this->files->put(
            app_path('Modules/Course/Contracts/CourseServiceInterface.php'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course\Contracts;

interface CourseServiceInterface
{
}
PHP
        );

        // Intentionally missing interface binding reference.
        $this->files->put(
            app_path('Modules/Course/CourseServiceProvider.php'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course;

use Illuminate\Support\ServiceProvider;

class CourseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
PHP
        );

        $this->files->put(
            app_path('Modules/Billing/Services/BillingService.php'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Course\Services\CourseService;
use Illuminate\Support\Facades\Auth;

class BillingService
{
    public function actor(): mixed
    {
        return Auth::user();
    }
}
PHP
        );
    }

    private function cleanupFixtures(): void
    {
        $this->files->deleteDirectory(app_path('Modules/Course'));
        $this->files->deleteDirectory(app_path('Modules/Billing'));
    }
}
