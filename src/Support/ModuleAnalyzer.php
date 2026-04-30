<?php

declare(strict_types=1);

namespace Modulate\Support;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class ModuleAnalyzer
{
    /**
     * @return array<int, string>
     */
    public function discoverModules(): array
    {
        $modulesRoot = $this->modulesRoot();
        if (! is_dir($modulesRoot)) {
            return [];
        }

        $finder = (new Finder())
            ->directories()
            ->depth('== 0')
            ->in($modulesRoot)
            ->sortByName();

        $modules = [];

        foreach ($finder as $directory) {
            $modules[] = $directory->getBasename();
        }

        return $modules;
    }

    public function modulesRoot(): string
    {
        return app_path((string) config('modulate.path', 'Modules'));
    }

    public function modulePath(string $module): string
    {
        return $this->modulesRoot().'/'.$module;
    }

    public function moduleNamespace(string $module): string
    {
        $baseNamespace = trim((string) config('modulate.namespace', 'App\\Modules'), '\\');

        return $baseNamespace.'\\'.$module;
    }

    public function moduleProviderClass(string $module): string
    {
        return $this->moduleNamespace($module).'\\'.$module.'ServiceProvider';
    }

    public function moduleProviderPath(string $module): string
    {
        return $this->modulePath($module).'/'.$module.'ServiceProvider.php';
    }

