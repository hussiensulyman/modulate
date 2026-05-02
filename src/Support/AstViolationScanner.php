<?php

declare(strict_types=1);

namespace Modulate\Support;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

class AstViolationScanner
{
    /**
     * @var array<int, array{type: string, file: string, line: int, message: string}>
     */
    private array $violations = [];

    /**
     * @var array<string, true>
     */
    private array $violationIndex = [];

    /**
     * @var array<int, string>
     */
    private array $failedFiles = [];

    /**
     * @var array<int, string>
     */
    private array $warnings = [];

    /**
     * @return array<int, array{type: string, file: string, line: int, message: string}>
     */
    public function scan(string $modulesRoot): array
    {
        $this->violations = [];
        $this->violationIndex = [];
        $this->failedFiles = [];
        $this->warnings = [];

        if (! class_exists(ParserFactory::class)) {
            throw new \RuntimeException('nikic/php-parser is not installed.');
        }

        if (! is_dir($modulesRoot)) {
            return [];
        }

        $moduleNames = $this->discoverModuleNames($modulesRoot);
        $parser = (new ParserFactory())->createForHostVersion();
        $finder = new NodeFinder();

        $files = (new Finder())
            ->files()
            ->name('*.php')
            ->in($modulesRoot);

        foreach ($files as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $content = $file->getContents();
            $module = $this->moduleFromPath($path, $modulesRoot);

            try {
                $ast = $parser->parse($content);
            } catch (Error $error) {
                $this->recordFailure($path, sprintf('Parse error on line %d: %s', $error->getStartLine(), $error->getMessage()));

                continue;
            } catch (\Throwable $error) {
                $this->recordFailure($path, sprintf('Parse failed: %s', $error->getMessage()));

                continue;
            }

            if (! is_array($ast)) {
                continue;
            }

            try {
                $traverser = new NodeTraverser(new NameResolver());
                $resolvedAst = $traverser->traverse($ast);
            } catch (\Throwable $error) {
                $this->recordFailure($path, sprintf('Name resolution failed: %s', $error->getMessage()));

                continue;
            }

            $this->scanUseStatements($finder, $resolvedAst, $path, $module);
            $this->scanClassReferences($finder, $resolvedAst, $path, $module);
            $this->scanStaticCalls($finder, $resolvedAst, $path, $module, $moduleNames);
            $this->scanFunctionCalls($finder, $resolvedAst, $path);
        }

        return array_values($this->violations);
    }

    /**
     * @return array<int, string>
     */
    public function failedFiles(): array
    {
        return array_values(array_unique($this->failedFiles));
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return array_values(array_unique($this->warnings));
    }

    /**
     * @param array<int, Node> $ast
     */
    private function scanUseStatements(NodeFinder $finder, array $ast, string $path, string $currentModule): void
    {
        foreach ($finder->findInstanceOf($ast, Stmt\Use_::class) as $useStatement) {
            foreach ($useStatement->uses as $use) {
                $useType = $use->type | $useStatement->type;

                if ($useType !== Stmt\Use_::TYPE_NORMAL) {
                    continue;
                }

                $this->registerCrossModuleReference(
                    $this->normalizeName((string) $use->name),
                    $path,
                    $use->getStartLine() ?: $useStatement->getStartLine(),
                    $currentModule
                );
            }
        }

        foreach ($finder->findInstanceOf($ast, Stmt\GroupUse::class) as $groupUse) {
            foreach ($groupUse->uses as $use) {
                $useType = $use->type | $groupUse->type;

                if ($useType !== Stmt\Use_::TYPE_NORMAL) {
                    continue;
                }

                $name = Name::concat($groupUse->prefix, $use->name);

                if (! $name instanceof Name) {
                    continue;
                }

                $this->registerCrossModuleReference(
                    $this->normalizeName((string) $name),
                    $path,
                    $use->getStartLine() ?: $groupUse->getStartLine(),
                    $currentModule
                );
            }
        }
    }

