<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class InitCommandTest extends TestCase
{
    private Filesystem $files;

    /**
     * @var array<string, string|null>
     */
    private array $backups = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();

        $this->backupFile(base_path('routes/web.php'));
        $this->backupFile(base_path('routes/api.php'));
        $this->backupFile(app_path('Models/User.php'));
        $this->backupFile(config_path('auth.php'));

        $this->files->ensureDirectoryExists(app_path('Models'));
        $this->files->ensureDirectoryExists(app_path('Http/Controllers'));
        $this->files->ensureDirectoryExists(app_path('Http/Middleware'));
        $this->files->ensureDirectoryExists(base_path('routes'));

        $this->files->put(base_path('routes/web.php'), "<?php\n\nRoute::get('/', fn () => 'ok');\n");
        $this->files->put(base_path('routes/api.php'), "<?php\n\nRoute::get('/ping', fn () => ['ok' => true]);\n");
        $this->files->put(app_path('Models/User.php'), "<?php\n\nnamespace App\\Models;\n\nclass User {}\n");

        if (file_exists(config_path('auth.php'))) {
            $auth = (string) $this->files->get(config_path('auth.php'));
            $auth = str_replace(
                "'model' => App\\\\Modules\\\\Auth\\\\Models\\\\User::class,",
                "'model' => App\\\\Models\\\\User::class,",
                $auth
            );
            $this->files->put(config_path('auth.php'), $auth);
        }

        $this->cleanupGenerated();
    }

    protected function tearDown(): void
    {
        $this->cleanupGenerated();

        foreach ($this->backups as $path => $content) {
            if ($content === null) {
                @unlink($path);
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $content);
        }

        parent::tearDown();
    }

    public function test_init_dry_run_output_and_no_write(): void
    {
        $exit = $this->runInit(['--dry-run' => true]);
        $output = $this->consoleOutput();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Running in dry-run mode', $output);
        $this->assertStringContainsString('Step 1/8', $output);
        $this->assertStringContainsString('Dry-run complete. No changes were applied.', $output);
        $this->assertFalse(is_dir(app_path('Modules/Shared')));
        $this->assertFalse(is_dir(app_path('Modules/Auth')));
    }

    public function test_init_creates_modules_and_alias_in_default_mode(): void
    {
        $exit = $this->runInit();

        $this->assertSame(0, $exit);
        $this->assertDirectoryExists(app_path('Modules/Shared'));
        $this->assertDirectoryExists(app_path('Modules/Auth'));
        $this->assertFileExists(app_path('Modules/Auth/Models/User.php'));
        $this->assertFileExists(app_path('Models/User.php'));

        $aliasContent = (string) file_get_contents(app_path('Models/User.php'));
        $this->assertStringContainsString('class_alias', $aliasContent);
        $this->assertStringContainsString('App\\Modules\\Auth\\Models\\User', $aliasContent);
    }

    public function test_init_strict_removes_alias_and_updates_auth_config(): void
    {
        $exit = $this->runInit(['--strict' => true]);

        $this->assertSame(0, $exit);
        $this->assertFileExists(app_path('Modules/Auth/Models/User.php'));
        $this->assertFileDoesNotExist(app_path('Models/User.php'));

        if (file_exists(config_path('auth.php'))) {
            $auth = (string) file_get_contents(config_path('auth.php'));
            $this->assertStringContainsString("'model' => App\\Modules\\Auth\\Models\\User::class,", $auth);
        }
    }

    public function test_init_skip_shared_flag(): void
    {
        $exit = $this->runInit(['--skip' => 'shared']);

        $this->assertSame(0, $exit);
        $this->assertDirectoryDoesNotExist(app_path('Modules/Shared'));
        $this->assertDirectoryExists(app_path('Modules/Auth'));
    }

    public function test_init_replaces_route_stubs_and_creates_middleware_readme(): void
    {
        $exit = $this->runInit();

        $this->assertSame(0, $exit);

        $web = (string) file_get_contents(base_path('routes/web.php'));
        $api = (string) file_get_contents(base_path('routes/api.php'));
        $readme = (string) file_get_contents(app_path('Http/Middleware/README.md'));

        $this->assertStringContainsString('Module routes are automatically loaded', $web);
        $this->assertStringContainsString('Module routes are automatically loaded', $api);
        $this->assertStringContainsString('Global Middleware', $readme);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runInit(array $options = []): int
    {
        return $this->app->make(Kernel::class)->call('modulate:init', $options);
    }

    private function consoleOutput(): string
    {
        return $this->app->make(Kernel::class)->output();
    }

    private function backupFile(string $path): void
    {
        $this->backups[$path] = file_exists($path) ? (string) file_get_contents($path) : null;
    }

    private function cleanupGenerated(): void
    {
        $this->files->deleteDirectory(app_path('Modules'));
        @unlink(app_path('Http/Middleware/README.md'));

        foreach ([app_path('Models/README.md'), app_path('Models/.gitkeep')] as $marker) {
            @unlink($marker);
        }
    }
}
