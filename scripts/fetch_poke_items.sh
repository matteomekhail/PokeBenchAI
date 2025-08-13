#!/usr/bin/env bash
set -euo pipefail
DEST="public/assets/items"
BASE="https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items"
ITEMS=(
  poke-ball.png great-ball.png ultra-ball.png master-ball.png premier-ball.png
  safari-ball.png net-ball.png dive-ball.png nest-ball.png repeat-ball.png
  timer-ball.png luxury-ball.png heal-ball.png dusk-ball.png quick-ball.png
  fast-ball.png friend-ball.png heavy-ball.png level-ball.png love-ball.png
  lure-ball.png moon-ball.png
  potion.png super-potion.png hyper-potion.png max-potion.png full-restore.png
  revive.png max-revive.png rare-candy.png
)
mkdir -p "$DEST"
for f in "${ITEMS[@]}"; do
  echo "Downloading $f"
  curl -sSfL "$BASE/$f" -o "$DEST/$f"
  # add tiny webp for performance
  cwebp -quiet -q 85 "$DEST/$f" -o "${DEST}/${f%.png}.webp" || true
done
