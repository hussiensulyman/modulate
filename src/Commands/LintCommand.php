<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;

class LintCommand extends Command
{
    protected $signature = 'modulate:lint';

    protected $description = 'Alias of modulate:check for CI pipelines.';

    public function handle(): int
    {
        return (int) $this->call('modulate:check');
    }
}
