## PokeBenchAI

Benchmark suite to evaluate multi‑modal LLMs on Pokémon recognition across generations. It runs models via OpenRouter, scores predictions locally, and updates a lightweight leaderboard used by the web UI.

IMPORTANT: Use Laravel Artisan commands to run benchmarks (php artisan bench:run-all <gen>). Do not run the Python scripts directly.

## How benchmarks are performed

### Data layout
- For each generation `gen1..gen9`, the dataset lives in `public/benchmarks/<gen>/`:
  - `labels.txt`: one class per line (canonical label names)
  - `images.json`: list of `{ image_id, url | b64 }`
  - `ground_truth.jsonl`: one JSON object per line `{ image_id, class }`

### Execution pipeline
These steps are orchestrated by Laravel Artisan commands. Do NOT call the Python scripts directly; they are invoked for you by the PHP commands.
1) Predictions are produced by a Python runner that calls OpenRouter (internal detail):
   - `eval/run_openrouter.py --model <provider/model> --images <images.json> --labels <labels.txt> --out <predictions.json>`
   - Options:
     - `--image-mode=base64|url` to control how images are sent to the provider (default: base64)
     - `--tolerant` to accept free‑text outputs and map them to labels if strict JSON is missing
2) Scores are computed locally (internal detail):
   - `eval/score.py --ground-truth <ground_truth.jsonl> --predictions <predictions.json> --out <scores.json>`
   - Metrics include Top‑1, Top‑5, Macro‑F1, plus usage and duration metadata when available
3) The Laravel command updates `resources/data/leaderboard.json`, which powers the UI pages.

## Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ and npm (only if you want to run the UI locally)
- Python 3.10+ available as `python` on your PATH
  - Install Python deps: `pip install -r eval/requirements.txt`
- OpenRouter API key

## Configure your OpenRouter API key

1) Copy the environment file and generate an app key:
```bash
cp .env.example .env
php artisan key:generate
```
2) Edit `.env` and set your key:
```env
OPENROUTER_API_KEY=your_api_key_here
```

## How to run the benchmarks

IMPORTANT: Always use the Laravel Artisan commands below to run benchmarks. Do not execute the Python scripts directly unless you are developing the runner internals.

### Quick start
```bash
composer install
npm install            # optional, only for running the UI locally
pip install -r eval/requirements.txt

php artisan bench:run-all gen6
# or, with experimental flags (still in testing):
php artisan bench:run-all gen5 --image-mode=url --tolerant
```

- `--image-mode` and `--tolerant` are still in testing and may change.
- Running a generation triggers multiple model processes in parallel; ensure you have sufficient OpenRouter credits/limits.

### Single‑model run (all generations)
```bash
php artisan bench:run --bench=gen1 --model="openai/gpt-4o-mini"
php artisan bench:run --bench=gen7 --model="google/gemini-2.5-flash" --image-mode=url --tolerant   # experimental flags
```

Notes:
- bench:run supports gen1..gen9 for a single model.
- `--image-mode` and `--tolerant` are still in testing.

### All models for a generation (parallel)
```bash
php artisan bench:run-all gen3
```
## View results (UI)

```bash
npm run dev
```
Then open the homepage. You can:
- Browse each generation’s page for model comparisons
- Open a model detail page per generation

The UI reads from `resources/data/leaderboard.json` which is updated after scoring completes.

## Notes and known limitations

- Python command name: the orchestrator invokes `python`. On macOS, ensure `python` points to Python 3 (e.g., via pyenv or a symlink), not only `python3`.
- Rate limits and cost: running all models in parallel can be intensive. Consider `--limit`/`--start` options in `eval/run_openrouter.py` if you customize the runner.
- Tolerant and URL modes: still experimental; mapping/norms for label names may evolve.
- Dataset subsets: some generations may use curated subsets to avoid ambiguous forms or duplicate sprites. You can extend any generation by editing `labels.txt`, `ground_truth.jsonl`, and `images.json` accordingly.

## Contributing

- PRs welcome for new providers/models, better label normalization, or improved visualizations.
- Please include reproducibility notes and sample outputs when adding new models.


