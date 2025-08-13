<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

class BenchRunAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bench:run-all {benchmark : The benchmark to run (e.g., gen1, gen2, gen3, gen4, gen5, gen6, gen7, gen8, gen9)} {--image-mode=base64 : Image mode (url or base64)} {--tolerant : Use tolerant mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all available models in parallel for a specific benchmark';

    /**
     * List of all available models to test
     */
    private array $models = [
        'openai/gpt-5-chat',
        'openai/gpt-4o-2024-11-20',
        'openai/gpt-4o-mini',
        'anthropic/claude-opus-4.1',
        'anthropic/claude-sonnet-4',
        'anthropic/claude-3.5-sonnet',
        'anthropic/claude-3.7-sonnet',
        'anthropic/claude-3.7-sonnet:beta',
        'anthropic/claude-3.7-sonnet:thinking',
        'google/gemini-2.5-flash',
        'google/gemini-2.0-flash-001',
        'google/gemini-2.5-flash-lite',
        'mistralai/mistral-small-3.2-24b-instruct',
        'mistralai/pixtral-12b',
        'mistralai/pixtral-large-2411',
        'qwen/qwen2.5-vl-32b-instruct',
        'qwen/qwen-vl-max',
        'x-ai/grok-2-vision-1212',
        'meta-llama/llama-3.2-11b-vision-instruct',
        'meta-llama/llama-3.2-90b-vision-instruct',
        'z-ai/glm-4.5v',
    ];

    /**
     * Available benchmarks
     */
    private array $availableBenchmarks = [
        'gen1' => 'Generation 1 (151 Pokémon)',
        'gen2' => 'Generation 2 (100 Pokémon)',
        'gen3' => 'Generation 3 (135 Pokémon)',
        'gen4' => 'Generation 4 (107 Pokémon)',
        'gen5' => 'Generation 5 (156 Pokémon)',
        'gen6' => 'Generation 6 (72 Pokémon)',
        'gen7' => 'Generation 7 (33 Pokémon)',
        'gen8' => 'Generation 8 (89 Pokémon)',
        'gen9' => 'Generation 9 (127 Pokémon)',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $benchmark = $this->argument('benchmark');
        $imageMode = $this->option('image-mode');
        $tolerant = $this->option('tolerant');

        // Validate benchmark
        if (!array_key_exists($benchmark, $this->availableBenchmarks)) {
            $this->error("❌ Benchmark non valido: {$benchmark}");
            $this->info("📋 Benchmark disponibili:");
            foreach ($this->availableBenchmarks as $key => $description) {
                $this->info("   - {$key}: {$description}");
            }
            return 1;
        }

        $this->info("🚀 Starting parallel benchmark run for {$benchmark}");
        $this->info("📊 Description: " . $this->availableBenchmarks[$benchmark]);
        $this->info("🤖 Models: " . count($this->models));
        $this->info("🖼️  Image mode: {$imageMode}");
        $this->info("🔧 Tolerant mode: " . ($tolerant ? 'ON' : 'OFF'));
        $this->newLine();

        // Build the command for each model
        $commands = [];

        // Paths
        $baseDir = base_path("public/benchmarks/{$benchmark}");
        $images = $baseDir . '/images.json';
        $labels = $baseDir . '/labels.txt';
        $groundTruth = $baseDir . '/ground_truth.jsonl';

        $isGen1Or2 = in_array($benchmark, ['gen1','gen2'], true);

        foreach ($this->models as $model) {
            if ($isGen1Or2) {
                // Delegate to single-run command for gen1/gen2 (backward compatibility)
                $cmd = "php artisan bench:run --bench={$benchmark} --model=\"{$model}\"";
                if ($imageMode !== 'base64') { $cmd .= " --image-mode={$imageMode}"; }
                if ($tolerant) { $cmd .= " --tolerant"; }
                $commands[] = $cmd;
                continue;
            }

            // For gen3+ run python directly and score locally
            $modelSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $model));
            $modelSlug = trim($modelSlug, '-');
            $pred = $baseDir . '/predictions_' . $modelSlug . '.json';
            $score = $baseDir . '/scores_' . $modelSlug . '.json';

