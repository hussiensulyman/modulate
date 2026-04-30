<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class HealthCommandTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->files->deleteDirectory(app_path('Modules'));

        $this->seedHealthyModule();
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory(app_path('Modules'));

        parent::tearDown();
    }

    public function test_health_outputs_success_table_for_valid_module(): void
    {
        $this->artisan('modulate:health')
            ->expectsOutputToContain('Module')
            ->expectsOutputToContain('Course')
            ->expectsOutputToContain('All modules passed health checks.')
            ->assertExitCode(0);
    }

    public function test_health_reports_failure_when_binding_is_missing(): void
    {
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

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }
}
PHP
        );

        $this->artisan('modulate:health')
            ->expectsOutputToContain('Course')
            ->expectsOutputToContain('Health check failed for one or more modules.')
            ->assertExitCode(1);
    }

    private function seedHealthyModule(): void
    {
        $base = app_path('Modules/Course');

        $this->files->ensureDirectoryExists($base.'/Contracts');
        $this->files->ensureDirectoryExists($base.'/Services');
        $this->files->ensureDirectoryExists($base.'/Routes');
        $this->files->ensureDirectoryExists($base.'/Migrations');
        $this->files->ensureDirectoryExists($base.'/Tests/Unit');

        $this->files->put(
            $base.'/Contracts/CourseServiceInterface.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course\Contracts;

interface CourseServiceInterface
{
    public function find(int $id): array;
}
PHP
        );

        $this->files->put(
            $base.'/Services/CourseService.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course\Services;

use App\Modules\Course\Contracts\CourseServiceInterface;

class CourseService implements CourseServiceInterface
{
    public function find(int $id): array
    {
        return ['id' => $id];
    }
}
PHP
        );

        $this->files->put(
            $base.'/CourseServiceProvider.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course;

use Illuminate\Support\ServiceProvider;

class CourseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Modules\Course\Contracts\CourseServiceInterface::class, \App\Modules\Course\Services\CourseService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }
}
PHP
        );

        $this->files->put($base.'/Routes/routes.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/health-course', fn () => 'ok');\n");
        $this->files->put($base.'/Migrations/2026_01_01_000000_create_courses_table.php', "<?php\n\nreturn new class {};\n");
        $this->files->put($base.'/Tests/Unit/CourseTest.php', "<?php\n\nnamespace App\\Modules\\Course\\Tests\\Unit;\n\nclass CourseTest {}\n");
    }
}