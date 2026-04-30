<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modulate\Support\ModuleAnalyzer;

class ExtractCommand extends Command
{
    protected $signature = 'modulate:extract
        {name : Module name to analyze for extraction}';

    protected $description = 'Generate a tailored microservices extraction checklist for a module.';

    public function handle(): int
    {
        $module = Str::studly((string) $this->argument('name'));
        $analyzer = new ModuleAnalyzer();
        $modulePath = $analyzer->modulePath($module);

        if (! is_dir($modulePath)) {
            $this->components->error(sprintf('Module [%s] was not found.', $module));

            return self::FAILURE;
        }

        $contracts = $analyzer->exposedContractsWithMethods($module);
        $contractDeps = $analyzer->contractDependencies($module);
        $eventDeps = $analyzer->eventDependencies($module);
        $migrations = $analyzer->migrationFiles($module);
        $fkCandidates = $analyzer->foreignKeyCandidates($module);

        $this->line(sprintf('Microservices Extraction Checklist - %s Module', $module));
        $this->line(str_repeat('=', 60));
        $this->newLine();

        $this->line('Contracts this module exposes (need HTTP/gRPC endpoints):');
        if ($contracts === []) {
            $this->line('  [ ] None detected');
        } else {
            foreach ($contracts as $interface => $methods) {
                if ($methods === []) {
                    $this->line(sprintf('  [ ] %s (add explicit method signatures)', $interface));
                    continue;
                }

                foreach ($methods as $signature) {
                    $this->line(sprintf('  [ ] %s::%s', $interface, $signature));
                    $this->line(sprintf('      -> %s', $analyzer->contractEndpointSuggestion($module, $signature)));
                }
            }
        }

        $this->newLine();
        $this->line('Contracts this module depends on (need HTTP clients):');
        if ($contractDeps === []) {
            $this->line('  [ ] None detected');
        } else {
            foreach ($contractDeps as $target => $interfaces) {
                foreach ($interfaces as $interface) {
                    $this->line(sprintf('  [ ] %s (from %s)', $interface, $target));
                    $this->line(sprintf('      -> Replace binding with %sHttpClient', str_replace('Interface', '', $interface)));
                }
            }
        }

        $this->newLine();
        $this->line('Events this module fires (need message broker publishing):');
        if ($eventDeps['fires'] === []) {
            $this->line('  [ ] None detected');
        } else {
            foreach ($eventDeps['fires'] as $edge) {
                $this->line(sprintf('  [ ] %s -> topic: %s.%s', $edge['event'], strtolower($edge['target']), strtolower($edge['event'])));
            }
        }

        $this->newLine();
        $this->line('Events this module listens to (need broker consumers):');
        if ($eventDeps['listens'] === []) {
            $this->line('  [ ] None detected');
        } else {
            foreach ($eventDeps['listens'] as $edge) {
                $this->line(sprintf('  [ ] %s (from %s)', $edge['event'], $edge['target']));
            }
        }

        $this->newLine();
        $this->line('Migrations to move:');
        if ($migrations === []) {
            $this->line('  [ ] No module migrations detected');
        } else {
            $this->line(sprintf('  [ ] Modules/%s/Migrations/ -> <service>/database/migrations/', $module));
            foreach ($migrations as $migration) {
                $this->line(sprintf('      - %s', str_replace(base_path().'/', '', $migration)));
            }
        }

        $this->newLine();
        $this->line('Foreign keys to remove:');
        if ($fkCandidates === []) {
            $this->line('  [ ] No foreign keys detected in module migrations');
        } else {
            foreach ($fkCandidates as $candidate) {
                $this->line(sprintf('  [ ] %s (enforce at app level instead)', $candidate));
            }
        }

        $this->newLine();
        $this->line('Rollback steps:');
        $this->line('  [ ] Restore module folder in monolith');
        $this->line('  [ ] Rebind contracts back to local service implementations');
        $this->line('  [ ] Re-run module migrations on monolith database');
        $this->line('  [ ] Restore in-process event listeners');
        $this->line('  [ ] Shut down standalone service deployment');

        $this->newLine();
        $this->line('Tests to verify:');
        $testsRoot = $modulePath.'/Tests';
        foreach (['Unit', 'Feature', 'E2E'] as $suite) {
            $suitePath = $testsRoot.'/'.$suite;
            if (! is_dir($suitePath)) {
                continue;
            }

            $tests = glob($suitePath.'/*.php') ?: [];
            $this->line(sprintf('  [ ] Tests/%s/ (%d tests)', $suite, count($tests)));
        }

        $this->line(str_repeat('=', 60));

        return self::SUCCESS;
    }
}