    /**
     * @return array<int, string>
     */
    public function moduleContracts(string $module): array
    {
        $contractsPath = $this->modulePath($module).'/Contracts';
        if (! is_dir($contractsPath)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->name('*Interface.php')
            ->in($contractsPath)
            ->sortByName();

        $contracts = [];

        foreach ($finder as $file) {
            $contracts[] = $file->getBasename('.php');
        }

        return $contracts;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function contractDependencies(string $module): array
    {
        $dependencies = [];

        foreach ($this->modulePhpFiles($module) as $filePath => $content) {
            if (str_ends_with($filePath, '/Contracts') || str_contains($filePath, '/Contracts/')) {
                continue;
            }

            preg_match_all(
                '/^\s*use\s+App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\Contracts\\\\([A-Za-z0-9_]+Interface)\s*;/m',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $targetModule = $match[1];
                $contract = $match[2];

                if (strcasecmp($targetModule, $module) === 0) {
                    continue;
                }

                $dependencies[$targetModule] ??= [];
                if (! in_array($contract, $dependencies[$targetModule], true)) {
                    $dependencies[$targetModule][] = $contract;
                }
            }
        }

        ksort($dependencies);

        return $dependencies;
    }

    /**
     * @return array{fires: array<int, array{target: string, event: string}>, listens: array<int, array{target: string, event: string}>}
     */
    public function eventDependencies(string $module): array
    {
        $fires = [];
        $listens = [];

        foreach ($this->modulePhpFiles($module) as $filePath => $content) {
            $namespace = $this->namespaceFromContent($content);
            $imports = $this->importsFromContent($content);

            preg_match_all('/\bevent\s*\(\s*new\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)/m', $content, $dispatches);
            foreach ($dispatches[1] as $className) {
                $fqcn = $this->resolveClassName($className, $namespace, $imports);
                $edge = $this->eventEdgeFromClass($module, $fqcn);
                if ($edge !== null) {
                    $fires[$edge['target'].'|'.$edge['event']] = $edge;
                }
            }

            if (str_contains($filePath, '/Listeners/')) {
                preg_match_all(
                    '/function\s+handle\s*\(\s*([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)\s+\$[A-Za-z_][A-Za-z0-9_]*\s*\)/m',
                    $content,
                    $listenerMatches
                );

                foreach ($listenerMatches[1] as $typeHint) {
                    $fqcn = $this->resolveClassName($typeHint, $namespace, $imports);
                    $edge = $this->eventEdgeFromClass($module, $fqcn);
                    if ($edge !== null) {
                        $listens[$edge['target'].'|'.$edge['event']] = $edge;
                    }
                }
            }
        }

        return [
            'fires' => array_values($fires),
            'listens' => array_values($listens),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function exposedContractsWithMethods(string $module): array
    {
        $contractsPath = $this->modulePath($module).'/Contracts';
        if (! is_dir($contractsPath)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->name('*Interface.php')
            ->in($contractsPath)
            ->sortByName();

        $contracts = [];

        foreach ($finder as $file) {
            $methods = [];
            preg_match_all(
                '/public\s+function\s+([A-Za-z0-9_]+)\s*\(([^)]*)\)\s*:\s*([^;{]+)\s*;/m',
                $file->getContents(),
                $methodMatches,
                PREG_SET_ORDER
            );

            foreach ($methodMatches as $methodMatch) {
                $signature = sprintf(
                    '%s(%s): %s',
                    $methodMatch[1],
                    trim($methodMatch[2]),
                    trim($methodMatch[3])
                );

                $methods[] = preg_replace('/\s+/', ' ', $signature) ?? $signature;
            }

            $contracts[$file->getBasename('.php')] = $methods;
        }

        return $contracts;
    }

    /**
     * @return array<int, string>
     */
    public function migrationFiles(string $module): array
    {
        $migrationsPath = $this->modulePath($module).'/Migrations';
        if (! is_dir($migrationsPath)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in($migrationsPath)
            ->sortByName();

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath() ?: $file->getPathname();
        }

        return $files;
    }

    /**
     * @return array<int, string>
     */
    public function foreignKeyCandidates(string $module): array
    {
        $candidates = [];

        foreach ($this->migrationFiles($module) as $path) {
            $content = (string) file_get_contents($path);

            preg_match_all(
                "/foreignId\\('([A-Za-z0-9_]+)'\\)\\s*->\\s*constrained\\('([A-Za-z0-9_]+)'\\)/",
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $candidates[] = sprintf('%s -> %s.id', $match[1], $match[2]);
            }

            preg_match_all(
                "/foreign\\('([A-Za-z0-9_]+)'\\).*?->on\\('([A-Za-z0-9_]+)'\\)/s",
                $content,
                $legacyMatches,
                PREG_SET_ORDER
            );

            foreach ($legacyMatches as $match) {
                $candidates[] = sprintf('%s -> %s.id', $match[1], $match[2]);
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<int, string>
     */
    public function moduleTestDirectories(string $module): array
    {
        $base = $this->modulePath($module).'/Tests';
        $dirs = [];

        foreach (['Unit', 'Feature', 'E2E'] as $type) {
            if (is_dir($base.'/'.$type)) {
                $dirs[] = $base.'/'.$type;
            }
        }

        return $dirs;
    }

    /**
     * @return array<string, string>
     */
    private function modulePhpFiles(string $module): array
    {
        $path = $this->modulePath($module);
        if (! is_dir($path)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in($path);

        $files = [];
        foreach ($finder as $file) {
            $files[$file->getRealPath() ?: $file->getPathname()] = $file->getContents();
        }

        return $files;
    }

    private function namespaceFromContent(string $content): string
    {
        if (! preg_match('/^\s*namespace\s+([^;]+);/m', $content, $match)) {
            return '';
        }

        return trim($match[1]);
    }

    /**
     * @return array<string, string>
     */
    private function importsFromContent(string $content): array
    {
        $imports = [];
        preg_match_all('/^\s*use\s+([^;]+);/m', $content, $matches);

        foreach ($matches[1] as $rawImport) {
            $import = trim($rawImport);

            if (str_starts_with($import, 'function ') || str_starts_with($import, 'const ')) {
                continue;
            }

            $parts = preg_split('/\s+as\s+/i', $import) ?: [];
            $fqcn = ltrim(trim($parts[0] ?? ''), '\\');

            if ($fqcn === '') {
                continue;
            }

            $alias = trim($parts[1] ?? '');
            if ($alias === '') {
                $segments = explode('\\', $fqcn);
                $alias = end($segments) ?: $fqcn;
            }

            $imports[$alias] = $fqcn;
        }

        return $imports;
    }

    /**
     * @param array<string, string> $imports
     */
    private function resolveClassName(string $className, string $namespace, array $imports): string
    {
        $className = trim($className);
        if ($className === '') {
            return '';
        }

        if (str_starts_with($className, '\\')) {
            return ltrim($className, '\\');
        }

        if (str_contains($className, '\\')) {
            return $className;
        }

        if (isset($imports[$className])) {
            return $imports[$className];
        }

        return $namespace === '' ? $className : $namespace.'\\'.$className;
    }

    /**
     * @return array{target: string, event: string}|null
     */
    private function eventEdgeFromClass(string $currentModule, string $fqcn): ?array
    {
        if (! preg_match('/^App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\Events\\\\([A-Za-z0-9_]+)$/', $fqcn, $match)) {
            return null;
        }

        $targetModule = $match[1];
        if (strcasecmp($targetModule, $currentModule) === 0) {
            return null;
        }

        return [
            'target' => $targetModule,
            'event' => $match[2],
        ];
    }

    public function contractEndpointSuggestion(string $module, string $signature): string
    {
        preg_match('/^([A-Za-z0-9_]+)/', $signature, $method);
        preg_match('/\(([^)]*)\)/', $signature, $params);

        $methodName = strtolower($method[1] ?? 'call');
        $paramsString = strtolower(trim($params[1] ?? ''));

        $moduleSlug = Str::kebab(Str::pluralStudly($module));

        $httpVerb = str_starts_with($methodName, 'find')
            || str_starts_with($methodName, 'get')
            || str_starts_with($methodName, 'list')
            ? 'GET'
            : 'POST';

        $path = '/api/'.$moduleSlug;

        if (str_contains($paramsString, '$id')) {
            $path .= '/{id}';
        } elseif (str_contains($paramsString, '$userid')) {
            $path = '/api/users/{userId}/'.$moduleSlug;
        }

        return sprintf('%s %s', $httpVerb, $path);
    }
}