            $cmd = "python -u eval/run_openrouter.py --model \"{$model}\" --images \"{$images}\" --labels \"{$labels}\" --out \"{$pred}\" --progress";
            if ($imageMode !== 'base64') { $cmd .= " --image-mode={$imageMode}"; }
            if ($tolerant) { $cmd .= " --tolerant"; }
            $cmd .= " && python eval/score.py --ground-truth \"{$groundTruth}\" --predictions \"{$pred}\" --out \"{$score}\"";

            $commands[] = $cmd;
        }

        // Join all commands with & and add wait at the end
        $fullCommand = '(' . implode(' & ', $commands) . ' & wait) | cat';

        $this->info("⚡ Executing all models in parallel...");
        $this->newLine();

        // Execute the parallel command (no timeout)
        $process = Process::timeout(0)->idleTimeout(0)->env([
            'OPENROUTER_API_KEY' => env('OPENROUTER_API_KEY'),
            'PYTHONUNBUFFERED' => '1',
        ])->run($fullCommand, function (string $type, string $output) {
            if ($type === SymfonyProcess::ERR) {
                $this->error($output);
            } else {
                $this->line($output);
            }
        });

        if (!$process->successful()) {
            $this->newLine();
            $this->error("❌ Some benchmark runs failed. Check the output above for details.");
            return 1;
        }

        // After completion, update leaderboard for gen3+ from produced score files
        if (!$isGen1Or2) {
            $count = 0;
            if (is_file($labels)) {
                $count = max(0, count(file($labels, FILE_IGNORE_NEW_LINES)));
            }
            foreach ($this->models as $model) {
                $modelSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $model));
                $modelSlug = trim($modelSlug, '-');
                $score = $baseDir . '/scores_' . $modelSlug . '.json';
                if (!is_file($score)) { continue; }
                $metrics = json_decode(@file_get_contents($score), true);
                if (!is_array($metrics)) { continue; }
                $m = $metrics['metrics'] ?? [];
                $usage = $metrics['usage'] ?? [];
                $durationMs = (int) ($metrics['duration_ms'] ?? 0);
                $this->updateLeaderboard($benchmark, $count, $model, $m, $durationMs, $usage);
            }
        }

        $this->newLine();
        $this->info("✅ All benchmark runs completed successfully!");
        return 0;
    }

    private function updateLeaderboard(string $benchSlug, int $count, string $model, array $m, int $durationMs = 0, array $usage = []): void
    {
        $path = base_path('resources/data/leaderboard.json');
        $dir = dirname($path);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $fp = @fopen($path, 'c+');
        if ($fp === false) { return; }
        if (@flock($fp, LOCK_EX)) {
            $size = filesize($path);
            rewind($fp);
            $raw = $size ? fread($fp, $size) : '[]';
            $data = json_decode($raw, true);
            if (!is_array($data)) { $data = []; }
            $now = date('Y-m-d');
            $genNum = (int) preg_replace('/[^0-9]/', '', $benchSlug);
            $name = "PokeBench v1 (Gen{$genNum} {$count})";
            $found = false;
            foreach ($data as &$row) {
                if (($row['benchmark'] ?? '') === $name && ($row['model'] ?? '') === $model) {
                    $row['metrics'] = ['top1' => round($m['top1'] ?? 0, 4), 'top5' => round($m['top5'] ?? 0, 4), 'macro_f1' => round($m['macro_f1'] ?? 0, 4)];
                    $row['date'] = $now;
                    $row['duration_ms'] = $durationMs;
                    if (!empty($usage)) { $row['usage'] = $usage; }
                    $found = true; break;
                }
            }
            if (!$found) {
                $data[] = [
                    'team' => 'PokeBenchAI',
                    'model' => $model,
                    'benchmark' => $name,
                    'task' => 'T1',
                    'metrics' => ['top1' => round($m['top1'] ?? 0, 4), 'top5' => round($m['top5'] ?? 0, 4), 'macro_f1' => round($m['macro_f1'] ?? 0, 4)],
                    'date' => $now,
                    'duration_ms' => $durationMs,
                    'usage' => $usage,
                ];
            }
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}
