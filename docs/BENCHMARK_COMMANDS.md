# Comandi Benchmark PokeBenchAI

## Comando Artisan: `bench:run-all`

Lancia tutti i 21 modelli disponibili in parallelo per un benchmark specifico.

### Sintassi
```bash
php artisan bench:run-all <benchmark> [opzioni]
```

### Parametri
- `benchmark`: Il benchmark da eseguire (es. `gen1`, `gen2`)

### Opzioni
- `--image-mode`: Modalità immagine (`url` o `base64`, default: `base64`)
- `--tolerant`: Abilita modalità tollerante per il parsing delle risposte

### Esempi
```bash
# Lancia tutti i modelli per Gen1
php artisan bench:run-all gen1

# Lancia tutti i modelli per Gen2 con modalità URL
php artisan bench:run-all gen2 --image-mode=url

# Lancia tutti i modelli per Gen1 con modalità tollerante
php artisan bench:run-all gen1 --tolerant

# Lancia tutti i modelli per Gen2 con entrambe le opzioni
php artisan bench:run-all gen2 --image-mode=url --tolerant
```

## Script Shell: `run_all_models.sh`

Alternativa allo script Artisan, più flessibile per l'uso diretto.

### Sintassi
```bash
./scripts/run_all_models.sh <benchmark> [image_mode] [tolerant]
```

### Parametri
- `benchmark`: Il benchmark da eseguire (es. `gen1`, `gen2`)
- `image_mode`: Modalità immagine (`url` o `base64`, default: `base64`)
- `tolerant`: Modalità tollerante (`true` o `false`, default: `false`)

### Esempi
```bash
# Lancia tutti i modelli per Gen1
./scripts/run_all_models.sh gen1

# Lancia tutti i modelli per Gen2 con modalità URL
./scripts/run_all_models.sh gen2 url

# Lancia tutti i modelli per Gen1 con modalità tollerante
./scripts/run_all_models.sh gen1 base64 true

# Lancia tutti i modelli per Gen2 con modalità URL e tollerante
./scripts/run_all_models.sh gen2 url true
```

## Modelli Disponibili

I seguenti 21 modelli vengono lanciati automaticamente:

### OpenAI
- `openai/gpt-5-chat`
- `openai/gpt-4o-2024-11-20`
- `openai/gpt-4o-mini`

### Anthropic
- `anthropic/claude-opus-4.1`
- `anthropic/claude-sonnet-4`
- `anthropic/claude-3.5-sonnet`
- `anthropic/claude-3.7-sonnet`
- `anthropic/claude-3.7-sonnet:beta`
- `anthropic/claude-3.7-sonnet:thinking`

### Google
- `google/gemini-2.5-flash`
- `google/gemini-2.0-flash-001`
- `google/gemini-2.5-flash-lite`

### Mistral AI
- `mistralai/mistral-small-3.2-24b-instruct`
- `mistralai/pixtral-12b`
- `mistralai/pixtral-large-2411`

### Altri
- `qwen/qwen2.5-vl-32b-instruct`
- `qwen/qwen-vl-max`
- `x-ai/grok-2-vision-1212`
- `meta-llama/llama-3.2-11b-vision-instruct`
- `meta-llama/llama-3.2-90b-vision-instruct`
- `z-ai/glm-4.5v`

## Vantaggi

1. **Efficienza**: Tutti i modelli vengono lanciati in parallelo
2. **Semplicità**: Un solo comando per lanciare tutto
3. **Flessibilità**: Opzioni per personalizzare il comportamento
4. **Monitoraggio**: Output in tempo reale di tutti i processi
5. **Gestione errori**: Gestione automatica dei processi in background

## Note

- Assicurati di avere sufficienti crediti OpenRouter per tutti i modelli
- Il comando attiva automaticamente l'ambiente virtuale Python
- L'API key viene caricata automaticamente dal file `.env`
- Tutti i risultati vengono salvati nella directory `public/benchmarks/<benchmark>/` 
