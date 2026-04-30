<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class MoveCommand extends Command
{
    protected $signature = 'modulate:move
        {name : Module name}
        {target : Target directory path}
        {--dry-run : Preview move and rewrite changes without writing files}';

    protected $description = 'Move a module to another path and rewrite namespaces/imports.';

    public function handle(): int
    {
        $module = Str::studly((string) $this->argument('name'));
        $targetArgument = trim((string) $this->argument('target'));
        $dryRun = (bool) $this->option('dry-run');

        if ($module === '' || $targetArgument === '') {
            $this->components->error('Module name and target are required.');

            return self::FAILURE;
        }

        $sourcePath = $this->modulesRoot().'/'.$module;
        if (! is_dir($sourcePath)) {
            $this->components->error(sprintf('Module [%s] was not found.', $module));

            return self::FAILURE;
        }

        $targetPath = $this->normalizeTargetPath($targetArgument, $module);
        if (is_dir($targetPath)) {
            $this->components->error('Target path already exists.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->confirm('This will move the module and rewrite namespaces/imports. Continue?', false)) {
            $this->components->warn('Aborted. Use --dry-run to preview changes first.');

            return self::FAILURE;
        }

        $oldNamespace = $this->moduleNamespace($module);
        $newNamespace = $this->namespaceFromPath($targetPath);

        $replacements = [
            $oldNamespace => $newNamespace,
            str_replace(base_path().'/', '', $sourcePath) => str_replace(base_path().'/', '', $targetPath),
        ];

        $rewriteFiles = $this->filesNeedingRewrite($replacements);

        $this->components->info(sprintf('Files to rewrite: %d', count($rewriteFiles)));
        foreach ($rewriteFiles as $path) {
            $this->line('- '.str_replace(base_path().'/', '', $path));
        }

        $this->line(sprintf('- move %s -> %s', $sourcePath, $targetPath));

        if ($dryRun) {
            $this->components->info('Dry-run complete. No changes were applied.');

            return self::SUCCESS;
        }

        $this->applyRewrites($rewriteFiles, $replacements);

        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists(dirname($targetPath));

        if (! $filesystem->moveDirectory($sourcePath, $targetPath)) {
            $this->components->error('Unable to move module directory.');

            return self::FAILURE;
        }

        $this->components->info(sprintf('Module [%s] moved to [%s].', $module, $targetPath));

        return self::SUCCESS;
    }

    /**
     * @param array<string, string> $replacements
     *
     * @return array<int, string>
     */
    private function filesNeedingRewrite(array $replacements): array
    {
        $paths = [];

        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in(app_path());

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $content = $file->getContents();

            foreach ($replacements as $search => $replace) {
                if ($search !== $replace && str_contains($content, $search)) {
                    $paths[] = $path;
                    break;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param array<int, string> $files
     * @param array<string, string> $replacements
     */
    private function applyRewrites(array $files, array $replacements): void
    {
        $filesystem = new Filesystem();

        foreach ($files as $path) {
            $content = (string) $filesystem->get($path);
            $updated = str_replace(array_keys($replacements), array_values($replacements), $content);

            if ($updated !== $content) {
                $filesystem->put($path, $updated);
            }
        }
    }

    private function normalizeTargetPath(string $target, string $module): string
    {
        $path = $this->isAbsolutePath($target) ? $target : base_path($target);
        $trimmed = rtrim($path, DIRECTORY_SEPARATOR);

        if (basename($trimmed) !== $module) {
            $trimmed .= DIRECTORY_SEPARATOR.$module;
        }

        return $trimmed;
    }

    private function namespaceFromPath(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        $appRoot = str_replace('\\', '/', app_path());

        if (str_starts_with($normalized, $appRoot.'/')) {
            $relative = trim(substr($normalized, strlen($appRoot)), '/');

            return 'App'.($relative === '' ? '' : '\\'.str_replace('/', '\\', $relative));
        }

        return $this->moduleNamespace(Str::studly(basename($absolutePath)));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\/]/', $path) === 1;
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
