<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Modulate\Commands\Concerns\InteractsWithModuleStubs;

class MakeContractCommand extends Command
{
    use InteractsWithModuleStubs;

    protected $signature = 'modulate:make-contract
        {module : Existing module name}
        {name : Contract base name}';

    protected $description = 'Generate a contract interface in an existing module.';

    public function handle(): int
    {
        $module = $this->moduleName((string) $this->argument('module'));
        $name = $this->moduleName((string) $this->argument('name'));

        $modulePath = $this->moduleBasePath($module);
        if (! is_dir($modulePath)) {
            $this->components->error(sprintf('Module [%s] does not exist.', $module));

            return self::FAILURE;
        }

        $contractClass = str_ends_with($name, 'Interface') ? $name : $name.'Interface';

        $this->writeFromStub(
            'make/contract.stub',
            $modulePath.'/Contracts/'.$contractClass.'.php',
            $this->baseReplacements($module, $contractClass)
        );

        $this->components->info(sprintf('Contract [%s] generated in module [%s].', $contractClass, $module));

        return self::SUCCESS;
    }
}
