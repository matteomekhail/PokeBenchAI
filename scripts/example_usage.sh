#!/bin/bash

# Esempi di utilizzo dei comandi benchmark
# Questo file mostra come usare i nuovi comandi per lanciare tutti i modelli

echo "🚀 Esempi di utilizzo dei comandi benchmark PokeBenchAI"
echo ""

echo "📋 1. Comando Artisan (raccomandato):"
echo "   php artisan bench:run-all gen1"
echo "   php artisan bench:run-all gen2 --image-mode=url"
echo "   php artisan bench:run-all gen1 --tolerant"
echo ""

echo "📋 2. Script Shell:"
echo "   ./scripts/run_all_models.sh gen1"
echo "   ./scripts/run_all_models.sh gen2 url"
echo "   ./scripts/run_all_models.sh gen1 base64 true"
echo ""

echo "📋 3. Esempi completi:"
echo ""

echo "   # Lancia tutti i 21 modelli per Gen1 (default: base64)"
echo "   php artisan bench:run-all gen1"
echo ""

echo "   # Lancia tutti i 21 modelli per Gen2 con modalità URL"
echo "   php artisan bench:run-all gen2 --image-mode=url"
echo ""

echo "   # Lancia tutti i 21 modelli per Gen1 con modalità tollerante"
echo "   php artisan bench:run-all gen1 --tolerant"
echo ""

echo "   # Lancia tutti i 21 modelli per Gen2 con entrambe le opzioni"
echo "   php artisan bench:run-all gen2 --image-mode=url --tolerant"
echo ""

echo "📊 Modelli che verranno lanciati automaticamente:"
echo "   - OpenAI: gpt-5-chat, gpt-4o-2024-11-20, gpt-4o-mini"
echo "   - Anthropic: claude-opus-4.1, claude-sonnet-4, claude-3.5-sonnet, claude-3.7-sonnet*"
echo "   - Google: gemini-2.5-flash, gemini-2.0-flash-001, gemini-2.5-flash-lite"
echo "   - Mistral AI: mistral-small-3.2-24b-instruct, pixtral-12b, pixtral-large-2411"
echo "   - Altri: qwen*, grok-2-vision-1212, llama-3.2*, glm-4.5v"
echo ""

echo "⚠️  Note importanti:"
echo "   - Assicurati di avere sufficienti crediti OpenRouter"
echo "   - Tutti i modelli vengono lanciati in parallelo"
echo "   - I risultati vengono salvati automaticamente"
echo "   - Il leaderboard viene aggiornato automaticamente"
echo ""

echo "🎯 Per iniziare subito:"
echo "   php artisan bench:run-all gen1"
