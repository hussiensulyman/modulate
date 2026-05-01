<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    private Filesystem $files;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->fixturePath = base_path('tests/Fixtures/compatibility-doctor-test.md');
        $this->files->ensureDirectoryExists(base_path('tests/Fixtures'));

        $this->files->put(
            $this->fixturePath,
            <<<'MD'
# Compatibility

```yaml
# package: laravel/sanctum
# status: needs-setup
# notes: Sanctum setup required.
# action: "Add HasApiTokens to User model in Auth module"
```
MD
        );
    }

    protected function tearDown(): void
    {
        $this->files->delete($this->fixturePath);

        parent::tearDown();
    }

    public function test_doctor_outputs_table_with_colored_warning_status(): void
    {
        $this->artisan('modulate:doctor', [
            '--compatibility' => $this->fixturePath,
            '--installed' => 'laravel/sanctum',
        ])
            ->expectsOutputToContain('laravel/sanctum')
            ->expectsOutputToContain('warning')
            ->assertExitCode(0);
    }
}