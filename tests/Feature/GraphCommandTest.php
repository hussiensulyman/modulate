<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class GraphCommandTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->files->deleteDirectory(app_path('Modules'));

        $this->seedGraphFixtures();
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory(app_path('Modules'));

        parent::tearDown();
    }

    public function test_graph_ascii_output_contains_contract_and_event_dependencies(): void
    {
        $this->artisan('modulate:graph')
            ->expectsOutputToContain('Module Dependency Graph')
            ->expectsOutputToContain('Billing')
            ->expectsOutputToContain('<- Auth via UserServiceInterface')
            ->expectsOutputToContain('-> Auth SubscriptionActivated')
            ->assertExitCode(0);
    }

    public function test_graph_dot_output_contains_graphviz_syntax(): void
    {
        $this->artisan('modulate:graph', ['--format' => 'dot'])
            ->expectsOutputToContain('digraph ModulateDependencies')
            ->expectsOutputToContain('rankdir=LR;')
            ->expectsOutputToContain('node [shape=box, style=rounded];')
            ->assertExitCode(0);
    }

    public function test_graph_html_output_contains_html_document_structure(): void
    {
        $this->artisan('modulate:graph', ['--format' => 'html'])
            ->expectsOutputToContain('<!doctype html>')
            ->expectsOutputToContain('<html lang="en">')
            ->expectsOutputToContain('const graph =')
            ->assertExitCode(0);
    }

    private function seedGraphFixtures(): void
    {
        $authBase = app_path('Modules/Auth');
        $billingBase = app_path('Modules/Billing');

        $this->files->ensureDirectoryExists($authBase.'/Contracts');
        $this->files->ensureDirectoryExists($authBase.'/Events');
        $this->files->ensureDirectoryExists($authBase.'/Routes');
        $this->files->ensureDirectoryExists($authBase.'/Migrations');
        $this->files->ensureDirectoryExists($authBase.'/Tests/Feature');

        $this->files->put(
            $authBase.'/AuthServiceProvider.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
}
PHP
        );

        $this->files->put(
            $authBase.'/Contracts/UserServiceInterface.php',
            '<?php\n\nnamespace App\\Modules\\Auth\\Contracts;\n\ninterface UserServiceInterface { public function find(int $id): array; }\n'
        );
        $this->files->put(
            $authBase.'/Events/SubscriptionActivated.php',
            '<?php\n\nnamespace App\\Modules\\Auth\\Events;\n\nclass SubscriptionActivated {}\n'
        );

        $this->files->ensureDirectoryExists($billingBase.'/Services');
        $this->files->ensureDirectoryExists($billingBase.'/Listeners');
        $this->files->ensureDirectoryExists($billingBase.'/Routes');
        $this->files->ensureDirectoryExists($billingBase.'/Migrations');
        $this->files->ensureDirectoryExists($billingBase.'/Tests/Feature');

        $this->files->put(
            $billingBase.'/BillingServiceProvider.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
}
PHP
        );

        $this->files->put(
            $billingBase.'/Services/BillingService.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Auth\Contracts\UserServiceInterface;
use App\Modules\Auth\Events\SubscriptionActivated;

class BillingService
{
    public function __construct(private UserServiceInterface $users)
    {
    }

    public function completePayment(): void
    {
        event(new SubscriptionActivated());
    }
}
PHP
        );

        $this->files->put(
            $billingBase.'/Listeners/HandleSubscriptionActivated.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Auth\Events\SubscriptionActivated;

class HandleSubscriptionActivated
{
    public function handle(SubscriptionActivated $event): void
    {
    }
}
PHP
        );
    }
}