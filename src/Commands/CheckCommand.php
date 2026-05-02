<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Modulate\Support\ViolationScanner;

class CheckCommand extends Command
{
    protected $signature = 'modulate:check {--use-ast : Enable AST-based scanning when available}';

    protected $description = 'Scan modules for architecture and coupling violations.';

    public function handle(): int
    {
        if ((bool) $this->option('use-ast')) {
            config()->set('modulate.use_ast', true);
        }

        $configuration = config('modulate.check_violations', true);

        if ($configuration === false) {
            $this->components->info('Violation checks are disabled by config: modulate.check_violations=false.');

            return self::SUCCESS;
        }

        $violations = (new ViolationScanner())->scan();

        if ($violations === []) {
            $this->components->info('No violations detected.');

            return self::SUCCESS;
        }

        $rows = array_map(static function (array $violation): array {
            return [
                $violation['type'],
                str_replace(base_path().'/', '', $violation['file']),
                (string) $violation['line'],
                $violation['message'],
            ];
        }, $violations);

        $this->table(['Type', 'File', 'Line', 'Message'], $rows);
        $this->components->error(sprintf('Found %d violation(s).', count($violations)));

        return self::FAILURE;
    }
}
