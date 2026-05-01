<?php

declare(strict_types=1);

namespace Modulate\Support;

use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class CompatibilityScanner
{
    private ?Command $command;

    public function __construct(?Command $command = null)
    {
        $this->command = $command;
    }

    /**
     * @return array<string, array{package: string, status: string, notes: string, action: string}>
     */
    public function parseCompatibilityList(string $markdown): array
    {
        $entries = [];

        foreach ($this->extractFrontmatterBlocks($markdown) as $block) {
            $parsed = $this->parseSimpleYamlBlock($block);
            if (! isset($parsed['package']) || ! is_string($parsed['package'])) {
                continue;
            }

            $package = strtolower(trim($parsed['package']));
            if ($package === '') {
                continue;
            }

            $entries[$package] = [
                'package' => $package,
                'status' => $this->normalizeStatus(isset($parsed['status']) && is_string($parsed['status'])
                    ? $parsed['status']
                    : 'unknown'),
                'notes' => isset($parsed['notes']) && is_string($parsed['notes'])
                    ? trim($parsed['notes'])
                    : 'No additional notes.',
                'action' => isset($parsed['action']) && is_string($parsed['action'])
                    ? trim($parsed['action'])
                    : '-',
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, string> $installed
     * @param array<string, array{package: string, status: string, notes: string, action: string}> $compatibility
     *
     * @return array<int, array{package: string, status: string, notes: string, action: string}>
     */
    public function checkInstalledPackages(array $installed, array $compatibility): array
    {
        $results = [];
        $installed = array_values(array_unique(array_map(static function (mixed $package): string {
            return strtolower(is_string($package) ? trim($package) : '');
        }, $installed)));

        foreach ($installed as $package) {
            if ($package === '') {
                continue;
            }

            if (isset($compatibility[$package])) {
                $entry = $compatibility[$package];
                $results[] = [
                    'package' => $package,
                    'status' => $this->normalizeStatus($entry['status']),
                    'notes' => $entry['notes'],
                    'action' => $entry['action'],
                ];

                continue;
            }

            $results[] = [
                'package' => $package,
                'status' => 'unknown',
                'notes' => 'Package is not listed in docs/compatibility.md.',
                'action' => 'Review package docs and add compatibility metadata.',
            ];
        }

        usort($results, static fn (array $left, array $right): int => strcmp($left['package'], $right['package']));

        return $results;
    }

    /**
     * @param array<int, array{package: string, status: string, notes: string, action: string}> $results
     */
    public function renderTable(array $results): void
    {
        if (! $this->command instanceof Command) {
            return;
        }

        $rows = array_map(function (array $row): array {
            return [
                $row['package'],
                $this->colorizeStatus($row['status']),
                $row['notes'],
                $row['action'],
            ];
        }, $results);

        $table = new Table($this->command->getOutput());
        $table->setHeaders(['Package', 'Status', 'Notes', 'Action']);
        $table->setRows($rows);
        $table->render();
    }

    /**
     * @return array<int, string>
     */
    private function extractFrontmatterBlocks(string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $count = count($lines);
        $blocks = [];
        $index = 0;

        while ($index < $count) {
            if (! isset($lines[$index]) || trim((string) $lines[$index]) !== '---') {
                $index++;

                continue;
            }

            $end = $index + 1;
            while ($end < $count && trim((string) ($lines[$end] ?? '')) !== '---') {
                $end++;
            }

            if ($end < $count) {
                $blockLines = array_slice($lines, $index + 1, $end - $index - 1);
                $blocks[] = implode(PHP_EOL, array_map(static fn (mixed $line): string => is_string($line) ? $line : '', $blockLines));
                $index = $end + 1;

                continue;
            }

            $index++;
        }

        return $blocks;
    }

    /**
     * @return array<string, string>
     */
    private function parseSimpleYamlBlock(string $block): array
    {
        $parsed = [];
        $lines = preg_split('/\R/', $block) ?: [];

        foreach ($lines as $line) {
            if (! is_string($line)) {
                continue;
            }

            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            if ($key === '') {
                continue;
            }

            $value = trim($value, "\"'");
            $parsed[strtolower($key)] = $value;
        }

        return $parsed;
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'ok', 'compatible', 'no-action-needed', 'no_action_needed' => 'compatible',
            'needs-setup', 'needs_setup', 'warning' => 'needs-setup',
            default => 'unknown',
        };
    }

    private function colorizeStatus(string $status): string
    {
        return match ($this->normalizeStatus($status)) {
            'compatible' => '<fg=green>✓ Compatible</>',
            'needs-setup' => '<fg=yellow>⚠ Needs Setup</>',
            default => '<fg=red>❓ Unknown</>',
        };
    }
}