#!/usr/bin/env python3
import argparse
import json
from pathlib import Path
import time
import sys

import requests


def fetch_name(poke_id: int) -> str:
    url = f"https://pokeapi.co/api/v2/pokemon/{poke_id}"
    r = requests.get(url, timeout=20)
    r.raise_for_status()
    data = r.json()
    return data["name"].replace("-", " ")


def main():
    p = argparse.ArgumentParser(description="Build benchmark assets for a generation")
    p.add_argument("--gen", type=int, required=True, choices=[1, 2, 3, 4, 5, 6, 7, 8, 9], help="Generation id (1-9)")
    p.add_argument("--out", type=str, default="public/benchmarks", help="Output base folder")
    args = p.parse_args()

    # Gen ranges based on National Dex
    gen_ranges = {
        1: (1, 151, "gen1"),
        2: (152, 251, "gen2"),
        3: (252, 386, "gen3"),
        4: (387, 493, "gen4"),
        5: (494, 649, "gen5"),
        6: (650, 721, "gen6"),
        7: (777, 809, "gen7"),  # Skip 722-776 (Alola forms)
        8: (810, 898, "gen8"),
        9: (899, 1025, "gen9")  # Current max
    }

    start, end, folder = gen_ranges[args.gen]
    out_dir = Path(args.out) / folder
    out_dir.mkdir(parents=True, exist_ok=True)

    print(f"🔄 Building benchmark for Generation {args.gen} (Pokémon {start}-{end})")
    print(f"📁 Output directory: {out_dir}")

    images = []
    labels = []
    gt_lines = []

    for pid in range(start, end + 1):
        print(f"📥 Processing Pokémon {pid}...")
        
        # sprite
        images.append({
            "image_id": f"{pid}.png",
            "url": f"https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/{pid}.png",
        })
        
        # name
        try:
            name = fetch_name(pid)
            print(f"✅ {pid}: {name}")
        except Exception as e:
            print(f"❌ Failed to fetch {pid}: {e}", file=sys.stderr)
            name = f"pokemon-{pid}"
        
        labels.append(name)
        gt_lines.append(json.dumps({"image_id": f"{pid}.png", "class": name}))
        time.sleep(0.1)

    # Write files
    (out_dir / "images.json").write_text(json.dumps(images, indent=2), encoding="utf-8")
    (out_dir / "labels.txt").write_text("\n".join(labels) + "\n", encoding="utf-8")
    (out_dir / "ground_truth.jsonl").write_text("\n".join(gt_lines) + "\n", encoding="utf-8")
    
    print(f"✅ Wrote {out_dir}")
    print(f"📊 Total Pokémon: {len(images)}")
    print(f"📁 Files created:")
    print(f"   - {out_dir}/images.json")
    print(f"   - {out_dir}/labels.txt")
    print(f"   - {out_dir}/ground_truth.jsonl")


if __name__ == "__main__":
    main()