    /**
     * @param array<int, Node> $ast
     */
    private function scanClassReferences(NodeFinder $finder, array $ast, string $path, string $currentModule): void
    {
        foreach ($finder->findInstanceOf($ast, Expr\New_::class) as $newExpression) {
            $this->registerClassReference($newExpression->class, $path, $newExpression->getStartLine(), $currentModule);
        }

        foreach ($finder->findInstanceOf($ast, Expr\Instanceof_::class) as $instanceOfExpression) {
            $this->registerClassReference($instanceOfExpression->class, $path, $instanceOfExpression->getStartLine(), $currentModule);
        }

        foreach ($finder->findInstanceOf($ast, Expr\ClassConstFetch::class) as $classConstFetch) {
            if ($classConstFetch->class instanceof Name && ! $classConstFetch->class->isSpecialClassName()) {
                $this->registerClassReference($classConstFetch->class, $path, $classConstFetch->getStartLine(), $currentModule);
            }
        }
    }

    /**
     * @param array<int, Node> $ast
     * @param array<int, string> $moduleNames
     */
    private function scanStaticCalls(NodeFinder $finder, array $ast, string $path, string $currentModule, array $moduleNames): void
    {
        foreach ($finder->findInstanceOf($ast, Expr\StaticCall::class) as $staticCall) {
            if (! $staticCall->class instanceof Name || $staticCall->class->isSpecialClassName()) {
                continue;
            }

            $className = $this->normalizeName((string) $staticCall->class);
            $line = $staticCall->getStartLine();

            $this->registerCrossModuleReference($className, $path, $line, $currentModule);

            if ($this->isAuthFacade($className)) {
                $this->addViolation(
                    'facade_bypass',
                    $path,
                    $line,
                    'Facade bypass detected: Auth facade used directly.'
                );
            }

            if ($this->isCrossModuleModelReference($className, $currentModule) && $this->isDbLikeMethod($staticCall->name)) {
                $this->addViolation(
                    'cross_module_db_query',
                    $path,
                    $line,
                    sprintf('Cross-module DB query detected via model static call: %s.', $className)
                );
            }

            if ($this->isDbFacade($className) && $this->isTableMethod($staticCall->name)) {
                $table = $this->extractTableName($staticCall);
                if ($table === null) {
                    continue;
                }

                $targetModule = $this->moduleFromTableName($table, $moduleNames);
                if ($targetModule === null || strcasecmp($targetModule, $currentModule) === 0) {
                    continue;
                }

                $this->addViolation(
                    'cross_module_db_query',
                    $path,
                    $line,
                    sprintf('Cross-module DB query detected against table "%s".', $table)
                );
            }
        }
    }

