#!/usr/bin/env python3
import argparse
import json
from collections import Counter
from pathlib import Path

import pandas as pd
from sklearn.metrics import f1_score


def load_json(path: Path):
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)


def compute_topk_metrics(gt_df: pd.DataFrame, pred_df: pd.DataFrame, topk=(1, 5)):
    joined = gt_df.merge(pred_df, on='image_id', how='inner', suffixes=('_gt', '_pred'))
    if joined.empty:
        return {f'top{k}': 0.0 for k in topk} | {'macro_f1': 0.0}

    correct_at_k = {k: 0 for k in topk}
    y_true = []
    y_pred = []

    for _, row in joined.iterrows():
        true_cls = row['class']
        probs = row['probs'] if isinstance(row['probs'], dict) else {}
        # Ordina per probabilità decrescente
        sorted_preds = sorted(probs.items(), key=lambda x: x[1], reverse=True)
        top_labels = [label for label, _ in sorted_preds]
        for k in topk:
            if true_cls in top_labels[:k]:
                correct_at_k[k] += 1

        # Per F1 macro usiamo la classe con prob massima
        y_true.append(true_cls)
        y_pred.append(top_labels[0] if top_labels else None)

    n = len(joined)
    metrics = {f'top{k}': correct_at_k[k] / n for k in topk}
    # Rimuovi None
    paired = [(t, p) for t, p in zip(y_true, y_pred) if p is not None]
    if paired:
        y_true_f1, y_pred_f1 = zip(*paired)
        metrics['macro_f1'] = f1_score(y_true_f1, y_pred_f1, average='macro')
    else:
        metrics['macro_f1'] = 0.0
    return metrics


def main():
    parser = argparse.ArgumentParser(description='Score PokeBenchAI predictions (T1 classification).')
    parser.add_argument('--ground-truth', required=True, help='Path to ground truth JSONL or JSON')
    parser.add_argument('--predictions', required=True, help='Path to predictions JSON or JSONL')
    parser.add_argument('--out', default='-', help='Where to write metrics JSON (default stdout)')
    parser.add_argument('--duration-ms', type=int, default=None, help='Optional duration in milliseconds to include in output')
    args = parser.parse_args()

    gt_path = Path(args.ground_truth)
    pred_path = Path(args.predictions)

    # Ground truth format: JSONL lines: {"image_id": "0001.png", "class": "bulbasaur"}
    if gt_path.suffix.lower() == '.jsonl':
        gt_df = pd.read_json(gt_path, lines=True)
    else:
        gt_df = pd.DataFrame(load_json(gt_path))

    # Predictions format: {"entries": [{"image_id": "0001.png", "probs": {"bulbasaur": 0.9, ...}}]}
    if pred_path.suffix.lower() == '.jsonl':
        pred_df = pd.read_json(pred_path, lines=True)
        usage = None
    else:
        pred_raw = load_json(pred_path)
        entries = pred_raw['entries'] if isinstance(pred_raw, dict) and 'entries' in pred_raw else pred_raw
        usage = pred_raw.get('usage') if isinstance(pred_raw, dict) else None
        pred_df = pd.DataFrame(entries)

    # Normalize columns
    if 'label' in pred_df.columns and 'probs' not in pred_df.columns:
        # If only top-1 label provided, convert to probs with 1.0
        pred_df['probs'] = pred_df['label'].apply(lambda l: {l: 1.0} if pd.notna(l) else {})

    metrics = compute_topk_metrics(gt_df[['image_id', 'class']], pred_df[['image_id', 'probs']])

    output = {'task': 'T1', 'metrics': metrics}
    if usage is not None:
        output['usage'] = usage
    if args.duration_ms is not None:
        output['duration_ms'] = int(args.duration_ms)

    if args.out == '-' or args.out == '/dev/stdout':
        print(json.dumps(output))
    else:
        Path(args.out).write_text(json.dumps(output), encoding='utf-8')


if __name__ == '__main__':
    main()

