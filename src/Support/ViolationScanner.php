<?php

declare(strict_types=1);

namespace Modulate\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;

class ViolationScanner
{
    /**
     * @return array<int, array{type: string, file: string, line: int, message: string}>
     */
    public function scan(): array
    {
        $configuration = config('modulate.check_violations', true);

        if ($configuration === false) {
            return [];
        }

        $modulesRoot = app_path((string) config('modulate.path', 'Modules'));
        if (! is_dir($modulesRoot)) {
            return [];
        }

        $patterns = $this->resolvePatterns($configuration);

        if ($this->useAst()) {
            $astViolations = $this->scanWithAst($modulesRoot, $patterns);
            if ($astViolations !== null) {
                return array_merge($astViolations, $this->findMissingBindings($modulesRoot));
            }
        }

        return array_merge(
            $this->scanWithRegex($modulesRoot, $patterns),
            $this->findMissingBindings($modulesRoot)
        );
    }

    protected function useAst(): bool
    {
        return (bool) config('modulate.use_ast', false);
    }

    /**
     * @param array<int, array{type: string, pattern: string, message: string}> $patterns
     *
     * @return array<int, array{type: string, file: string, line: int, message: string}>|null
     */
    protected function scanWithAst(string $modulesRoot, array $patterns): ?array
    {
        if (! class_exists(AstViolationScanner::class)) {
            Log::warning('AST scanner is unavailable. Falling back to regex violation scanning.');

            return null;
        }

        try {
            $scanner = $this->createAstScanner();
            $astViolations = $scanner->scan($modulesRoot);

            foreach ($scanner->warnings() as $warning) {
                Log::warning($warning);
            }

            $failedFiles = $scanner->failedFiles();
            if ($failedFiles === []) {
                return $astViolations;
            }

            $fallbackViolations = $this->scanWithRegex($modulesRoot, $patterns, $failedFiles);

            return array_merge($astViolations, $fallbackViolations);
        } catch (\Throwable $error) {
            Log::warning(sprintf(
                'AST violation scanner failed: %s. Falling back to regex scanning.',
                $error->getMessage()
            ));

            return null;
        }
    }

    protected function createAstScanner(): AstViolationScanner
    {
        return new AstViolationScanner();
    }

