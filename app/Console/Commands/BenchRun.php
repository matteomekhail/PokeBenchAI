<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;

class BenchRun extends Command
{
    protected $signature = 'bench:run {--bench=gen1} {--model=} {--image-mode=base64} {--tolerant}';
    protected $description = 'Run a benchmark (gen1..gen9) with a single model via OpenRouter and update leaderboard';

    public function handle(): int
    {
        $bench = (string) ($this->option('bench') ?? 'gen1');
        $model = (string) ($this->option('model') ?? '');
        if ($model === '') {
            $this->error('Missing --model');
            return self::FAILURE;
        }

        if (!preg_match('/^gen([1-9])$/', $bench)) {
            $this->error('Invalid --bench. Expected one of: gen1..gen9');
            return self::FAILURE;
        }
        $base = base_path("public/benchmarks/{$bench}");
        if (!is_dir($base)) {
            $this->error("Benchmark folder not found: {$base}");
            return self::FAILURE;
        }

        $images = $base.'/images.json';
        $labels = $base.'/labels.txt';
        $modelSlug = Str::of($model)->lower()->replaceMatches('/[^a-z0-9]+/i', '-')->trim('-');
        $pred = $base.'/predictions_'.$modelSlug.'.json';
        $score = $base.'/scores_'.$modelSlug.'.json';

        $env = array_merge($_ENV, [
            'OPENROUTER_API_KEY' => env('OPENROUTER_API_KEY'),
            'PYTHONUNBUFFERED' => '1',
        ]);
        $this->info("Running {$bench} on {$model}...");
        $t0 = microtime(true);
        $args = ['python', '-u', 'eval/run_openrouter.py', '--model', $model, '--images', $images, '--labels', $labels, '--out', $pred, '--progress'];
        $imageMode = (string) ($this->option('image-mode') ?? 'base64');
        if ($imageMode !== '') { array_push($args, '--image-mode', $imageMode); }
        if ($this->option('tolerant')) { $args[] = '--tolerant'; }
        $p = new Process($args, base_path(), $env, null, 3600);
        $p->setTimeout(3600);
        $p->run(function ($type, $buffer) { $this->output->write($buffer); });
        if (!$p->isSuccessful()) {
            $this->error('Run failed: '.$p->getErrorOutput());
            return self::FAILURE;
        }
        // Validate predictions
        $entries = 0;
        $nonEmpty = 0;
        if (file_exists($pred)) {
            $json = json_decode(@file_get_contents($pred), true);
            $list = is_array($json) && isset($json['entries']) && is_array($json['entries']) ? $json['entries'] : [];
            $entries = count($list);
            foreach ($list as $row) {
                if (!empty($row['probs']) && is_array($row['probs'])) { $nonEmpty++; }
            }
        }
        if ($entries === 0) {
            $this->error('No predictions were produced (entries=0). Skipping score.');
            $this->line('Hint: the provider may require an extra key or a different model id. Try e.g. "openai/gpt-4o-mini" or "google/gemini-2.0-flash-001".');
            return self::FAILURE;
        }
        if ($nonEmpty === 0) {
            $this->error('Predictions contain no probabilities for any image. Skipping score.');
            $this->line('Hint: this often means the model is text-only or ignored the image input. Try a vision model like "openai/gpt-4o-mini", "google/gemini-2.5-flash" or "google/gemini-2.0-flash-001".');
            return self::FAILURE;
        }
        $durationMs = (int) round((microtime(true) - $t0) * 1000);

        $this->info('Scoring...');
        $s = new Process(['python', 'eval/score.py', '--ground-truth', $base.'/ground_truth.jsonl', '--predictions', $pred, '--out', $score, '--duration-ms', (string)$durationMs], base_path());
        $s->run();
        if (!$s->isSuccessful()) {
            $this->error('Score failed: '.$s->getErrorOutput());
            return self::FAILURE;
        }

        $metrics = json_decode(file_get_contents($score), true);
        $m = $metrics['metrics'] ?? [];
        $usage = $metrics['usage'] ?? [];
        $count = 0;
        if (is_file($labels)) { $count = max(0, count(file($labels, FILE_IGNORE_NEW_LINES))); }
        $this->updateLeaderboard($bench, $count, $model, $m, $metrics['duration_ms']??$durationMs, $usage);
        $this->info(sprintf('Done. Top1 %.2f%% Top5 %.2f%% F1 %.2f%% Duration %dms Tokens %d/%d', ($m['top1']??0)*100, ($m['top5']??0)*100, ($m['macro_f1']??0)*100, $metrics['duration_ms']??$durationMs, $usage['prompt_tokens']??0, $usage['completion_tokens']??0));
        return self::SUCCESS;
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

