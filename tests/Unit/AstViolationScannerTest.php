<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Modulate\Support\AstViolationScanner;
use Modulate\Support\ViolationScanner;
use Tests\TestCase;

class AstViolationScannerTest extends TestCase
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

    public function test_ast_scanner_detects_fully_qualified_cross_module_references_regex_misses(): void
    {
        config()->set('modulate.check_violations', true);
        config()->set('modulate.use_ast', false);

        $this->seedAstOnlyViolationFixtures();

        $regexViolations = (new ViolationScanner())->scan();
        $regexTypes = array_column($regexViolations, 'type');

        $this->assertNotContains('direct_model_import', $regexTypes);
        $this->assertNotContains('cross_module_db_query', $regexTypes);

        $astViolations = (new AstViolationScanner())->scan(app_path('Modules'));
        $astTypes = array_column($astViolations, 'type');

        $this->assertContains('direct_model_import', $astTypes);
        $this->assertContains('cross_module_db_query', $astTypes);
    }

    public function test_violation_scanner_falls_back_to_regex_when_ast_scanner_fails(): void
    {
        config()->set('modulate.check_violations', true);
        config()->set('modulate.use_ast', true);

        $this->seedRegexViolationFixtures();

        $scanner = new class extends ViolationScanner
        {
            protected function useAst(): bool
            {
                return true;
            }

            protected function createAstScanner(): AstViolationScanner
            {
                return new class extends AstViolationScanner
                {
                    public function scan(string $modulesRoot): array
                    {
                        throw new \RuntimeException('nikic/php-parser is not installed.');
                    }
                };
            }
        };

        $violations = $scanner->scan();
        $types = array_column($violations, 'type');

        $this->assertContains('cross_module_import', $types);
    }

    private function seedAstOnlyViolationFixtures(): void
    {
        $this->files->ensureDirectoryExists(app_path('Modules/Course/Models'));
        $this->files->ensureDirectoryExists(app_path('Modules/Billing/Services'));

        $this->files->put(
            app_path('Modules/Course/Models/Course.php'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course\Models;

class Course
{
}
PHP
        );

        $this->files->put(
            app_path('Modules/Billing/Services/BillingService.php'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

class BillingService
{
    public function countCourses(): int
    {
        return \App\Modules\Course\Models\Course::query()->count();
    }
}
PHP
        );
    }

    private function seedRegexViolationFixtures(): void
    {
        $this->files->ensureDirectoryExists(app_path('Modules/Course/Services'));
        $this->files->ensureDirectoryExists(app_path('Modules/Billing/Services'));

        $this->files->put(
            app_path('Modules/Course/Services/CourseService.php'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course\Services;

class CourseService
{
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

class BillingService
{
    public function __construct(public CourseService $service)
    {
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
