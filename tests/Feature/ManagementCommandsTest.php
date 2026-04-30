<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class ManagementCommandsTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->cleanupFixtures();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();

        parent::tearDown();
    }

    public function test_rename_dry_run_does_not_modify_files(): void
    {
        $this->artisan('modulate:rename', [
            'from' => 'Course',
            'to' => 'Curriculum',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertDirectoryExists(app_path('Modules/Course'));
        $this->assertDirectoryDoesNotExist(app_path('Modules/Curriculum'));

        $consumer = (string) file_get_contents(app_path('Modules/Billing/Services/BillingService.php'));
        $this->assertStringContainsString('App\\Modules\\Course\\Services\\CourseService', $consumer);
    }

    public function test_rename_rewrites_namespace_and_imports_after_confirmation(): void
    {
        $this->artisan('modulate:rename', [
            'from' => 'Course',
            'to' => 'Curriculum',
        ])
            ->expectsConfirmation('This will rename module files and rewrite imports. Continue?', 'yes')
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist(app_path('Modules/Course'));
        $this->assertDirectoryExists(app_path('Modules/Curriculum'));
        $this->assertFileExists(app_path('Modules/Curriculum/CurriculumServiceProvider.php'));

        $provider = (string) file_get_contents(app_path('Modules/Curriculum/CurriculumServiceProvider.php'));
        $this->assertStringContainsString('namespace App\\Modules\\Curriculum;', $provider);

        $consumer = (string) file_get_contents(app_path('Modules/Billing/Services/BillingService.php'));
        $this->assertStringContainsString('App\\Modules\\Curriculum\\Services\\CourseService', $consumer);
    }

    public function test_delete_dry_run_shows_broken_dependency_warnings_without_deleting(): void
    {
        $this->artisan('modulate:delete', [
            'name' => 'Course',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Potential broken dependencies detected:')
            ->assertExitCode(0);

        $this->assertDirectoryExists(app_path('Modules/Course'));
    }

    public function test_delete_aborts_when_not_confirmed(): void
    {
        $this->artisan('modulate:delete', [
            'name' => 'Course',
        ])
            ->expectsConfirmation('This will permanently delete the module. Continue?', 'no')
            ->assertExitCode(1);

        $this->assertDirectoryExists(app_path('Modules/Course'));
    }

    public function test_move_aborts_when_not_confirmed(): void
    {
        $target = app_path('Domain/Modules/Course');

        $this->artisan('modulate:move', [
            'name' => 'Course',
            'target' => $target,
        ])
            ->expectsConfirmation('This will move the module and rewrite namespaces/imports. Continue?', 'no')
            ->assertExitCode(1);

        $this->assertDirectoryExists(app_path('Modules/Course'));
        $this->assertDirectoryDoesNotExist($target);
    }

    private function seedFixtures(): void
    {
        $this->files->ensureDirectoryExists(app_path('Modules/Course/Services'));
        $this->files->ensureDirectoryExists(app_path('Modules/Billing/Services'));

        $this->files->put(
            app_path('Modules/Course/CourseServiceProvider.php'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course;

class CourseServiceProvider
{
}
PHP
        );

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
        $this->files->deleteDirectory(app_path('Modules/Curriculum'));
        $this->files->deleteDirectory(app_path('Modules/Billing'));
        $this->files->deleteDirectory(app_path('Domain'));
    }
}
