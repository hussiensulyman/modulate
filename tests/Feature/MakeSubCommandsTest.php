<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class MakeSubCommandsTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->files->deleteDirectory(app_path('Modules/Course'));
        $this->files->deleteDirectory(base_path('tests/E2E'));

        $this->artisan('modulate:make', ['name' => 'Course', '--minimal' => true])
            ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory(app_path('Modules/Course'));
        $this->files->deleteDirectory(base_path('tests/E2E'));

        parent::tearDown();
    }

    public function test_make_service_generates_service_and_contract(): void
    {
        $this->artisan('modulate:make-service', [
            'module' => 'Course',
            'name' => 'Lesson',
        ])->assertExitCode(0);

        $servicePath = app_path('Modules/Course/Services/LessonService.php');
        $contractPath = app_path('Modules/Course/Contracts/LessonServiceInterface.php');

        $this->assertFileExists($servicePath);
        $this->assertFileExists($contractPath);
        $this->assertNoPlaceholderTokens($servicePath);
        $this->assertNoPlaceholderTokens($contractPath);
    }

    public function test_make_event_generates_event_and_listener(): void
    {
        $this->artisan('modulate:make-event', [
            'module' => 'Course',
            'name' => 'SubscriptionActivated',
        ])->assertExitCode(0);

        $eventPath = app_path('Modules/Course/Events/SubscriptionActivated.php');
        $listenerPath = app_path('Modules/Course/Listeners/HandleSubscriptionActivated.php');

        $this->assertFileExists($eventPath);
        $this->assertFileExists($listenerPath);
        $this->assertNoPlaceholderTokens($eventPath);
        $this->assertNoPlaceholderTokens($listenerPath);
    }

    public function test_make_contract_generates_interface(): void
    {
        $this->artisan('modulate:make-contract', [
            'module' => 'Course',
            'name' => 'CourseRepository',
        ])->assertExitCode(0);

        $path = app_path('Modules/Course/Contracts/CourseRepositoryInterface.php');

        $this->assertFileExists($path);
        $this->assertNoPlaceholderTokens($path);
    }

    public function test_make_migration_generates_module_scoped_migration(): void
    {
        $this->artisan('modulate:make-migration', [
            'module' => 'Course',
            'name' => 'create_courses_table',
        ])->assertExitCode(0);

        $migrations = glob(app_path('Modules/Course/Migrations/*_create_courses_table.php'));

        $this->assertIsArray($migrations);
        $this->assertNotEmpty($migrations);
        $this->assertNoPlaceholderTokens($migrations[0]);
    }

    private function assertNoPlaceholderTokens(string $path): void
    {
        $content = (string) file_get_contents($path);

        $this->assertStringNotContainsString('{{ ModuleName }}', $content);
        $this->assertStringNotContainsString('{{ ModuleNamespace }}', $content);
        $this->assertStringNotContainsString('{{ ClassName }}', $content);
    }
}
