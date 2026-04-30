<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modulate\ModulateServiceProvider;
use Tests\TestCase;

class ConfigPublishTest extends TestCase
{
    public function test_config_and_stubs_publish_paths_are_registered(): void
    {
        $configSource = dirname(__DIR__, 2).'/config/modulate.php';
        $stubsSource = dirname(__DIR__, 2).'/stubs/modulate';

        $configPublishes = ModulateServiceProvider::pathsToPublish(null, 'modulate-config');
        $stubsPublishes = ModulateServiceProvider::pathsToPublish(null, 'modulate-stubs');
        $installPublishes = ModulateServiceProvider::pathsToPublish(null, 'modulate-install');

        $this->assertArrayHasKey($configSource, $configPublishes);
        $this->assertSame(config_path('modulate.php'), $configPublishes[$configSource]);

        $this->assertArrayHasKey($stubsSource, $stubsPublishes);
        $this->assertSame(base_path('stubs/modulate'), $stubsPublishes[$stubsSource]);

        $this->assertArrayHasKey($configSource, $installPublishes);
        $this->assertArrayHasKey($stubsSource, $installPublishes);
    }
}