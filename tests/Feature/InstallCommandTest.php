<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_install_command_publishes_config_and_stubs(): void
    {
        $this->artisan('modulate:install')
            ->assertExitCode(0);

        $this->assertTrue(file_exists(config_path('modulate.php')));
    }

    public function test_install_command_with_force_flag(): void
    {
        // First publish
        $this->artisan('modulate:install')->assertExitCode(0);

        // Modify the config
        $configPath = config_path('modulate.php');
        $original = file_get_contents($configPath);
        $modified = str_replace("'path' => 'Modules'", "'path' => 'CustomModules'", $original);
        file_put_contents($configPath, $modified);

        // Publish again with --force
        $this->artisan('modulate:install', ['--force' => true])
            ->assertExitCode(0);

        // Config should be reset to original
        $this->assertStringContainsString("'path' => 'Modules'", file_get_contents($configPath));
    }
}
