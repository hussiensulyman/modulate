<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Modulate\Commands\Concerns\InteractsWithModuleStubs;

class MakeEventCommand extends Command
{
    use InteractsWithModuleStubs;

    protected $signature = 'modulate:make-event
        {module : Existing module name}
        {name : Event class name}';

    protected $description = 'Generate an event and matching listener in an existing module.';

    public function handle(): int
    {
        $module = $this->moduleName((string) $this->argument('module'));
        $eventClass = $this->moduleName((string) $this->argument('name'));

        $modulePath = $this->moduleBasePath($module);
        if (! is_dir($modulePath)) {
            $this->components->error(sprintf('Module [%s] does not exist.', $module));

            return self::FAILURE;
        }

        $listenerClass = 'Handle'.$eventClass;

        $this->writeFromStub(
            'make/event.stub',
            $modulePath.'/Events/'.$eventClass.'.php',
            $this->baseReplacements($module, $eventClass)
        );
        $this->writeFromStub(
            'make/listener.stub',
            $modulePath.'/Listeners/'.$listenerClass.'.php',
            $this->baseReplacements($module, $listenerClass)
        );

        $this->components->info(sprintf('Event [%s] and listener [%s] generated.', $eventClass, $listenerClass));

        return self::SUCCESS;
    }
}
