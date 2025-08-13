#!/bin/bash

# Script per lanciare tutti i modelli in parallelo per un benchmark specifico
# Uso: ./scripts/run_all_models.sh <benchmark> [image_mode] [tolerant]

# Lista di tutti i modelli disponibili
MODELS=(
    "openai/gpt-5-chat"
    "openai/gpt-4o-2024-11-20"
    "openai/gpt-4o-mini"
    "anthropic/claude-opus-4.1"
    "anthropic/claude-sonnet-4"
    "anthropic/claude-3.5-sonnet"
    "anthropic/claude-3.7-sonnet"
    "anthropic/claude-3.7-sonnet:beta"
    "anthropic/claude-3.7-sonnet:thinking"
    "google/gemini-2.5-flash"
    "google/gemini-2.0-flash-001"
    "google/gemini-2.5-flash-lite"
    "mistralai/mistral-small-3.2-24b-instruct"
    "mistralai/pixtral-12b"
    "mistralai/pixtral-large-2411"
    "qwen/qwen2.5-vl-32b-instruct"
    "qwen/qwen-vl-max"
    "x-ai/grok-2-vision-1212"
    "meta-llama/llama-3.2-11b-vision-instruct"
    "meta-llama/llama-3.2-90b-vision-instruct"
    "z-ai/glm-4.5v"
)

# Benchmark disponibili
AVAILABLE_BENCHMARKS=(
    "gen1:Generation 1 (151 Pokémon)"
    "gen2:Generation 2 (100 Pokémon)"
    "gen3:Generation 3 (135 Pokémon)"
    "gen4:Generation 4 (107 Pokémon)"
    "gen5:Generation 5 (156 Pokémon)"
    "gen6:Generation 6 (72 Pokémon)"
    "gen7:Generation 7 (33 Pokémon)"
    "gen8:Generation 8 (89 Pokémon)"
    "gen9:Generation 9 (127 Pokémon)"
)

# Funzione per mostrare l'uso
show_usage() {
    echo "🚀 Script per lanciare tutti i modelli in parallelo per un benchmark specifico"
    echo ""
    echo "📋 Uso: $0 <benchmark> [image_mode] [tolerant]"
    echo ""
    echo "📊 Benchmark disponibili:"
    for bench in "${AVAILABLE_BENCHMARKS[@]}"; do
        IFS=':' read -r key desc <<< "$bench"
        echo "   - $key: $desc"
    done
    echo ""
    echo "🖼️  Modalità immagine: url, base64 (default: base64)"
    echo "🔧 Modalità tollerante: true, false (default: false)"
    echo ""
    echo "📝 Esempi:"
    echo "   $0 gen1                    # Lancia tutti i modelli per Gen1"
    echo "   $0 gen2 url                # Lancia tutti i modelli per Gen2 con image_mode=url"
    echo "   $0 gen3 base64 true        # Lancia tutti i modelli per Gen3 con image_mode=base64 e tolerant=true"
    echo "   $0 gen9                    # Lancia tutti i modelli per Gen9 (default: base64)"
    echo ""
    echo "🤖 Modelli disponibili: ${#MODELS[@]}"
    echo "   ${MODELS[*]}"
}

