<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;

class LintCommand extends Command
{
    protected $signature = 'modulate:lint {--ci : CI mode output (compatibility flag)} {--use-ast : Enable AST-based scanning when available}';

    protected $description = 'Alias of modulate:check for CI pipelines.';

    public function handle(): int
    {
        return (int) $this->call('modulate:check', [
            '--ci' => (bool) $this->option('ci'),
            '--use-ast' => (bool) $this->option('use-ast'),
        ]);
    }
}
