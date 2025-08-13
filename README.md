<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## PokeBenchAI (MVP)

Leaderboard statica con Laravel + Inertia (React) e script Python di scoring per T1 (classificazione specie Pokémon).

### Formato Ground Truth
- JSONL (una riga per immagine):
```
{"image_id": "0001.png", "class": "bulbasaur"}
```

### Formato Predictions
- JSON con lista `entries`:
```
{
  "entries": [
    {"image_id": "0001.png", "probs": {"bulbasaur": 0.9, "ivysaur": 0.08}}
  ]
}
```
- Alternativa JSONL con colonne `image_id`, `probs` o `label`.

### Scoring (Top-1/Top-5, Macro-F1)
Prerequisiti:
```
python3 -m venv .venv && source .venv/bin/activate
pip install -r eval/requirements.txt
```
Esecuzione:
```
python eval/score.py --ground-truth public/examples/ground_truth.jsonl \
  --predictions public/examples/predictions.json --out scores.json
```
Output esempio:
```
{"task": "T1", "metrics": {"top1": 0.6667, "top5": 1.0, "macro_f1": 0.6667}}
```

### Leaderboard statica
- I dati visualizzati sono in `resources/data/leaderboard.json`.
- Modifica il file e rigenera asset (`npm run build`) per aggiornare la pagina.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
