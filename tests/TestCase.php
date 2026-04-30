<?php

declare(strict_types=1);

namespace Tests;

use Modulate\ModulateServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected string $tempPath;

    protected function getPackageProviders($app): array
    {
        return [
            ModulateServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('modulate', require dirname(__DIR__).'/config/modulate.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir().'/modulate-tests-'.bin2hex(random_bytes(6));

        @mkdir($this->tempPath.'/stubs', 0777, true);
        @mkdir($this->tempPath.'/publish', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempPath);

        parent::tearDown();
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    public function artisan($command, $parameters = [])
    {
        return parent::artisan($command, $parameters);
    }

    public function be(\Illuminate\Contracts\Auth\Authenticatable $user, $driver = null)
    {
        return parent::be($user, $driver);
    }

    public function call($method, $uri, $parameters = [], $files = [], $server = [], $content = null, $changeHistory = true)
    {
        return parent::call($method, $uri, $parameters, $files, $server, $content, $changeHistory);
    }

    public function seed($class = 'DatabaseSeeder')
    {
        return parent::seed($class);
    }
}