<?php

declare(strict_types=1);

namespace Modulate\Support;

use Illuminate\Console\Command;

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
        preg_match_all('/```yaml\s*(.*?)```/si', $markdown, $matches);

        $entries = [];

        foreach ($matches[1] ?? [] as $block) {
            if (! is_string($block)) {
                continue;
            }

            $parsed = $this->parseCommentedYamlBlock($block);
            if (! isset($parsed['package']) || ! is_string($parsed['package'])) {
                continue;
            }

            $package = strtolower(trim($parsed['package']));
            if ($package === '') {
                continue;
            }

            $entries[$package] = [
                'package' => $package,
                'status' => isset($parsed['status']) && is_string($parsed['status'])
                    ? strtolower(trim($parsed['status']))
                    : 'compatible',
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
                    'status' => $this->mapStatusToOutput($entry['status']),
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

        $this->command->table(['Package', 'Status', 'Notes', 'Action'], $rows);
    }

    /**
     * @return array<string, string>
     */
    private function parseCommentedYamlBlock(string $block): array
    {
        $parsed = [];
        $lines = preg_split('/\R/', $block) ?: [];

        foreach ($lines as $line) {
            if (! is_string($line)) {
                continue;
            }

            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '#')) {
                continue;
            }

            $withoutComment = trim(substr($line, 1));
            if ($withoutComment === '' || ! str_contains($withoutComment, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $withoutComment, 2));
            if ($key === '') {
                continue;
            }

            $value = trim($value, "\"'");
            $parsed[strtolower($key)] = $value;
        }

        return $parsed;
    }

    private function mapStatusToOutput(string $status): string
    {
        return match (strtolower(trim($status))) {
            'ok', 'compatible', 'no-action-needed', 'no_action_needed' => 'ok',
            'needs-setup', 'needs_setup', 'warning' => 'warning',
            default => 'unknown',
        };
    }

    private function colorizeStatus(string $status): string
    {
        return match ($status) {
            'ok' => "\033[32mok\033[39m",
            'warning' => "\033[33mwarning\033[39m",
            default => "\033[31munknown\033[39m",
        };
    }
}