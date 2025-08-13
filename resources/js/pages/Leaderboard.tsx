import React from 'react';
import { Link } from '@inertiajs/react';
type Metrics = { top1: number; top5?: number; macro_f1?: number };
type Row = { model: string; task: string; benchmark: string; metrics: Metrics; date: string };
type Props = { results?: Row[] };

export default function Leaderboard({ results = [] }: Props) {
  if (!Array.isArray(results)) {
    results = [];
  }
  const r = results[0];
  const top1 = r ? (r.metrics.top1 * 100).toFixed(2) : '—';
  const top5 = r ? (r.metrics.top5! * 100).toFixed(2) : '—';
  const f1 = r && r.metrics.macro_f1 != null ? (r.metrics.macro_f1 * 100).toFixed(2) : '—';

  return (
    <div className="max-w-6xl mx-auto p-6">
      <div className="mb-6">
        <h1 className="pk-title">PokeBenchAI</h1>
        <p className="text-xs text-gray-700">Benchmark: Gen1 (151) • Modello: {r?.model || '—'}</p>
      </div>

      <div className="grid md:grid-cols-3 gap-4 mb-6">
        <div className="bg-white rounded shadow p-4">
          <div className="text-sm text-gray-500">Top‑1</div>
          <div className="text-3xl font-semibold">{top1}%</div>
        </div>
        <div className="bg-white rounded shadow p-4">
          <div className="text-sm text-gray-500">Top‑5</div>
          <div className="text-3xl font-semibold">{top5}%</div>
        </div>
        <div className="bg-white rounded shadow p-4">
          <div className="text-sm text-gray-500">Macro‑F1</div>
          <div className="text-3xl font-semibold">{f1}%</div>
        </div>
      </div>

      <div className="pk-card p-4 mb-6">
        <div className="flex items-end gap-4">
          <div className="flex-1">
            <div className="h-2 bg-gray-100 rounded">
              <div className="h-2 bg-blue-600 rounded" style={{ width: `${r ? r.metrics.top1 * 100 : 0}%` }} />
            </div>
            <div className="mt-1 text-xs text-gray-500">Top‑1</div>
          </div>
          <div className="flex-1">
            <div className="h-2 bg-gray-100 rounded">
              <div className="h-2 bg-emerald-600 rounded" style={{ width: `${r ? r.metrics.top5! * 100 : 0}%` }} />
            </div>
            <div className="mt-1 text-xs text-gray-500">Top‑5</div>
          </div>
          <div className="flex-1">
            <div className="h-2 bg-gray-100 rounded">
              <div className="h-2 bg-purple-600 rounded" style={{ width: `${r && r.metrics.macro_f1 != null ? r.metrics.macro_f1 * 100 : 0}%` }} />
            </div>
            <div className="mt-1 text-xs text-gray-500">Macro‑F1</div>
          </div>
        </div>
      </div>

      <div className="flex items-center justify-between">
        <div className="text-sm text-gray-600">Aggiornato: {r?.date || '—'}</div>
        <Link href="/results/gen1" className="px-4 py-2 rounded border hover:bg-gray-50">Vedi risultati Gen1</Link>
      </div>
    </div>
  );
}

