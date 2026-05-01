<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Modulate\Support\CompatibilityScanner;

class DoctorCommand extends Command
{
    protected $signature = 'modulate:doctor
        {--compatibility= : Path to compatibility markdown file}
        {--installed= : Comma-separated installed package list override}
        {--json : Output machine-readable JSON}';

    protected $description = 'Scan installed Composer packages and report Modulate compatibility guidance.';

    public function handle(): int
    {
        $installed = $this->resolveInstalledPackages();
        $compatibilityPath = $this->resolveCompatibilityPath();

        if (! is_file($compatibilityPath)) {
            $this->components->error(sprintf('Compatibility file not found: %s', $compatibilityPath));

            return self::FAILURE;
        }

        $markdown = (string) file_get_contents($compatibilityPath);
        $scanner = new CompatibilityScanner($this);
        $compatibility = $scanner->parseCompatibilityList($markdown);
        $results = $scanner->checkInstalledPackages($installed, $compatibility);

        if ($results === []) {
            $this->components->warn('No installed packages detected to scan.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $scanner->renderTable($results);

        $warnings = count(array_filter($results, static fn (array $result): bool => $result['status'] === 'needs-setup'));
        $unknown = count(array_filter($results, static fn (array $result): bool => $result['status'] === 'unknown'));

        if ($warnings > 0 || $unknown > 0) {
            $this->components->warn(sprintf(
                'Doctor found %d warning(s) and %d unknown package(s).',
                $warnings,
                $unknown
            ));
        } else {
            $this->components->info('Doctor scan complete: all scanned packages are compatible.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveInstalledPackages(): array
    {
        $override = $this->option('installed');

        if (! is_string($override) && ! is_array($override)) {
            $rawOption = $this->input?->getParameterOption('--installed', null, true);
            if (is_string($rawOption) || is_array($rawOption)) {
                $override = $rawOption;
            }
        }

        if (is_array($override)) {
            $override = implode(',', array_filter($override, static fn (mixed $value): bool => is_string($value)));
        }

        if (is_string($override)) {
            if (trim($override) === '') {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(
                static fn (string $package): string => strtolower(trim($package)),
                explode(',', $override)
            ))));
        }

        if (class_exists(\Composer\InstalledVersions::class)) {
            /** @var array<int, string> $packages */
            $packages = \Composer\InstalledVersions::getInstalledPackages();

            return array_values(array_unique(array_map('strtolower', $packages)));
        }

        $output = shell_exec('composer show --format=json 2>/dev/null');
        if (! is_string($output) || trim($output) === '') {
            return [];
        }

        $decoded = json_decode($output, true);
        if (! is_array($decoded) || ! isset($decoded['installed']) || ! is_array($decoded['installed'])) {
            return [];
        }

        $packages = [];

        foreach ($decoded['installed'] as $item) {
            if (! is_array($item) || ! isset($item['name']) || ! is_string($item['name'])) {
                continue;
            }

            $packages[] = strtolower($item['name']);
        }

        return array_values(array_unique($packages));
    }

    private function resolveCompatibilityPath(): string
    {
        $option = $this->option('compatibility');

        if (is_string($option) && trim($option) !== '') {
            return $option;
        }

        return base_path('docs/compatibility.md');
    }
}