<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class ExtractCommandTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->files->deleteDirectory(app_path('Modules'));

        $this->seedExtractFixtures();
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory(app_path('Modules'));

        parent::tearDown();
    }

    public function test_extract_outputs_tailored_checklist_from_module_analysis(): void
    {
        $this->artisan('modulate:extract', ['name' => 'Course'])
            ->expectsOutputToContain('Microservices Extraction Checklist - Course Module')
            ->expectsOutputToContain('CourseServiceInterface::find(int $id): array')
            ->expectsOutputToContain('UserServiceInterface (from Auth)')
            ->expectsOutputToContain('SubscriptionActivated -> topic: auth.subscriptionactivated')
            ->expectsOutputToContain('Modules/Course/Migrations/ -> <service>/database/migrations/')
            ->expectsOutputToContain('user_id -> users.id')
            ->expectsOutputToContain('Rollback steps:')
            ->assertExitCode(0);
    }

    private function seedExtractFixtures(): void
    {
        $authBase = app_path('Modules/Auth');
        $courseBase = app_path('Modules/Course');

        $this->files->ensureDirectoryExists($authBase.'/Contracts');
        $this->files->put(
            $authBase.'/Contracts/UserServiceInterface.php',
            '<?php\n\nnamespace App\\Modules\\Auth\\Contracts;\n\ninterface UserServiceInterface { public function find(int $id): array; }\n'
        );

        $this->files->ensureDirectoryExists($courseBase.'/Contracts');
        $this->files->ensureDirectoryExists($courseBase.'/Services');
        $this->files->ensureDirectoryExists($courseBase.'/Listeners');
        $this->files->ensureDirectoryExists($courseBase.'/Migrations');
        $this->files->ensureDirectoryExists($courseBase.'/Tests/Unit');
        $this->files->ensureDirectoryExists($courseBase.'/Tests/Feature');

        $this->files->put(
            $courseBase.'/CourseServiceProvider.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course;

use Illuminate\Support\ServiceProvider;

class CourseServiceProvider extends ServiceProvider
{
}
PHP
        );

        $this->files->put(
            $courseBase.'/Contracts/CourseServiceInterface.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course\Contracts;

interface CourseServiceInterface
{
    public function find(int $id): array;
    public function unlockAllForUser(int $userId): void;
}
PHP
        );

        $this->files->put(
            $courseBase.'/Services/CourseService.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course\Services;

use App\Modules\Auth\Contracts\UserServiceInterface;
use App\Modules\Auth\Events\SubscriptionActivated;

class CourseService
{
    public function __construct(private UserServiceInterface $users)
    {
    }

    public function publish(): void
    {
        event(new SubscriptionActivated());
    }
}
PHP
        );

        $this->files->put(
            $courseBase.'/Listeners/HandleSubscriptionActivated.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Course\Listeners;

use App\Modules\Auth\Events\SubscriptionActivated;

class HandleSubscriptionActivated
{
    public function handle(SubscriptionActivated $event): void
    {
    }
}
PHP
        );

        $this->files->put(
            $courseBase.'/Migrations/2026_01_01_000000_create_courses_table.php',
            <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
        });
    }
};
PHP
        );

        $this->files->put($courseBase.'/Tests/Unit/CourseUnitTest.php', "<?php\n\nclass CourseUnitTest {}\n");
        $this->files->put($courseBase.'/Tests/Feature/CourseFeatureTest.php', "<?php\n\nclass CourseFeatureTest {}\n");
    }
}