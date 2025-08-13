<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\BenchmarkController;

/**
 * @param array<int, array<string, mixed>> $results
 * @return array<int, array{model:string, avg_top1:float|null, avg_top5:float|null, avg_macro_f1:float|null, count:int}>
 */
if (!function_exists('compute_model_averages')) {
    function compute_model_averages(array $results): array
    {
        $byModel = [];
        foreach ($results as $row) {
            $model = (string) ($row['model'] ?? '');
            if ($model === '') { continue; }
            $metrics = (array) ($row['metrics'] ?? []);
            $top1 = isset($metrics['top1']) && is_numeric($metrics['top1']) ? (float) $metrics['top1'] : null;
            $top5 = isset($metrics['top5']) && is_numeric($metrics['top5']) ? (float) $metrics['top5'] : null;
            $macro = isset($metrics['macro_f1']) && is_numeric($metrics['macro_f1']) ? (float) $metrics['macro_f1'] : null;
            if (!isset($byModel[$model])) {
                $byModel[$model] = ['top1' => [], 'top5' => [], 'macro_f1' => []];
            }
            if ($top1 !== null) { $byModel[$model]['top1'][] = $top1; }
            if ($top5 !== null) { $byModel[$model]['top5'][] = $top5; }
            if ($macro !== null) { $byModel[$model]['macro_f1'][] = $macro; }
        }

        $out = [];
        foreach ($byModel as $modelName => $vals) {
            $avg = function (array $arr): ?float {
                $n = count($arr);
                return $n > 0 ? array_sum($arr) / $n : null;
            };
            $count = max(count($vals['top1']), count($vals['top5']), count($vals['macro_f1']));
            $out[] = [
                'model' => (string) $modelName,
                'avg_top1' => $avg($vals['top1']),
                'avg_top5' => $avg($vals['top5']),
                'avg_macro_f1' => $avg($vals['macro_f1']),
                'count' => $count,
            ];
        }

        usort($out, function ($a, $b) {
            $avga = $a['avg_top1'] ?? null;
            $avgb = $b['avg_top1'] ?? null;
            if ($avga === $avgb) { return 0; }
            if ($avga === null) { return 1; }
            if ($avgb === null) { return -1; }
            return $avga < $avgb ? 1 : -1;
        });

        return array_values($out);
    }
}

Route::get('/', function () {
    /** @var array<int, array<string, mixed>> $results */
    $results = json_decode(file_get_contents(base_path('resources/data/leaderboard.json')), true);
    $slugify = fn(string $s) => trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s)), '-');

    // Calcola medie per modello (classifica media)
    $modelAverages = compute_model_averages($results);

    // Tests dal leaderboard (normalizza slug a genX se possibile)
    $testsFromLeaderboard = collect($results)
        ->groupBy('benchmark')
        ->map(function ($items, $name) use ($slugify) {
            $slug = $slugify($name);
            if (preg_match('/gen\s*([0-9]+)/i', $name, $m)) {
                $slug = 'gen'.((int)$m[1]);
            }
            return [
                'name' => $name,
                'slug' => $slug,
                'modelsCount' => $items->count(),
            ];
        })->keyBy('slug');

    // Tests from folders in public/benchmarks (gen1..gen9)
    $benchBase = public_path('benchmarks');
    $dirTests = collect();
    if (is_dir($benchBase)) {
        foreach (scandir($benchBase) as $entry) {
            if (!preg_match('/^gen([1-9])$/', $entry, $m)) { continue; }
            $labels = $benchBase . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'labels.txt';
            $count = file_exists($labels) ? max(0, count(file($labels, FILE_IGNORE_NEW_LINES))) : 0;
            $name = "PokeBench v1 (Gen{$m[1]} {$count})";
            $dirTests->put($entry, [
                'name' => $name,
                'slug' => $entry,
                'modelsCount' => collect($results)->filter(function ($r) use ($entry) {
                    return str_contains(strtolower($r['benchmark'] ?? ''), strtolower($entry));
                })->count(),
            ]);
        }
    }

    // Merge: prefer leaderboard data, fill with directories (de‑dup by slug)
    $merged = $dirTests->merge($testsFromLeaderboard)->keyBy('slug')->values()->all();

    return Inertia::render('Home', [
        'tests' => $merged,
        'modelAverages' => $modelAverages,
    ]);
});

// remove standalone results route (unused)

Route::get('/results/gen1', function () {
    return Inertia::render('Results', [
        'title' => 'Risultati Gen1 (151)',
        'groundTruthPath' => '/benchmarks/gen1/ground_truth.jsonl',
        'predictionsPath' => '/benchmarks/gen1/predictions_gemini2.json',
        'imagesPath' => '/benchmarks/gen1/images.json',
    ]);
});

