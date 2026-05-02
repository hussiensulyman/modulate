<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class CheckCommandAstTest extends TestCase
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

    public function test_check_command_detects_ast_only_violation_when_use_ast_flag_is_enabled(): void
    {
        config()->set('modulate.check_violations', true);
        config()->set('modulate.use_ast', false);
        $this->seedAstOnlyViolationFixtures();

        $this->artisan('modulate:check', ['--use-ast' => true])
            ->expectsOutputToContain('direct_model_import')
            ->assertExitCode(1);
    }

    public function test_check_command_accepts_ci_option_when_clean(): void
    {
        config()->set('modulate.check_violations', true);

        $this->artisan('modulate:check', ['--ci' => true])
            ->assertExitCode(0);
    }

    public function test_lint_alias_accepts_ci_option_and_returns_failure_on_violation(): void
    {
        config()->set('modulate.check_violations', true);
        config()->set('modulate.use_ast', true);
        $this->seedAstOnlyViolationFixtures();

        $this->artisan('modulate:lint', ['--ci' => true, '--use-ast' => true])
            ->expectsOutputToContain('direct_model_import')
            ->assertExitCode(1);
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

    private function cleanupFixtures(): void
    {
        $this->files->deleteDirectory(app_path('Modules/Course'));
        $this->files->deleteDirectory(app_path('Modules/Billing'));
    }
}
