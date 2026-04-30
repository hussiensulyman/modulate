<?php

declare(strict_types=1);

namespace Modulate;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

class ModulateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/modulate.php', 'modulate');

        foreach ($this->discoverModuleProviders() as $providerClass) {
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();

            $this->publishes([
                dirname(__DIR__).'/config/modulate.php' => config_path('modulate.php'),
            ], 'modulate-config');

            $this->publishes([
                dirname(__DIR__).'/stubs/modulate' => base_path('stubs/modulate'),
            ], 'modulate-stubs');

            $this->publishes([
                dirname(__DIR__).'/config/modulate.php' => config_path('modulate.php'),
                dirname(__DIR__).'/stubs/modulate' => base_path('stubs/modulate'),
            ], 'modulate-install');
        }

        if (
            (bool) config('modulate.check_on_optimize', true)
            && method_exists($this, 'optimizes')
        ) {
            $this->optimizes(optimize: 'modulate:check');
        }
    }

    /**
     * @return array<int, class-string>
     */
    private function discoverModuleProviders(): array
    {
        $providers = (array) config('modulate.providers', []);
        $discoveryMode = (string) config('modulate.discovery', 'auto');

        if (! in_array($discoveryMode, ['auto', 'hybrid'], true)) {
            return array_values(array_unique($providers));
        }

        $modulesPath = app_path((string) config('modulate.path', 'Modules'));
        if (! is_dir($modulesPath)) {
            return array_values(array_unique($providers));
        }

        $baseNamespace = trim((string) config('modulate.namespace', 'App\\Modules'), '\\');

        $finder = (new Finder())
            ->files()
            ->depth('== 1')
            ->name('*ServiceProvider.php')
            ->in($modulesPath);

        foreach ($finder as $file) {
            $moduleName = basename($file->getPath());
            $className = $file->getBasename('.php');
            $providers[] = $baseNamespace.'\\'.$moduleName.'\\'.$className;
        }

        return array_values(array_unique($providers));
    }

    private function registerCommands(): void
    {
        $commandsPath = __DIR__.'/Commands';
        if (! is_dir($commandsPath)) {
            return;
        }

        $finder = (new Finder())
            ->files()
            ->name('*Command.php')
            ->in($commandsPath);

        $commands = [];

        foreach ($finder as $file) {
            $class = 'Modulate\\Commands\\'.$file->getBasename('.php');

            if (class_exists($class)) {
                $commands[] = $class;
            }
        }

        if ($commands !== []) {
            $this->commands($commands);
        }
    }
}