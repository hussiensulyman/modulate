<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Modulate\Commands\Concerns\InteractsWithModuleStubs;

class MakeServiceCommand extends Command
{
    use InteractsWithModuleStubs;

    protected $signature = 'modulate:make-service
        {module : Existing module name}
        {name : Service base name}';

    protected $description = 'Generate a service class and its contract interface in an existing module.';

    public function handle(): int
    {
        $module = $this->moduleName((string) $this->argument('module'));
        $name = $this->moduleName((string) $this->argument('name'));

        $modulePath = $this->moduleBasePath($module);
        if (! is_dir($modulePath)) {
            $this->components->error(sprintf('Module [%s] does not exist.', $module));

            return self::FAILURE;
        }

        $serviceClass = $name.'Service';
        $contractClass = $serviceClass.'Interface';

        $this->writeFromStub(
            'make/service.stub',
            $modulePath.'/Services/'.$serviceClass.'.php',
            $this->baseReplacements($module, $serviceClass)
        );
        $this->writeFromStub(
            'make/contract.stub',
            $modulePath.'/Contracts/'.$contractClass.'.php',
            $this->baseReplacements($module, $contractClass)
        );

        $this->components->info(sprintf('Service [%s] generated in module [%s].', $serviceClass, $module));

        return self::SUCCESS;
    }
}
