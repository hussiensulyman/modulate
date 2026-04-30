<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class DeleteCommand extends Command
{
    protected $signature = 'modulate:delete
        {name : Module name to delete}
        {--dry-run : Preview deletions and dependency warnings without writing files}';

    protected $description = 'Delete a module and warn about broken cross-module dependencies.';

    public function handle(): int
    {
        $module = Str::studly((string) $this->argument('name'));
        $dryRun = (bool) $this->option('dry-run');

        if ($module === '') {
            $this->components->error('Module name cannot be empty.');

            return self::FAILURE;
        }

        $path = $this->modulesRoot().'/'.$module;
        if (! is_dir($path)) {
            $this->components->error(sprintf('Module [%s] was not found.', $module));

            return self::FAILURE;
        }

        $warnings = $this->collectDependencyWarnings($module);

        $this->line('Files/directories to delete:');
        $this->line('- '.str_replace(base_path().'/', '', $path));

        if ($warnings !== []) {
            $this->newLine();
            $this->components->warn('Potential broken dependencies detected:');
            foreach ($warnings as $warning) {
                $this->line(sprintf(
                    '- %s:%d %s',
                    str_replace(base_path().'/', '', $warning['file']),
                    $warning['line'],
                    $warning['excerpt']
                ));
            }
        }

        if ($dryRun) {
            $this->components->info('Dry-run complete. No changes were applied.');

            return self::SUCCESS;
        }

        if (! $this->confirm('This will permanently delete the module. Continue?', false)) {
            $this->components->warn('Aborted. Use --dry-run to preview changes first.');

            return self::FAILURE;
        }

        (new Filesystem())->deleteDirectory($path);
        $this->components->info(sprintf('Module [%s] deleted.', $module));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{file: string, line: int, excerpt: string}>
     */
    private function collectDependencyWarnings(string $module): array
    {
        $warnings = [];
        $namespaceNeedle = $this->moduleNamespace($module).'\\';

        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in(app_path());

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();

            if (str_contains($path, DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $lines = preg_split('/\R/', $file->getContents()) ?: [];
            foreach ($lines as $index => $line) {
                if (! str_contains($line, $namespaceNeedle)) {
                    continue;
                }

                $warnings[] = [
                    'file' => $path,
                    'line' => $index + 1,
                    'excerpt' => trim($line),
                ];
            }
        }

        return $warnings;
    }

    private function modulesRoot(): string
    {
        return app_path((string) config('modulate.path', 'Modules'));
    }

    private function moduleNamespace(string $moduleName): string
    {
        $baseNamespace = trim((string) config('modulate.namespace', 'App\\Modules'), '\\');

        return $baseNamespace.'\\'.$moduleName;
    }
}
