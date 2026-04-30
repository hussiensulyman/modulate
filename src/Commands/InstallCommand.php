<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class InstallCommand extends Command
{
    protected $name = 'modulate:install';

    protected $description = 'Publish config and stubs for Modulate';

    public function handle(): int
    {
        $this->components->task('Publishing Modulate configuration and stubs', function () {
            $this->call('vendor:publish', [
                '--provider' => 'Modulate\ModulateServiceProvider',
                '--tag' => 'modulate-install',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->info('Modulate successfully installed!');
            $this->line('');
        $this->components->bulletList([
            'Config published to: <info>config/modulate.php</info>',
            'Stubs published to: <info>stubs/modulate/</info>',
            'Next step: <info>php artisan modulate:init --dry-run</info>',
        ]);

        return 0;
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite any existing files'],
        ];
    }
}
