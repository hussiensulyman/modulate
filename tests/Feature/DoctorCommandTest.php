<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
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
            "# Compatibility\n\n---\npackage: laravel/sanctum\nstatus: needs-setup\nnotes: Sanctum setup required.\naction: \"Add HasApiTokens to User model in Auth module\"\n---\n"
        );
    }

    protected function tearDown(): void
    {
        $this->files->delete($this->fixturePath);
        $this->files->deleteDirectory(base_path('tests/Fixtures'));

        parent::tearDown();
    }

    public function test_doctor_outputs_table_with_colored_warning_status(): void
    {
        $output = new BufferedOutput(decorated: true);

        $exitCode = Artisan::call('modulate:doctor', [
            '--compatibility' => $this->fixturePath,
            '--installed' => 'laravel/sanctum',
            '--ansi' => true,
        ], $output);

        $buffered = $output->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('laravel/sanctum', $buffered);
        $this->assertStringContainsString("\e[33m", $buffered);
        $this->assertStringContainsString('⚠ Needs Setup', $buffered);
    }

    public function test_doctor_outputs_json_when_json_flag_is_set(): void
    {
        $exitCode = Artisan::call('modulate:doctor', [
            '--compatibility' => $this->fixturePath,
            '--installed' => 'laravel/sanctum',
            '--json' => true,
        ]);

        $buffered = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"package"', $buffered);
        $this->assertStringContainsString('laravel/sanctum', $buffered);
        $this->assertStringContainsString('"status"', $buffered);
        $this->assertStringContainsString('needs-setup', $buffered);
    }

    public function test_doctor_outputs_unknown_status_for_unlisted_package(): void
    {
        $exitCode = Artisan::call('modulate:doctor', [
            '--compatibility' => $this->fixturePath,
            '--installed' => 'vendor/unlisted-package',
        ]);

        $buffered = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('vendor/unlisted-package', $buffered);
        $this->assertStringContainsString('Unknown', $buffered);
    }

    public function test_doctor_warns_when_no_packages_detected(): void
    {
        $exitCode = Artisan::call('modulate:doctor', [
            '--compatibility' => $this->fixturePath,
            '--installed' => '',
        ]);

        $buffered = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No installed packages detected to scan.', $buffered);
    }

    public function test_doctor_fails_when_compatibility_file_not_found(): void
    {
        $this->artisan('modulate:doctor', [
            '--compatibility' => '/non/existent/path/compatibility.md',
        ])
            ->assertExitCode(1);
    }
}
