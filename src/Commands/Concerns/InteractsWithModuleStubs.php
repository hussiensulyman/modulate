<?php

declare(strict_types=1);

namespace Modulate\Commands\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Modulate\Support\StubPublisher;

trait InteractsWithModuleStubs
{
    private Filesystem $files;

    protected function files(): Filesystem
    {
        return $this->files ??= new Filesystem();
    }

    protected function moduleName(string $value): string
    {
        return Str::studly(trim($value));
    }

    protected function moduleBasePath(string $moduleName): string
    {
        $modulesFolder = trim((string) config('modulate.path', 'Modules'), '/');

        return app_path($modulesFolder.'/'.$moduleName);
    }

    protected function moduleNamespace(string $moduleName): string
    {
        $baseNamespace = trim((string) config('modulate.namespace', 'App\\Modules'), '\\');

        return $baseNamespace.'\\'.$moduleName;
    }

    /**
     * @return array<int, string>
     */
    protected function csvOptionList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $value)
        );

        return array_values(array_unique(array_filter($parts)));
    }

    /**
     * @param array<string, string> $replacements
     */
    protected function writeFromStub(string $stubRelativePath, string $targetPath, array $replacements): void
    {
        $stubPath = $this->resolveStubPath($stubRelativePath);

        $content = (string) file_get_contents($stubPath);
        $rendered = StubPublisher::replacePlaceholders($content, $replacements);

        $this->files()->ensureDirectoryExists(dirname($targetPath));
        $this->files()->put($targetPath, $rendered);
    }

    protected function resolveStubPath(string $stubRelativePath): string
    {
        $relative = ltrim($stubRelativePath, '/');
        $published = base_path('stubs/modulate/'.$relative);
        if (is_file($published)) {
            return $published;
        }

        $package = dirname(__DIR__, 3).'/stubs/modulate/'.$relative;
        if (is_file($package)) {
            return $package;
        }

        throw new \RuntimeException(sprintf('Stub not found: %s', $stubRelativePath));
    }

    /**
     * @return array<string, string>
     */
    protected function baseReplacements(string $moduleName, ?string $className = null): array
    {
        return [
            'ModuleName' => $moduleName,
            'ModuleNameLower' => Str::snake($moduleName),
            'ModuleNamespace' => $this->moduleNamespace($moduleName),
            'ClassName' => $className ?? $moduleName,
        ];
    }
}
