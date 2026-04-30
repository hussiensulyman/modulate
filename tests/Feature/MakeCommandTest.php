<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class MakeCommandTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->cleanupModules();
        $this->files->deleteDirectory(base_path('tests/E2E'));
    }

    protected function tearDown(): void
    {
        $this->cleanupModules();
        $this->files->deleteDirectory(base_path('tests/E2E'));

        parent::tearDown();
    }

    public function test_make_creates_default_module_structure_and_root_e2e_once(): void
    {
        $this->artisan('modulate:make', ['name' => 'Course'])
            ->assertExitCode(0);

        $base = app_path('Modules/Course');

        $this->assertFileExists($base.'/CourseServiceProvider.php');
        $this->assertFileExists($base.'/Contracts/CourseServiceInterface.php');
        $this->assertFileExists($base.'/Services/CourseService.php');
        $this->assertFileExists($base.'/Models/Course.php');
        $this->assertFileExists($base.'/Controllers/Web/CourseWebController.php');
        $this->assertFileExists($base.'/Controllers/Api/CourseApiController.php');
        $this->assertFileExists($base.'/Routes/web.php');
        $this->assertFileExists($base.'/Routes/api.php');
        $this->assertFileExists($base.'/Events/CourseCreated.php');
        $this->assertFileExists($base.'/Listeners/HandleCourseCreated.php');
        $this->assertFileExists($base.'/Migrations');
        $this->assertFileExists($base.'/Tests/E2E/CourseE2ETest.php');

        $this->assertDirectoryExists(base_path('tests/E2E'));
        $this->assertFileExists(base_path('tests/E2E/README.md'));

        $provider = (string) file_get_contents($base.'/CourseServiceProvider.php');
        $this->assertStringContainsString('namespace App\\Modules\\Course;', $provider);
        $this->assertStringNotContainsString('{{ ModuleName }}', $provider);

        // Ensure root tests/E2E survives subsequent runs and is not recreated destructively.
        $this->artisan('modulate:make', ['name' => 'Billing'])
            ->assertExitCode(0);

        $this->assertDirectoryExists(base_path('tests/E2E'));
        $this->assertFileExists(base_path('tests/E2E/README.md'));
    }

    public function test_make_with_minimal_generates_single_route_and_skips_optional_defaults(): void
    {
        $this->artisan('modulate:make', ['name' => 'Billing', '--minimal' => true])
            ->assertExitCode(0);

        $base = app_path('Modules/Billing');

        $this->assertFileExists($base.'/Routes/routes.php');
        $this->assertFileDoesNotExist($base.'/Routes/web.php');
        $this->assertFileDoesNotExist($base.'/Routes/api.php');
        $this->assertDirectoryDoesNotExist($base.'/Events');
        $this->assertDirectoryDoesNotExist($base.'/Listeners');
        $this->assertDirectoryDoesNotExist($base.'/Resources');
    }

    public function test_make_with_api_only_skips_web_controller_and_web_routes(): void
    {
        $this->artisan('modulate:make', ['name' => 'Catalog', '--api-only' => true])
            ->assertExitCode(0);

        $base = app_path('Modules/Catalog');

        $this->assertFileExists($base.'/Controllers/Api/CatalogApiController.php');
        $this->assertDirectoryDoesNotExist($base.'/Controllers/Web');
        $this->assertFileExists($base.'/Routes/api.php');
        $this->assertFileDoesNotExist($base.'/Routes/web.php');
    }

    public function test_make_with_add_and_skip_combinations(): void
    {
        $this->artisan('modulate:make', [
            'name' => 'Academy',
            '--add' => 'repositories,actions,dtos,enums,policies,dusk',
            '--skip' => 'events,listeners,e2e',
        ])->assertExitCode(0);

        $base = app_path('Modules/Academy');

        $this->assertFileExists($base.'/Contracts/AcademyRepositoryInterface.php');
        $this->assertDirectoryExists($base.'/Repositories');
        $this->assertFileExists($base.'/Actions/AcademyAction.php');
        $this->assertFileExists($base.'/DTOs/AcademyData.php');
        $this->assertFileExists($base.'/Enums/AcademyStatus.php');
        $this->assertFileExists($base.'/Policies/AcademyPolicy.php');

        $this->assertDirectoryDoesNotExist($base.'/Events');
        $this->assertDirectoryDoesNotExist($base.'/Listeners');
        $this->assertDirectoryDoesNotExist($base.'/Tests/E2E');
        $this->assertFileDoesNotExist($base.'/Tests/E2E/AcademyDuskTest.php');
    }

    public function test_make_with_add_dusk_generates_dusk_test_when_e2e_enabled(): void
    {
        $this->artisan('modulate:make', [
            'name' => 'Portal',
            '--add' => 'dusk',
        ])->assertExitCode(0);

        $base = app_path('Modules/Portal');

        $this->assertFileExists($base.'/Tests/E2E/PortalE2ETest.php');
        $this->assertFileExists($base.'/Tests/E2E/PortalDuskTest.php');
    }

    private function cleanupModules(): void
    {
        foreach (['Course', 'Billing', 'Catalog', 'Academy', 'Portal'] as $module) {
            $this->files->deleteDirectory(app_path('Modules/'.$module));
        }
    }
}
