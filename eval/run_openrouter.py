#!/usr/bin/env python3
import argparse
import json
import os
import time
from pathlib import Path

from providers.openrouter import classify_images, ImageInput, OpenRouterError
import requests
import base64


def main():
    parser = argparse.ArgumentParser(description='Run OpenRouter classification on images and save predictions.json')
    parser.add_argument('--model', required=True, help='e.g. openai/gpt-oss-20b:free')
    parser.add_argument('--images', required=True, help='JSON list: [{"image_id","url"}]')
    parser.add_argument('--labels', required=True, help='Text file with one label per line')
    parser.add_argument('--out', default='predictions.json', help='Output predictions path')
    parser.add_argument('--limit', type=int, default=None, help='Limit number of images (for rate limits)')
    parser.add_argument('--start', type=int, default=0, help='Start index (offset) in the images list')
    parser.add_argument('--progress', action='store_true', help='Print progress and ETA')
    parser.add_argument('--image-mode', choices=['base64', 'url'], default='base64', help='How to send images to the API')
    parser.add_argument('--tolerant', action='store_true', help='Accept free text and map to labels if JSON is missing')
    args = parser.parse_args()

    api_key = os.environ.get('OPENROUTER_API_KEY')
    if not api_key:
        raise SystemExit('Set OPENROUTER_API_KEY')

    images_spec = json.loads(Path(args.images).read_text())
    images: list[ImageInput] = []
    for i in images_spec:
        image_id = i['image_id']
        if args.image_mode == 'url' and 'url' in i and i['url']:
            images.append(ImageInput(image_id=image_id, url=i['url']))
        elif 'b64' in i and i['b64']:
            images.append(ImageInput(image_id=image_id, b64=i['b64']))
        elif 'url' in i and i['url']:
            # Default base64 embed
            r = requests.get(i['url'], timeout=30)
            r.raise_for_status()
            mime = r.headers.get('Content-Type', 'image/png')
            b64 = base64.b64encode(r.content).decode('utf-8')
            images.append(ImageInput(image_id=image_id, b64=b64, mime=mime))
        else:
            raise SystemExit(f"image spec missing url/b64 for {image_id}")
    labels = [l.strip() for l in Path(args.labels).read_text().splitlines() if l.strip()]

    # Apply windowing for rate limits
    start = max(0, int(args.start))
    end = len(images) if args.limit is None else min(len(images), start + int(args.limit))
    window = images[start:end]

    collected = []
    usage = {"prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0, "input_images": 0}
    t0 = time.time()
    total = len(window)

    def write_partial():
        Path(args.out).write_text(json.dumps({"entries": collected, "usage": usage}, ensure_ascii=False), encoding='utf-8')

    for i, img in enumerate(window):
        idx = start + i
        try:
            res, u = classify_images(api_key, args.model, [img], labels, tolerant=args.tolerant)
            collected.extend(res)
            for k in usage.keys():
                try:
                    usage[k] += int(u.get(k, 0))
                except Exception:
                    pass
            if args.progress:
                elapsed = time.time() - t0
                speed = (i + 1) / elapsed if elapsed > 0 else 0
                remaining = (total - (i + 1)) / speed if speed > 0 else 0
                eta_min = int(remaining // 60)
                eta_sec = int(remaining % 60)
                print(f"[{i+1}/{total}] {img.image_id} ✓  ETA {eta_min:02d}:{eta_sec:02d}", flush=True)
        except OpenRouterError as e:
            print(f"[{i+1}/{total}] {img.image_id} ✗ {e}", flush=True)
            break
        finally:
            write_partial()

    duration_ms = int((time.time() - t0) * 1000)
    print(f'Wrote {args.out} ({len(collected)} entries) in {duration_ms} ms', flush=True)
    # If caller follows with score.py, they can pass --duration-ms ${duration_ms}


if __name__ == '__main__':
    main()

