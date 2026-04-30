<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Modulate\Support\ModuleAnalyzer;
use Symfony\Component\Finder\Finder;

class HealthCommand extends Command
{
    protected $signature = 'modulate:health';

    protected $description = 'Boot each module in isolation and verify bindings, routes, migrations, and tests.';

    public function handle(): int
    {
        $analyzer = new ModuleAnalyzer();
        $modules = $analyzer->discoverModules();

        if ($modules === []) {
            $this->components->warn('No modules found.');

            return self::SUCCESS;
        }

        $rows = [];
        $hasFailures = false;

        foreach ($modules as $module) {
            $result = $this->inspectModule($module, $analyzer);

            $rows[] = [
                $module,
                $result['boot'],
                $result['bindings'],
                $result['routes'],
                $result['migrations'],
                $result['tests'],
                $result['status'],
            ];

            if ($result['status'] === '✗') {
                $hasFailures = true;
            }
        }

        $this->table(['Module', 'Boot', 'Bindings', 'Routes', 'Migrations', 'Tests', 'Status'], $rows);

        if ($hasFailures) {
            $this->components->error('Health check failed for one or more modules.');

            return self::FAILURE;
        }

        $this->components->info('All modules passed health checks.');

        return self::SUCCESS;
    }

    /**
     * @return array{boot: string, bindings: string, routes: string, migrations: string, tests: string, status: string}
     */
    private function inspectModule(string $module, ModuleAnalyzer $analyzer): array
    {
        $providerClass = $analyzer->moduleProviderClass($module);
        $providerPath = $analyzer->moduleProviderPath($module);
        $this->loadModuleClassFiles($analyzer->modulePath($module));

        $bootOk = true;
        $bindingOk = true;
        $routesOk = true;
        $migrationsOk = true;
        $testsOk = true;
        $providerContent = '';

        if (! is_file($providerPath) || ! class_exists($providerClass)) {
            $bootOk = false;
            $bindingOk = false;
        } else {
            $providerContent = (string) file_get_contents($providerPath);

            try {
                $provider = new $providerClass($this->laravel);
                $provider->register();
                $provider->boot();
            } catch (\Throwable) {
                $bootOk = false;
                $bindingOk = false;
            }
        }

        if ($bindingOk) {
            foreach ($analyzer->moduleContracts($module) as $contract) {
                $fqcn = $analyzer->moduleNamespace($module).'\\Contracts\\'.$contract;

                if (
                    ! str_contains($providerContent ?? '', $contract.'::class')
                    && ! str_contains($providerContent ?? '', '\\Contracts\\'.$contract)
                ) {
                    $bindingOk = false;
                    break;
                }

                try {
                    $this->laravel->make($fqcn);
                } catch (\Throwable) {
                    $bindingOk = false;
                    break;
                }
            }
        }

        $routesPath = $analyzer->modulePath($module).'/Routes';
        $routeFiles = [];
        foreach (['web.php', 'api.php', 'routes.php'] as $routeFile) {
            $path = $routesPath.'/'.$routeFile;
            if (is_file($path)) {
                $routeFiles[] = $path;
            }
        }

        if ($routeFiles === []) {
            $routesOk = false;
        } else {
            $routesOk = $this->routesLoadSafely($routeFiles);
        }

        $migrationsOk = is_dir($analyzer->modulePath($module).'/Migrations');
        $testsOk = is_dir($analyzer->modulePath($module).'/Tests');

        $status = ($bootOk && $bindingOk && $routesOk && $migrationsOk && $testsOk) ? '✓' : '✗';

        return [
            'boot' => $bootOk ? '✓' : '✗',
            'bindings' => $bindingOk ? '✓' : '✗',
            'routes' => $routesOk ? '✓' : '✗',
            'migrations' => $migrationsOk ? '✓' : '✗',
            'tests' => $testsOk ? '✓' : '✗',
            'status' => $status,
        ];
    }

    /**
     * @param array<int, string> $routeFiles
     */
    private function routesLoadSafely(array $routeFiles): bool
    {
        $router = $this->laravel['router'];

        foreach ($routeFiles as $routeFile) {
            try {
                Route::middleware([])->group($routeFile);
            } catch (\Throwable) {
                return false;
            }
        }

        return $router !== null;
    }

    private function loadModuleClassFiles(string $modulePath): void
    {
        if (! is_dir($modulePath)) {
            return;
        }

        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in($modulePath)
            ->exclude(['Routes', 'Migrations', 'Tests']);

        foreach ($finder as $file) {
            require_once $file->getRealPath() ?: $file->getPathname();
        }
    }
}