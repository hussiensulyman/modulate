<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class RenameCommand extends Command
{
    protected $signature = 'modulate:rename
        {from : Existing module name}
        {to : New module name}
        {--dry-run : Preview all changes without writing files}';

    protected $description = 'Rename a module and rewrite namespaces/imports across app files.';

    public function handle(): int
    {
        $from = Str::studly((string) $this->argument('from'));
        $to = Str::studly((string) $this->argument('to'));
        $dryRun = (bool) $this->option('dry-run');

        if ($from === '' || $to === '' || $from === $to) {
            $this->components->error('Provide different non-empty source and target module names.');

            return self::FAILURE;
        }

        $sourcePath = $this->modulesRoot().'/'.$from;
        $targetPath = $this->modulesRoot().'/'.$to;

        if (! is_dir($sourcePath)) {
            $this->components->error(sprintf('Module [%s] was not found.', $from));

            return self::FAILURE;
        }

        if (is_dir($targetPath)) {
            $this->components->error(sprintf('Target module [%s] already exists.', $to));

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->confirm('This will rename module files and rewrite imports. Continue?', false)) {
            $this->components->warn('Aborted. Use --dry-run to preview changes first.');

            return self::FAILURE;
        }

        [$filesToRewrite, $replacementMap] = $this->rewritePlan($from, $to);

        $this->components->info(sprintf('Files to rewrite: %d', count($filesToRewrite)));
        foreach ($filesToRewrite as $path) {
            $this->line('- '.str_replace(base_path().'/', '', $path));
        }

        if ($dryRun) {
            $this->line(sprintf('- would move %s -> %s', $sourcePath, $targetPath));
            $this->components->info('Dry-run complete. No changes were applied.');

            return self::SUCCESS;
        }

        $this->applyRewrites($filesToRewrite, $replacementMap);

        $filesystem = new Filesystem();
        if (! $filesystem->moveDirectory($sourcePath, $targetPath)) {
            $this->components->error('Unable to move renamed module directory.');

            return self::FAILURE;
        }

        $oldProvider = $targetPath.'/'.$from.'ServiceProvider.php';
        $newProvider = $targetPath.'/'.$to.'ServiceProvider.php';
        if (is_file($oldProvider)) {
            $filesystem->move($oldProvider, $newProvider);
        }

        $this->components->info(sprintf('Module [%s] renamed to [%s].', $from, $to));

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     */
    private function rewritePlan(string $from, string $to): array
    {
        $oldNamespace = $this->moduleNamespace($from);
        $newNamespace = $this->moduleNamespace($to);

        $replacements = [
            $oldNamespace => $newNamespace,
            'Modules/'.$from => 'Modules/'.$to,
            $from.'ServiceProvider' => $to.'ServiceProvider',
        ];

        $files = [];

        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in(app_path());

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $content = $file->getContents();

            foreach ($replacements as $search => $replace) {
                if ($search !== $replace && str_contains($content, $search)) {
                    $files[] = $path;
                    break;
                }
            }
        }

        return [array_values(array_unique($files)), $replacements];
    }

    /**
     * @param array<int, string> $files
     * @param array<string, string> $replacementMap
     */
    private function applyRewrites(array $files, array $replacementMap): void
    {
        $filesystem = new Filesystem();

        foreach ($files as $path) {
            $content = (string) $filesystem->get($path);
            $updated = str_replace(array_keys($replacementMap), array_values($replacementMap), $content);

            if ($updated !== $content) {
                $filesystem->put($path, $updated);
            }
        }
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