    /**
     * @param array<int, Node> $ast
     */
    private function scanFunctionCalls(NodeFinder $finder, array $ast, string $path): void
    {
        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $functionCall) {
            if (! $functionCall->name instanceof Name) {
                continue;
            }

            if (strtolower((string) $functionCall->name) !== 'config') {
                continue;
            }

            $firstArgument = $functionCall->args[0]->value ?? null;
            if (! $firstArgument instanceof String_) {
                continue;
            }

            if (! str_contains($firstArgument->value, '.')) {
                continue;
            }

            $this->addViolation(
                'cross_module_config',
                $path,
                $functionCall->getStartLine(),
                'Cross-module config access candidate detected.'
            );
        }
    }

    private function registerClassReference(mixed $classNode, string $path, int $line, string $currentModule): void
    {
        if (! $classNode instanceof Name || $classNode->isSpecialClassName()) {
            return;
        }

        $this->registerCrossModuleReference(
            $this->normalizeName((string) $classNode),
            $path,
            $line,
            $currentModule
        );
    }

    private function registerCrossModuleReference(string $className, string $file, int $line, string $currentModule): void
    {
        $reference = $this->crossModuleReference($className, $currentModule);
        if ($reference === null) {
            return;
        }

        if ($reference['kind'] === 'model') {
            $this->addViolation(
                'direct_model_import',
                $file,
                $line,
                sprintf('Direct model import detected from module %s: %s.', $reference['module'], $className)
            );

            return;
        }

        if ($reference['kind'] === 'service') {
            $this->addViolation(
                'direct_service_import',
                $file,
                $line,
                sprintf('Direct service import detected from module %s: %s.', $reference['module'], $className)
            );

            return;
        }

        $this->addViolation(
            'cross_module_import',
            $file,
            $line,
            'Cross-module import detected outside Contracts.'
        );
    }

    /**
     * @return array{module: string, kind: string}|null
     */
    private function crossModuleReference(string $className, string $currentModule): ?array
    {
        $normalized = $this->normalizeName($className);
        if (! str_starts_with($normalized, 'App\\Modules\\')) {
            return null;
        }

        $segments = explode('\\', $normalized);
        if (count($segments) < 4) {
            return null;
        }

        $targetModule = $segments[2];
        if ($targetModule === '' || strcasecmp($targetModule, $currentModule) === 0) {
            return null;
        }

        if (($segments[3] ?? '') === 'Contracts') {
            return null;
        }

        $kind = 'other';
        if (($segments[3] ?? '') === 'Models') {
            $kind = 'model';
        } elseif (($segments[3] ?? '') === 'Services') {
            $kind = 'service';
        }

        return [
            'module' => $targetModule,
            'kind' => $kind,
        ];
    }

    private function addViolation(string $type, string $file, int $line, string $message): void
    {
        $line = max(1, $line);
        $key = sprintf('%s|%s|%d|%s', $type, $file, $line, $message);

        if (isset($this->violationIndex[$key])) {
            return;
        }

        $this->violationIndex[$key] = true;
        $this->violations[] = [
            'type' => $type,
            'file' => $file,
            'line' => $line,
            'message' => $message,
        ];
    }

    private function recordFailure(string $path, string $reason): void
    {
        $this->failedFiles[] = $path;
        $this->warnings[] = sprintf('AST parsing failed for %s: %s', $path, $reason);
    }

    /**
     * @return array<int, string>
     */
    private function discoverModuleNames(string $modulesRoot): array
    {
        $finder = (new Finder())
            ->directories()
            ->depth('== 0')
            ->in($modulesRoot);

        $modules = [];
        foreach ($finder as $directory) {
            $modules[] = $directory->getBasename();
        }

        return $modules;
    }

    private function moduleFromPath(string $path, string $modulesRoot): string
    {
        $relative = ltrim(str_replace($modulesRoot, '', $path), DIRECTORY_SEPARATOR);
        $segments = explode(DIRECTORY_SEPARATOR, $relative);

        return $segments[0] ?? '';
    }

    private function normalizeName(string $name): string
    {
        return ltrim($name, '\\');
    }

    private function isAuthFacade(string $className): bool
    {
        return in_array($className, ['Auth', 'Illuminate\\Support\\Facades\\Auth'], true);
    }

    private function isDbFacade(string $className): bool
    {
        return in_array($className, ['DB', 'Illuminate\\Support\\Facades\\DB'], true);
    }

    private function isCrossModuleModelReference(string $className, string $currentModule): bool
    {
        $reference = $this->crossModuleReference($className, $currentModule);

        return $reference !== null && $reference['kind'] === 'model';
    }

    private function isDbLikeMethod(mixed $name): bool
    {
        if (! $name instanceof Identifier) {
            return true;
        }

        return in_array(strtolower($name->toString()), [
            'query',
            'find',
            'findorfail',
            'first',
            'firstorfail',
            'where',
            'create',
            'update',
            'delete',
            'sum',
            'count',
            'paginate',
        ], true);
    }

    private function isTableMethod(mixed $name): bool
    {
        return $name instanceof Identifier && strtolower($name->toString()) === 'table';
    }

    private function extractTableName(Expr\StaticCall $staticCall): ?string
    {
        $firstArgument = $staticCall->args[0]->value ?? null;

        if (! $firstArgument instanceof String_) {
            return null;
        }

        $table = strtolower(trim($firstArgument->value));
        if ($table === '') {
            return null;
        }

        $parts = explode('.', $table);

        return end($parts) ?: null;
    }

    /**
     * @param array<int, string> $moduleNames
     */
    private function moduleFromTableName(string $table, array $moduleNames): ?string
    {
        foreach ($moduleNames as $moduleName) {
            $snake = $this->snakeCase($moduleName);

            if (
                $table === $snake
                || $table === $snake.'s'
                || str_starts_with($table, $snake.'_')
                || str_starts_with($table, $snake.'s_')
            ) {
                return $moduleName;
            }
        }

        return null;
    }

    private function snakeCase(string $value): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

        return strtolower($snake ?? $value);
    }
}