    /**
     * @param array<int, array{type: string, pattern: string, message: string}> $patterns
     * @param array<int, string> $onlyFiles
     *
     * @return array<int, array{type: string, file: string, line: int, message: string}>
     */
    private function scanWithRegex(string $modulesRoot, array $patterns, array $onlyFiles = []): array
    {
        $violations = [];
        $restrictTo = $this->buildPathIndex($onlyFiles);

        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in($modulesRoot);

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();

            if ($restrictTo !== [] && ! isset($restrictTo[$this->normalizePath($path)])) {
                continue;
            }

            $content = $file->getContents();
            $module = $this->moduleFromPath($path, $modulesRoot);

            foreach ($patterns as $definition) {
                if (! preg_match_all($definition['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[0] as $match) {
                    [$text, $offset] = $match;

                    if ($this->shouldSkipCrossModuleImport($definition['type'], $module, $text)) {
                        continue;
                    }

                    $violations[] = [
                        'type' => $definition['type'],
                        'file' => $path,
                        'line' => $this->lineNumberForOffset($content, (int) $offset),
                        'message' => $this->messageForMatch($definition['message'], $text),
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @param mixed $configuration
     *
     * @return array<int, array{type: string, pattern: string, message: string}>
     */
    private function resolvePatterns(mixed $configuration): array
    {
        if ($configuration === true) {
            return $this->defaultPatterns();
        }

        if (! is_array($configuration)) {
            return $this->defaultPatterns();
        }

        $patterns = [];

        foreach ($configuration as $key => $value) {
            if (is_string($value)) {
                $patterns[] = [
                    'type' => is_string($key) ? $key : 'custom_pattern',
                    'pattern' => $value,
                    'message' => sprintf('Pattern matched: %s', is_string($key) ? $key : 'custom_pattern'),
                ];

                continue;
            }

            if (! is_array($value) || ! isset($value['pattern']) || ! is_string($value['pattern'])) {
                continue;
            }

            $patterns[] = [
                'type' => isset($value['type']) && is_string($value['type'])
                    ? $value['type']
                    : (is_string($key) ? $key : 'custom_pattern'),
                'pattern' => $value['pattern'],
                'message' => isset($value['message']) && is_string($value['message'])
                    ? $value['message']
                    : sprintf('Pattern matched: %s', is_string($key) ? $key : 'custom_pattern'),
            ];
        }

        return $patterns === [] ? $this->defaultPatterns() : $patterns;
    }

    /**
     * @return array<int, array{type: string, pattern: string, message: string}>
     */
    private function defaultPatterns(): array
    {
        return [
            [
                'type' => 'cross_module_import',
                'pattern' => '/^\s*use\s+App\\\\Modules\\\\[A-Za-z0-9_]+\\\\(?!Contracts\\\\)[^;]+;/m',
                'message' => 'Cross-module import detected outside Contracts.',
            ],
            [
                'type' => 'facade_bypass',
                'pattern' => '/\bAuth::[A-Za-z_][A-Za-z0-9_]*\s*\(/',
                'message' => 'Facade bypass detected: Auth facade used directly.',
            ],
            [
                'type' => 'cross_module_config',
                'pattern' => '/config\(\s*[\"\'][a-z0-9_\-]+\.[^\"\']*[\"\']\s*\)/i',
                'message' => 'Cross-module config access candidate detected.',
            ],
        ];
    }

    private function messageForMatch(string $template, string $match): string
    {
        if (str_contains($template, '{match}')) {
            return str_replace('{match}', trim($match), $template);
        }

        return $template;
    }

    private function shouldSkipCrossModuleImport(string $type, string $currentModule, string $match): bool
    {
        if ($type !== 'cross_module_import') {
            return false;
        }

        if ($currentModule === '') {
            return false;
        }

        if (! preg_match('/App\\\\Modules\\\\([A-Za-z0-9_]+)/', $match, $parts)) {
            return false;
        }

        return strcasecmp($currentModule, $parts[1]) === 0;
    }

    private function moduleFromPath(string $path, string $modulesRoot): string
    {
        $relative = ltrim(str_replace($modulesRoot, '', $path), DIRECTORY_SEPARATOR);
        $segments = explode(DIRECTORY_SEPARATOR, $relative);

        return $segments[0] ?? '';
    }

    private function lineNumberForOffset(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, max(0, $offset)), "\n") + 1;
    }

    /**
     * @param array<int, string> $paths
     *
     * @return array<string, true>
     */
    private function buildPathIndex(array $paths): array
    {
        $index = [];

        foreach ($paths as $path) {
            $index[$this->normalizePath($path)] = true;
        }

        return $index;
    }

    private function normalizePath(string $path): string
    {
        $resolved = realpath($path);

        return str_replace('\\\\', '/', $resolved !== false ? $resolved : $path);
    }

    /**
     * @return array<int, array{type: string, file: string, line: int, message: string}>
     */
    private function findMissingBindings(string $modulesRoot): array
    {
        $violations = [];

        $moduleFinder = (new Finder())
            ->directories()
            ->depth('== 0')
            ->in($modulesRoot);

        foreach ($moduleFinder as $moduleDirectory) {
            $moduleName = $moduleDirectory->getBasename();
            $contractsDir = $moduleDirectory->getPathname().'/Contracts';
            $providerPath = $moduleDirectory->getPathname().'/'.$moduleName.'ServiceProvider.php';

            if (! is_dir($contractsDir) || ! is_file($providerPath)) {
                continue;
            }

            $providerContent = (string) file_get_contents($providerPath);

            $contractFinder = (new Finder())
                ->files()
                ->name('*Interface.php')
                ->in($contractsDir);

            foreach ($contractFinder as $contractFile) {
                $contractContent = $contractFile->getContents();

                if (! preg_match('/interface\s+([A-Za-z0-9_]+Interface)\b/', $contractContent, $interfaceMatch)) {
                    continue;
                }

                $interfaceName = $interfaceMatch[1];

                if (
                    str_contains($providerContent, $interfaceName.'::class')
                    || str_contains($providerContent, '\\Contracts\\'.$interfaceName)
                ) {
                    continue;
                }

                $violations[] = [
                    'type' => 'missing_binding',
                    'file' => $providerPath,
                    'line' => 1,
                    'message' => sprintf(
                        'Missing binding for contract %s in module %s provider.',
                        $interfaceName,
                        $moduleName
                    ),
                ];
            }
        }

        return $violations;
    }
}
