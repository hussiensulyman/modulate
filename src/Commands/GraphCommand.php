<?php

declare(strict_types=1);

namespace Modulate\Commands;

use Illuminate\Console\Command;
use Modulate\Support\ModuleAnalyzer;

class GraphCommand extends Command
{
    protected $signature = 'modulate:graph
        {--format=ascii : Output format: ascii, dot, html}';

    protected $description = 'Output a module dependency graph from contract and event cross-references.';

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        $analyzer = new ModuleAnalyzer();
        $modules = $analyzer->discoverModules();

        if ($modules === []) {
            $this->components->warn('No modules found.');

            return self::SUCCESS;
        }

        $graph = [];
        foreach ($modules as $module) {
            $graph[$module] = [
                'contracts' => $analyzer->contractDependencies($module),
                'events' => $analyzer->eventDependencies($module),
            ];
        }

        return match ($format) {
            'ascii' => $this->renderAscii($graph),
            'dot' => $this->renderDot($graph),
            'html' => $this->renderHtml($graph),
            default => $this->invalidFormat($format),
        };
    }

    /**
     * @param array<string, array{contracts: array<string, array<int, string>>, events: array{fires: array<int, array{target: string, event: string}>, listens: array<int, array{target: string, event: string}>}}> $graph
     */
    private function renderAscii(array $graph): int
    {
        $this->line('Module Dependency Graph');
        $this->line(str_repeat('=', 43));

        foreach ($graph as $module => $node) {
            $this->line($module);

            if ($node['contracts'] === []) {
                $this->line('  depends on (contracts): none');
            } else {
                $this->line('  depends on (contracts):');
                foreach ($node['contracts'] as $target => $contracts) {
                    foreach ($contracts as $contract) {
                        $this->line(sprintf('    <- %s via %s', $target, $contract));
                    }
                }
            }

            if ($node['events']['listens'] === []) {
                $this->line('  listens to (events): none');
            } else {
                $this->line('  listens to (events):');
                foreach ($node['events']['listens'] as $edge) {
                    $this->line(sprintf('    <- %s %s', $edge['target'], $edge['event']));
                }
            }

            if ($node['events']['fires'] === []) {
                $this->line('  fires (events): none');
            } else {
                $this->line('  fires (events):');
                foreach ($node['events']['fires'] as $edge) {
                    $this->line(sprintf('    -> %s %s', $edge['target'], $edge['event']));
                }
            }

            $this->newLine();
        }

        $this->line(str_repeat('=', 43));

        return self::SUCCESS;
    }

    /**
     * @param array<string, array{contracts: array<string, array<int, string>>, events: array{fires: array<int, array{target: string, event: string}>, listens: array<int, array{target: string, event: string}>}}> $graph
     */
    private function renderDot(array $graph): int
    {
        $lines = [
            'digraph ModulateDependencies {',
            '  rankdir=LR;',
            '  node [shape=box, style=rounded];',
        ];

        foreach ($graph as $module => $node) {
            $lines[] = sprintf('  "%s";', $module);

            foreach ($node['contracts'] as $target => $contracts) {
                foreach ($contracts as $contract) {
                    $lines[] = sprintf(
                        '  "%s" -> "%s" [label="contract:%s"];',
                        $module,
                        $target,
                        $contract
                    );
                }
            }

            foreach ($node['events']['listens'] as $edge) {
                $lines[] = sprintf(
                    '  "%s" -> "%s" [label="listens:%s", style=dashed];',
                    $module,
                    $edge['target'],
                    $edge['event']
                );
            }

            foreach ($node['events']['fires'] as $edge) {
                $lines[] = sprintf(
                    '  "%s" -> "%s" [label="fires:%s", color=blue];',
                    $module,
                    $edge['target'],
                    $edge['event']
                );
            }
        }

        $lines[] = '}';

        foreach ($lines as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, array{contracts: array<string, array<int, string>>, events: array{fires: array<int, array{target: string, event: string}>, listens: array<int, array{target: string, event: string}>}}> $graph
     */
    private function renderHtml(array $graph): int
    {
        $json = json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->components->error('Failed to encode graph payload.');

            return self::FAILURE;
        }

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Modulate Dependency Graph</title>
  <style>
    body { font-family: "Fira Sans", sans-serif; margin: 1.5rem; background: linear-gradient(120deg,#f9f7f3,#edf5ef); color: #16302b; }
    h1 { margin: 0 0 1rem; }
    pre { background: #ffffff; border: 1px solid #b9d3c7; border-radius: 10px; padding: 1rem; overflow: auto; }
  </style>
</head>
<body>
  <h1>Module Dependency Graph</h1>
  <pre id="graph"></pre>
  <script>
    const graph = %s;
    document.getElementById('graph').textContent = JSON.stringify(graph, null, 2);
  </script>
</body>
</html>
HTML;

        foreach (explode("\n", sprintf($html, $json)) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    private function invalidFormat(string $format): int
    {
        $this->components->error(sprintf('Invalid format [%s]. Allowed: ascii, dot, html.', $format));

        return self::FAILURE;
    }
}