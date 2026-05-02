<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class GitHubActionTest extends TestCase
{
    public function test_modulate_lint_action_defines_expected_inputs(): void
    {
        $path = dirname(__DIR__, 2).'/.github/actions/modulate-lint/action.yml';

        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertIsString($content);

        $this->assertStringContainsString("name: 'Modulate Lint'", $content);
        $this->assertStringContainsString("description: 'Run modulate:lint to detect architectural violations'", $content);
        $this->assertStringContainsString("using: 'composite'", $content);

        $this->assertStringContainsString('working-directory:', $content);
        $this->assertStringContainsString("default: './'", $content);

        $this->assertStringContainsString('config-path:', $content);
        $this->assertStringContainsString("default: 'config/modulate.php'", $content);

        $this->assertStringContainsString('fail-on-violations:', $content);
        $this->assertStringContainsString("default: 'true'", $content);
    }

    public function test_modulate_lint_action_runs_ci_command_with_error_handling(): void
    {
        $path = dirname(__DIR__, 2).'/.github/actions/modulate-lint/action.yml';
        $content = file_get_contents($path);

        $this->assertIsString($content);

        $this->assertStringContainsString('if ! cd "${{ inputs.working-directory }}"; then', $content);
        $this->assertStringContainsString('php artisan modulate:lint --ci', $content);
        $this->assertStringContainsString('if [ ! -f artisan ]; then', $content);
        $this->assertStringContainsString("if ! php artisan list --raw | grep -q '^modulate:lint$'; then", $content);
        $this->assertStringContainsString('status=$?', $content);
        $this->assertStringContainsString('if [ "$status" -ne 0 ]; then', $content);
        $this->assertStringContainsString('${{ inputs.fail-on-violations }}', $content);
        $this->assertStringContainsString("= 'true'", $content);

        $this->assertStringContainsString('MODULATE_CONFIG: ${{ inputs.config-path }}', $content);
        $this->assertStringContainsString('MODULATE_FAIL_ON_VIOLATIONS: ${{ inputs.fail-on-violations }}', $content);
    }
}