Route::get('/results/gen1/mistral', function () {
    return Inertia::render('Results', [
        'title' => 'Risultati Gen1 (151) • Mistral',
        'groundTruthPath' => '/benchmarks/gen1/ground_truth.jsonl',
        'predictionsPath' => '/benchmarks/gen1/predictions_mistral.json',
        'imagesPath' => '/benchmarks/gen1/images.json',
    ]);
});

Route::get('/benchmarks/{slug}', function (string $slug) {
    $results = json_decode(file_get_contents(base_path('resources/data/leaderboard.json')), true);
    $slugify = fn(string $s) => trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s)), '-');

    $filtered = collect($results)->filter(function ($item) use ($slugify, $slug) {
        return $slugify($item['benchmark']) === $slug;
    });
    if ($filtered->isEmpty() && preg_match('/^gen[1-9]$/', $slug)) {
        // also accept slugs like gen1/gen2 matching benchmark names containing GenX
        $filtered = collect($results)->filter(function ($item) use ($slug) {
            return str_contains(strtolower($item['benchmark'] ?? ''), strtolower($slug));
        });
    }

    if ($filtered->isEmpty()) {
        // If there are no results, accept slug like genX and show an empty page
        if (preg_match('/^gen([1-9])$/', $slug, $m)) {
            $benchBase = public_path('benchmarks');
            $labels = $benchBase . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'labels.txt';
            $count = file_exists($labels) ? max(0, count(file($labels, FILE_IGNORE_NEW_LINES))) : 0;
            $title = "PokeBench v1 (Gen{$m[1]} {$count})";
            return Inertia::render('BenchmarkDetail', [
                'title' => $title,
                'slug' => $slug,
                'series' => [],
            ]);
        }
        abort(404);
    }

    $title = $filtered->first()['benchmark'];

    $series = $filtered->map(function ($item) use ($slugify, $slug) {
        $modelSlug = $slugify($item['model']);
        return [
            'model' => $item['model'],
            'modelSlug' => $modelSlug,
            'link' => "/benchmarks/{$slug}/{$modelSlug}",
            'top1' => round(($item['metrics']['top1'] ?? 0) * 100, 2),
            'duration_ms' => $item['duration_ms'] ?? null,
            'usage' => $item['usage'] ?? null,
        ];
    })->values()->all();

    return Inertia::render('BenchmarkDetail', [
        'title' => $title,
        'slug' => $slug,
        'series' => $series,
    ]);
});

// Run page removed

Route::get('/benchmarks/{slug}/{model}', function (string $slug, string $model) {
    $results = json_decode(file_get_contents(base_path('resources/data/leaderboard.json')), true);
    $slugify = fn(string $s) => trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s)), '-');

    $filtered = collect($results)->filter(fn($i) => $slugify($i['benchmark']) === $slug);
    if ($filtered->isEmpty() && preg_match('/^gen[1-9]$/', $slug)) {
        $filtered = collect($results)->filter(function ($i) use ($slug) {
            return str_contains(strtolower($i['benchmark'] ?? ''), strtolower($slug));
        });
    }
    if ($filtered->isEmpty()) { abort(404); }

    $item = $filtered->first(fn($i) => $slugify($i['model']) === $model);
    if (!$item) { abort(404); }

    $title = $item['benchmark'].' • '.$item['model'];
    $benchLower = strtolower($item['benchmark']);
    // Folder detection: preferisci dallo slug se è del tipo genX, altrimenti dal titolo
    if (preg_match('/^gen([1-9])$/', $slug)) {
        $folder = $slug;
    } elseif (preg_match('/gen([1-9])/', $benchLower, $mm)) {
        $folder = 'gen'.$mm[1];
    } else {
        $folder = 'gen1';
    }
    $groundTruth = "/benchmarks/{$folder}/ground_truth.jsonl";
    $images = "/benchmarks/{$folder}/images.json";

    // Try generic mapping: predictions_<modelSlug>.json inside bench folder
    $predictions = "/benchmarks/{$folder}/predictions_{$model}.json";
    if (!file_exists(public_path($predictions))) {
        // Fallback to known names for backwards compatibility
        $modelLower = strtolower($item['model']);
        if (str_contains($modelLower, 'gemini-2.0-flash-001')) {
            $predictions = "/benchmarks/{$folder}/predictions_gemini2.json";
        } elseif (str_contains($modelLower, 'mistral')) {
            $predictions = "/benchmarks/{$folder}/predictions_mistral.json";
        } elseif (str_contains($modelLower, 'gpt-4o-mini')) {
            $predictions = "/benchmarks/{$folder}/predictions_gpt4omini.json";
        } elseif (str_contains($modelLower, 'claude')) {
            $predictions = "/benchmarks/{$folder}/predictions_claude.json";
        }
        if (!file_exists(public_path($predictions))) {
            abort(404, 'Predictions not available for this model');
        }
    }

    return Inertia::render('Results', [
        'title' => $title,
        'groundTruthPath' => $groundTruth,
        'predictionsPath' => $predictions,
        'imagesPath' => $images,
    ]);
});
