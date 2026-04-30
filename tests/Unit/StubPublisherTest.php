<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modulate\Support\StubPublisher;
use Tests\TestCase;

class StubPublisherTest extends TestCase
{
    public function test_it_replaces_all_supported_placeholders(): void
    {
        $content = '{{ ModuleName }}|{{ ModuleNameLower }}|{{ ModuleNamespace }}|{{ ClassName }}|{{ Timestamp }}';

        $result = StubPublisher::replacePlaceholders($content, [
            'ModuleName' => 'Course',
            'ModuleNameLower' => 'course',
            'ModuleNamespace' => 'App\\Modules\\Course',
            'ClassName' => 'CourseService',
            'Timestamp' => '2026_04_30_120000',
        ]);

        $this->assertSame(
            'Course|course|App\\Modules\\Course|CourseService|2026_04_30_120000',
            $result
        );
    }

    public function test_it_publishes_directory_and_replaces_placeholders_in_file_contents(): void
    {
        $source = $this->tempPath.'/stubs';
        $destination = $this->tempPath.'/publish';

        @mkdir($source.'/nested', 0777, true);

        file_put_contents(
            $source.'/nested/example.stub',
            "Class: {{ ClassName }}\nModule: {{ ModuleName }}\nNamespace: {{ ModuleNamespace }}"
        );

        $publisher = new StubPublisher();
        $publisher->publishDirectory($source, $destination, [
            'ModuleName' => 'Billing',
            'ModuleNameLower' => 'billing',
            'ModuleNamespace' => 'App\\Modules\\Billing',
            'ClassName' => 'InvoiceService',
            'Timestamp' => '2026_04_30_130000',
        ]);

        $publishedFile = $destination.'/nested/example.stub';

        $this->assertFileExists($publishedFile);
        $this->assertStringContainsString('Class: InvoiceService', (string) file_get_contents($publishedFile));
        $this->assertStringContainsString('Module: Billing', (string) file_get_contents($publishedFile));
        $this->assertStringContainsString('Namespace: App\\Modules\\Billing', (string) file_get_contents($publishedFile));
    }
}