<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InitCommand extends Command
{
    protected $signature = 'modulate:init
        {--strict : Move User with no backward-compat alias}
        {--skip= : Comma-separated modules to skip, e.g. shared}
        {--dry-run : Preview changes without writing files}';

    protected $description = 'Bootstrap modular structure in 8 ordered steps.';

    private Filesystem $files;

    private bool $dryRun = false;

    private bool $strict = false;

    /**
     * @var array<int, string>
     */
    private array $skip = [];

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem();
    }

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->strict = (bool) $this->option('strict');
        $this->skip = $this->parseSkip((string) ($this->option('skip') ?? ''));

        if ($this->dryRun) {
            $this->line('Running in dry-run mode. No files will be modified.');
        }

        $this->step(1, 'Shared module');
        $this->stepSharedModule();

        $this->step(2, 'Auth module + User model');
        $this->stepAuthModule();

        $this->step(3, 'User alias/move');
        $this->stepUserAliasOrMove();

        $this->step(4, 'Route stubs');
        $this->stepReplaceRoutes();

        $this->step(5, 'Cleanup controllers folder');
        $this->stepCleanupControllers();

        $this->step(6, 'Middleware README');
        $this->stepMiddlewareReadme();

        $this->step(7, 'Empty-folder markers');
        $this->stepEmptyFolderMarkers();

        $this->step(8, 'Completed');
        $this->line('modulate:init complete.');

        if ($this->dryRun) {
            $this->line('Dry-run complete. No changes were applied.');
        }

        return self::SUCCESS;
    }

    private function step(int $n, string $title): void
    {
        $this->line(sprintf('Step %d/8: %s', $n, $title));
    }

    private function stepSharedModule(): void
    {
        if (in_array('shared', $this->skip, true)) {
            $this->line('- skipped Shared module (--skip=shared)');

            return;
        }

        $base = app_path('Modules/Shared');
        $this->ensureModuleSkeleton($base, 'Shared');
        $this->write($base.'/README.md', $this->readStub('shared-module-readme.stub'));
    }

    private function stepAuthModule(): void
    {
        $base = app_path('Modules/Auth');
        $this->ensureModuleSkeleton($base, 'Auth');
        $this->write($base.'/README.md', $this->readStub('auth-module-readme.stub'));
        $this->write($base.'/Models/User.php', $this->defaultAuthUserModel());
    }

    private function stepUserAliasOrMove(): void
    {
        $legacyUser = app_path('Models/User.php');

        if ($this->strict) {
            $this->delete($legacyUser);
            $this->replaceInFile(
                config_path('auth.php'),
                "'model' => App\\Models\\User::class,",
                "'model' => App\\Modules\\Auth\\Models\\User::class,"
            );
                $this->replaceInFile(
                    config_path('auth.php'),
                    "'model' => env('AUTH_MODEL', Illuminate\\Foundation\\Auth\\User::class),",
                    "'model' => App\\Modules\\Auth\\Models\\User::class,"
                );

            return;
        }

        $alias = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Modules\Auth\Models\User::class, User::class);
PHP;

        $this->write($legacyUser, $alias);
    }

    private function stepReplaceRoutes(): void
    {
        $this->write(base_path('routes/web.php'), $this->readStub('routes-web.stub'));
        $this->write(base_path('routes/api.php'), $this->readStub('routes-api.stub'));
    }

    private function stepCleanupControllers(): void
    {
        $controllers = app_path('Http/Controllers');

        if (! is_dir($controllers)) {
            return;
        }

        $items = array_values(array_diff(scandir($controllers) ?: [], ['.', '..']));
        if ($items === []) {
            if ($this->dryRun) {
                $this->line('- would delete '.str_replace(base_path().'/', '', $controllers));

                return;
            }

            @rmdir($controllers);
        }
    }

    private function stepMiddlewareReadme(): void
    {
        $this->write(
            app_path('Http/Middleware/README.md'),
            $this->readStub('middleware-readme.stub')
        );
    }

    private function stepEmptyFolderMarkers(): void
    {
        $candidates = [
            app_path('Http/Controllers'),
            app_path('Models'),
        ];

        foreach ($candidates as $path) {
            if (! is_dir($path)) {
                $this->mkdir($path);
            }

            $items = array_values(array_diff(scandir($path) ?: [], ['.', '..']));
            if ($items === []) {
                $this->write($path.'/.gitkeep', "");
                $this->write($path.'/README.md', "This folder is intentionally kept for framework compatibility.\n");
            }
        }
    }

    private function ensureModuleSkeleton(string $base, string $module): void
    {
        $dirs = [
            $base,
            $base.'/Contracts',
            $base.'/Services',
            $base.'/Routes',
            $base.'/Migrations',
            $base.'/Tests/Unit',
            $base.'/Tests/Feature',
            $base.'/Models',
        ];

        foreach ($dirs as $dir) {
            $this->mkdir($dir);
        }

        $provider = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Modules\\{$module};

use Illuminate\\Support\\ServiceProvider;

class {$module}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \$this->loadRoutesFrom(__DIR__.'/Routes/routes.php');
        \$this->loadMigrationsFrom(__DIR__.'/Migrations');
    }
}
PHP;

        $routes = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Module routes
PHP;

        $this->write($base.'/'.$module.'ServiceProvider.php', $provider);
        $this->write($base.'/Routes/routes.php', $routes);
    }

    private function mkdir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if ($this->dryRun) {
            $this->line('- would create '.str_replace(base_path().'/', '', $path));

            return;
        }

        $this->files->ensureDirectoryExists($path);
    }

    private function write(string $path, string $content): void
    {
        if ($this->dryRun) {
            $this->line('- would write '.str_replace(base_path().'/', '', $path));

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
    }

    private function delete(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }

        if ($this->dryRun) {
            $this->line('- would delete '.str_replace(base_path().'/', '', $path));

            return;
        }

        $this->files->delete($path);
    }

    private function replaceInFile(string $path, string $search, string $replace): void
    {
        if (! file_exists($path)) {
            return;
        }

        if ($this->dryRun) {
            $this->line('- would update '.str_replace(base_path().'/', '', $path));

            return;
        }

        $content = (string) $this->files->get($path);
        $this->files->put($path, str_replace($search, $replace, $content));
    }

    private function readStub(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/stubs/modulate/'.$name);
    }

    private function defaultAuthUserModel(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
}
PHP;
    }

    /**
     * @return array<int, string>
     */
    private function parseSkip(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(static fn (string $s): string => strtolower(trim($s)), explode(',', $value))));
    }
}
