<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modulate\Commands\Concerns\InteractsWithModuleStubs;

class MakeCommand extends Command
{
    use InteractsWithModuleStubs;

    protected $signature = 'modulate:make
        {name : Module name}
        {--minimal : Generate a bare-bones module scaffold}
        {--api-only : Generate only API controllers/routes}
        {--add= : Comma-separated optional components}
        {--skip= : Comma-separated components to skip}';

    protected $description = 'Scaffold a complete module structure with configurable presets and flags.';

    /**
     * @var array<int, string>
     */
    private const SKIP_COMPONENTS = [
        'events',
        'listeners',
        'resources',
        'requests',
        'migrations',
        'tests',
        'e2e',
    ];

    /**
     * @var array<int, string>
     */
    private const ADD_COMPONENTS = [
        'repositories',
        'actions',
        'dtos',
        'enums',
        'policies',
        'dusk',
    ];

    public function handle(): int
    {
        $moduleName = $this->moduleName((string) $this->argument('name'));
        if ($moduleName === '') {
            $this->components->error('Module name cannot be empty.');

            return self::FAILURE;
        }

        $modulePath = $this->moduleBasePath($moduleName);
        if (is_dir($modulePath)) {
            $this->components->error(sprintf('Module [%s] already exists.', $moduleName));

            return self::FAILURE;
        }

        $add = $this->csvOptionList((string) ($this->option('add') ?? ''));
        $skip = $this->csvOptionList((string) ($this->option('skip') ?? ''));
        $minimal = (bool) $this->option('minimal');
        $apiOnly = (bool) $this->option('api-only');

        foreach ($add as $item) {
            if (! in_array($item, self::ADD_COMPONENTS, true)) {
                $this->components->error(sprintf('Invalid --add value [%s].', $item));

                return self::FAILURE;
            }
        }

        foreach ($skip as $item) {
            if (! in_array($item, self::SKIP_COMPONENTS, true)) {
                $this->components->error(sprintf('Invalid --skip value [%s].', $item));

                return self::FAILURE;
            }
        }

        if ($minimal) {
            $skip = array_values(array_unique(array_merge($skip, ['events', 'listeners', 'resources'])));
        }

        $skipSet = array_fill_keys($skip, true);
        $addSet = array_fill_keys($add, true);

        $replacements = $this->baseReplacements($moduleName, $moduleName.'Service');
        $this->files()->ensureDirectoryExists($modulePath);

        $this->writeFromStub(
            'make/module-provider.stub',
            $modulePath.'/'.$moduleName.'ServiceProvider.php',
            $this->baseReplacements($moduleName, $moduleName.'ServiceProvider')
        );

        $this->writeFromStub(
            'make/service.stub',
            $modulePath.'/Services/'.$moduleName.'Service.php',
            $this->baseReplacements($moduleName, $moduleName.'Service')
        );
        $this->writeFromStub(
            'make/contract.stub',
            $modulePath.'/Contracts/'.$moduleName.'ServiceInterface.php',
            $this->baseReplacements($moduleName, $moduleName.'ServiceInterface')
        );
        $this->writeFromStub(
            'make/model.stub',
            $modulePath.'/Models/'.$moduleName.'.php',
            $this->baseReplacements($moduleName, $moduleName)
        );

        if (! $apiOnly) {
            $this->writeFromStub(
                'make/controller-web.stub',
                $modulePath.'/Controllers/Web/'.$moduleName.'WebController.php',
                $this->baseReplacements($moduleName, $moduleName.'WebController')
            );
        }

        $this->writeFromStub(
            'make/controller-api.stub',
            $modulePath.'/Controllers/Api/'.$moduleName.'ApiController.php',
            $this->baseReplacements($moduleName, $moduleName.'ApiController')
        );

        if ($minimal) {
            $this->writeFromStub(
                'make/routes-flat.stub',
                $modulePath.'/Routes/routes.php',
                $replacements
            );
        } else {
            $this->writeFromStub(
                'make/routes-api.stub',
                $modulePath.'/Routes/api.php',
                $replacements
            );

            if (! $apiOnly) {
                $this->writeFromStub(
                    'make/routes-web.stub',
                    $modulePath.'/Routes/web.php',
                    $replacements
                );
            }
        }

        if (! isset($skipSet['events'])) {
            $this->writeFromStub(
                'make/event.stub',
                $modulePath.'/Events/'.$moduleName.'Created.php',
                $this->baseReplacements($moduleName, $moduleName.'Created')
            );
        }

        if (! isset($skipSet['listeners'])) {
            $this->writeFromStub(
                'make/listener.stub',
                $modulePath.'/Listeners/Handle'.$moduleName.'Created.php',
                $this->baseReplacements($moduleName, 'Handle'.$moduleName.'Created')
            );
        }

        if (! isset($skipSet['requests'])) {
            $this->writeFromStub(
                'make/request.stub',
                $modulePath.'/Requests/'.$moduleName.'Request.php',
                $this->baseReplacements($moduleName, $moduleName.'Request')
            );
        }

        if (! isset($skipSet['resources'])) {
            $this->writeFromStub(
                'make/resource.stub',
                $modulePath.'/Resources/'.$moduleName.'Resource.php',
                $this->baseReplacements($moduleName, $moduleName.'Resource')
            );
        }

        if (! isset($skipSet['migrations'])) {
            $this->writeFromStub(
                'make/migration.stub',
                $modulePath.'/Migrations/'.$this->migrationName($moduleName),
                $this->baseReplacements($moduleName, 'Create'.Str::studly(Str::plural(Str::snake($moduleName))).'Table')
            );
        }

        if (! isset($skipSet['tests'])) {
            $this->writeFromStub(
                'make/test-unit.stub',
                $modulePath.'/Tests/Unit/'.$moduleName.'ServiceTest.php',
                $this->baseReplacements($moduleName, $moduleName.'ServiceTest')
            );
            $this->writeFromStub(
                'make/test-feature.stub',
                $modulePath.'/Tests/Feature/'.$moduleName.'FeatureTest.php',
                $this->baseReplacements($moduleName, $moduleName.'FeatureTest')
            );

            $e2eEnabled = ! isset($skipSet['e2e']) && (bool) config('modulate.e2e', true);
            if ($e2eEnabled) {
                $this->writeFromStub(
                    'make/test-e2e.stub',
                    $modulePath.'/Tests/E2E/'.$moduleName.'E2ETest.php',
                    $this->baseReplacements($moduleName, $moduleName.'E2ETest')
                );

                if (isset($addSet['dusk'])) {
                    $this->writeFromStub(
                        'make/test-dusk.stub',
                        $modulePath.'/Tests/E2E/'.$moduleName.'DuskTest.php',
                        $this->baseReplacements($moduleName, $moduleName.'DuskTest')
                    );
                }
            }
        }

        if (isset($addSet['repositories'])) {
            $this->writeFromStub(
                'make/repository-contract.stub',
                $modulePath.'/Contracts/'.$moduleName.'RepositoryInterface.php',
                $this->baseReplacements($moduleName, $moduleName.'RepositoryInterface')
            );
            $this->files()->ensureDirectoryExists($modulePath.'/Repositories');
        }

        if (isset($addSet['actions'])) {
            $this->writeFromStub(
                'make/action.stub',
                $modulePath.'/Actions/'.$moduleName.'Action.php',
                $this->baseReplacements($moduleName, $moduleName.'Action')
            );
        }

        if (isset($addSet['dtos'])) {
            $this->writeFromStub(
                'make/dto.stub',
                $modulePath.'/DTOs/'.$moduleName.'Data.php',
                $this->baseReplacements($moduleName, $moduleName.'Data')
            );
        }

        if (isset($addSet['enums'])) {
            $this->writeFromStub(
                'make/enum.stub',
                $modulePath.'/Enums/'.$moduleName.'Status.php',
                $this->baseReplacements($moduleName, $moduleName.'Status')
            );
        }

        if (isset($addSet['policies'])) {
            $this->writeFromStub(
                'make/policy.stub',
                $modulePath.'/Policies/'.$moduleName.'Policy.php',
                $this->baseReplacements($moduleName, $moduleName.'Policy')
            );
        }

        if (! isset($skipSet['tests']) && ! isset($skipSet['e2e'])) {
            $this->createRootE2EFolder();
        }

        $this->components->info(sprintf('Module [%s] scaffolded successfully.', $moduleName));

        return self::SUCCESS;
    }

    private function migrationName(string $moduleName): string
    {
        $table = Str::snake(Str::pluralStudly($moduleName));

        return date('Y_m_d_His').'_create_'.$table.'_table.php';
    }

    private function createRootE2EFolder(): void
    {
        $path = base_path('tests/E2E');
        if (is_dir($path)) {
            return;
        }

        $this->files()->ensureDirectoryExists($path);
        $this->writeFromStub(
            'make/root-e2e-readme.stub',
            $path.'/README.md',
            [
                'ModuleName' => '',
                'ModuleNameLower' => '',
                'ModuleNamespace' => '',
                'ClassName' => '',
            ]
        );
        $this->files()->put($path.'/.gitkeep', "");
    }
}
