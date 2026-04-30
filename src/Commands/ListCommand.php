<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class ListCommand extends Command
{
    protected $signature = 'modulate:list';

    protected $description = 'List modules with contracts and status.';

    public function handle(): int
    {
        $modulesRoot = app_path((string) config('modulate.path', 'Modules'));

        if (! is_dir($modulesRoot)) {
            $this->components->warn('No modules directory found.');

            return self::SUCCESS;
        }

        $baseNamespace = trim((string) config('modulate.namespace', 'App\\Modules'), '\\');
        $configuredProviders = (array) config('modulate.providers', []);

        $finder = (new Finder())
            ->directories()
            ->depth('== 0')
            ->in($modulesRoot)
            ->sortByName();

        $rows = [];

        foreach ($finder as $directory) {
            $moduleName = $directory->getBasename();
            $providerPath = $directory->getPathname().'/'.$moduleName.'ServiceProvider.php';
            $providerClass = $baseNamespace.'\\'.$moduleName.'\\'.$moduleName.'ServiceProvider';

            $contracts = $this->moduleContracts($directory->getPathname().'/Contracts');

            $status = 'Present';
            if (! is_file($providerPath)) {
                $status = 'Provider missing';
            } elseif (in_array($providerClass, $configuredProviders, true)) {
                $status = 'Configured';
            } elseif (class_exists($providerClass) && $this->laravel->providerIsLoaded($providerClass)) {
                $status = 'Loaded';
            }

            $rows[] = [
                $moduleName,
                $contracts === [] ? ' - ' : implode(PHP_EOL, $contracts),
                $status,
            ];
        }

        $this->table(['Module', 'Contracts', 'Status'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function moduleContracts(string $contractsPath): array
    {
        if (! is_dir($contractsPath)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->name('*Interface.php')
            ->in($contractsPath)
            ->sortByName();

        $contracts = [];

        foreach ($finder as $contractFile) {
            $contracts[] = $contractFile->getBasename('.php');
        }

        return $contracts;
    }
}