# Controllo parametri
if [ $# -lt 1 ]; then
    show_usage
    exit 1
fi

BENCHMARK=$1
IMAGE_MODE=${2:-base64}
TOLERANT=${3:-false}

# Validazione benchmark
VALID_BENCHMARK=false
for bench in "${AVAILABLE_BENCHMARKS[@]}"; do
    IFS=':' read -r key desc <<< "$bench"
    if [ "$key" = "$BENCHMARK" ]; then
        VALID_BENCHMARK=true
        BENCHMARK_DESC=$desc
        break
    fi
done

if [ "$VALID_BENCHMARK" = false ]; then
    echo "❌ Benchmark non valido: $BENCHMARK"
    echo ""
    show_usage
    exit 1
fi

# Validazione modalità immagine
if [ "$IMAGE_MODE" != "url" ] && [ "$IMAGE_MODE" != "base64" ]; then
    echo "❌ Modalità immagine non valida: $IMAGE_MODE (deve essere 'url' o 'base64')"
    exit 1
fi

# Validazione modalità tollerante
if [ "$TOLERANT" != "true" ] && [ "$TOLERANT" != "false" ]; then
    echo "❌ Modalità tollerante non valida: $TOLERANT (deve essere 'true' o 'false')"
    exit 1
fi

echo "🚀 Starting parallel benchmark run for $BENCHMARK"
echo "📊 Description: $BENCHMARK_DESC"
echo "🤖 Models: ${#MODELS[@]}"
echo "🖼️  Image mode: $IMAGE_MODE"
echo "🔧 Tolerant mode: $TOLERANT"
echo ""

# Controllo se esiste la cartella del benchmark
if [ ! -d "public/benchmarks/$BENCHMARK" ]; then
    echo "❌ Cartella benchmark non trovata: public/benchmarks/$BENCHMARK"
    echo "💡 Assicurati di aver creato il benchmark con: python eval/build_gen.py --gen ${BENCHMARK#gen} --out public/benchmarks"
    exit 1
fi

# Controllo se i file necessari esistono
if [ ! -f "public/benchmarks/$BENCHMARK/images.json" ] || [ ! -f "public/benchmarks/$BENCHMARK/labels.txt" ] || [ ! -f "public/benchmarks/$BENCHMARK/ground_truth.jsonl" ]; then
    echo "❌ File benchmark mancanti in public/benchmarks/$BENCHMARK"
    echo "💡 Assicurati di aver creato il benchmark completo"
    exit 1
fi

echo "✅ Benchmark $BENCHMARK trovato e valido"
echo "📁 Directory: public/benchmarks/$BENCHMARK"
echo ""

# Attivazione ambiente virtuale e setup API key
if [ -f ".venv/bin/activate" ]; then
    source .venv/bin/activate
    echo "🐍 Ambiente virtuale Python attivato"
else
    echo "⚠️  Ambiente virtuale Python non trovato, continuo senza..."
fi

# Estrazione API key
if [ -f ".env" ]; then
    export OPENROUTER_API_KEY=$(grep -E '^OPENROUTER_API_KEY=' .env | cut -d= -f2-)
    if [ -n "$OPENROUTER_API_KEY" ]; then
        echo "🔑 API key OpenRouter configurata"
    else
        echo "⚠️  API key OpenRouter non trovata nel file .env"
    fi
else
    echo "⚠️  File .env non trovato"
fi

echo ""
echo "⚡ Esecuzione di tutti i modelli in parallelo..."
echo ""

# Costruzione comandi per ogni modello
COMMANDS=()
for model in "${MODELS[@]}"; do
    cmd="php artisan bench:run --bench=$BENCHMARK --model=\"$model\""
    
    if [ "$IMAGE_MODE" != "base64" ]; then
        cmd="$cmd --image-mode=$IMAGE_MODE"
    fi
    
    if [ "$TOLERANT" = "true" ]; then
        cmd="$cmd --tolerant"
    fi
    
    COMMANDS+=("$cmd")
done

# Esecuzione parallela
echo "🔄 Lanciando ${#COMMANDS[@]} modelli in parallelo..."
echo ""

# Unione comandi con & e aggiunta di wait
FULL_COMMAND="("
for i in "${!COMMANDS[@]}"; do
    if [ $i -gt 0 ]; then
        FULL_COMMAND="$FULL_COMMAND & "
    fi
    FULL_COMMAND="$FULL_COMMAND${COMMANDS[$i]}"
done
FULL_COMMAND="$FULL_COMMAND & wait) | cat"

echo "📝 Comando completo:"
echo "$FULL_COMMAND"
echo ""

# Esecuzione
eval "$FULL_COMMAND"

# Controllo risultato
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Tutti i benchmark sono stati completati con successo!"
    echo "📊 Risultati salvati in: public/benchmarks/$BENCHMARK/"
    echo "🏆 Leaderboard aggiornato in: resources/data/leaderboard.json"
else
    echo ""
    echo "❌ Alcuni benchmark sono falliti. Controlla l'output sopra per i dettagli."
    exit 1
fi 