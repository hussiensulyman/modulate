<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modulate\Commands\Concerns\InteractsWithModuleStubs;

class MakeMigrationCommand extends Command
{
    use InteractsWithModuleStubs;

    protected $signature = 'modulate:make-migration
        {module : Existing module name}
        {name : Migration name, e.g. create_courses_table}';

    protected $description = 'Generate a migration file inside a module Migrations directory.';

    public function handle(): int
    {
        $module = $this->moduleName((string) $this->argument('module'));
        $name = Str::snake((string) $this->argument('name'));

        $modulePath = $this->moduleBasePath($module);
        if (! is_dir($modulePath)) {
            $this->components->error(sprintf('Module [%s] does not exist.', $module));

            return self::FAILURE;
        }

        $timestamp = date('Y_m_d_His');
        $fileName = $timestamp.'_'.$name.'.php';
        $target = $modulePath.'/Migrations/'.$fileName;

        $counter = 1;
        while (is_file($target)) {
            $fileName = $timestamp.'_'.$counter.'_'.$name.'.php';
            $target = $modulePath.'/Migrations/'.$fileName;
            $counter++;
        }

        $className = Str::studly($name);

        $this->writeFromStub(
            'make/migration.stub',
            $target,
            [
                'ModuleName' => $module,
                'ModuleNameLower' => Str::snake($module),
                'ModuleNamespace' => $this->moduleNamespace($module),
                'ClassName' => $className,
                'Timestamp' => $timestamp,
            ]
        );

        $this->components->info(sprintf('Migration [%s] generated in module [%s].', $fileName, $module));

        return self::SUCCESS;
    }
}
