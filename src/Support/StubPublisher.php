<?php

declare(strict_types=1);

namespace Modulate\Support;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class StubPublisher
{
    public function __construct(private readonly Filesystem $files = new Filesystem())
    {
    }

    /**
     * Publish package stubs into the application's stubs/modulate directory.
     *
     * @param array<string, string> $replacements
     */
    public function publishPackageStubs(array $replacements = [], ?string $destination = null): void
    {
        $source = dirname(__DIR__, 2).'/stubs/modulate';
        $target = $destination ?? base_path('stubs/modulate');

        $this->publishDirectory($source, $target, $replacements);
    }

    /**
     * Publish a source stub directory to a destination directory with replacement tokens.
     *
     * @param array<string, string> $replacements
     */
    public function publishDirectory(string $source, string $destination, array $replacements = []): void
    {
        if (! is_dir($source)) {
            return;
        }

        $this->files->ensureDirectoryExists($destination);

        $finder = (new Finder())
            ->files()
            ->in($source);

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $targetPath = rtrim($destination, '/').'/'.$relativePath;

            $this->files->ensureDirectoryExists(dirname($targetPath));

            $content = $file->getContents();
            $this->files->put($targetPath, self::replacePlaceholders($content, $replacements));
        }
    }

    /**
     * Replace known placeholders in a stub content string.
     *
     * @param array<string, string> $replacements
     */
    public static function replacePlaceholders(string $content, array $replacements): string
    {
        $map = [
            '{{ ModuleName }}' => $replacements['ModuleName'] ?? '',
            '{{ ModuleNameLower }}' => $replacements['ModuleNameLower'] ?? '',
            '{{ ModuleNamespace }}' => $replacements['ModuleNamespace'] ?? '',
            '{{ ClassName }}' => $replacements['ClassName'] ?? '',
            '{{ Timestamp }}' => $replacements['Timestamp'] ?? date('Y_m_d_His'),
        ];

        return strtr($content, $map);
    }
}