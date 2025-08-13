import React, { useEffect, useState } from 'react';
type Row = {
  image_id: string;
  url: string | null;
  truth: string | null;
  topLabel: string | null;
  topProb: number;
  topk: [string, number][];
  correct: boolean;
};
type Props = {
  title?: string;
  groundTruthPath?: string;
  predictionsPath?: string;
  imagesPath?: string;
};

function parseJsonl(text: string) {
  return text
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter(Boolean)
    .map((l) => {
      try {
        return JSON.parse(l);
      } catch (_) {
        return null as any;
      }
    })
    .filter(Boolean) as Array<{ image_id: string; class: string }>;
}

export default function Results({ title = 'Risultati (anteprima)', groundTruthPath = '/benchmarks/gen1/ground_truth.jsonl', predictionsPath = '/benchmarks/gen1/predictions_gemini2.json', imagesPath = '/benchmarks/gen1/images.json' }: Props) {
  const [items, setItems] = useState<Row[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string>('');

  useEffect(() => {
    async function load() {
      try {
        const [gtRes, predRes, imgRes] = await Promise.all([
          fetch(groundTruthPath!),
          fetch(predictionsPath!),
          fetch(imagesPath!),
        ]);
        const gtText = await gtRes.text();
        const gt = parseJsonl(gtText);
        const gtMap = new Map(gt.map((r) => [r.image_id, r.class]));
        const preds = await predRes.json();
        const entries = Array.isArray(preds.entries) ? preds.entries : [];
        const images = await imgRes.json();
        const urlMap = new Map(images.map((i: any) => [i.image_id, i.url]));

        const rows: Row[] = entries.map((e: any) => {
          const probs: Record<string, number> = e.probs || {};
          const sorted = Object.entries(probs).sort((a, b) => (b[1] as number) - (a[1] as number));
          const [topLabel, topProb] = (sorted[0] || [null, 0]) as [string | null, number];
          const truth = (gtMap.get(e.image_id) as string | undefined) || null;
          const correct = !!(truth && topLabel && truth === topLabel);
          return {
            image_id: e.image_id as string,
            url: (urlMap.get(e.image_id) as string | undefined) || null,
            truth,
            topLabel,
            topProb: topProb || 0,
            topk: sorted.slice(0, 5) as [string, number][],
            correct,
          };
        });

        setItems(rows);
      } catch (err) {
        setError(String(err));
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [groundTruthPath, predictionsPath, imagesPath]);

  if (loading) return <div className="p-6">Caricamento…</div>;
  if (error) return <div className="p-6 text-red-600">Errore: {error}</div>;

  const accuracy = items.length
    ? (items.filter((r) => r.correct).length / items.length) * 100
    : 0;

  return (
    <div>
      <div className="flex items-baseline justify-between mb-4">
        <h1 className="pk-title">{title}</h1>
        <div className="text-xs text-gray-700">Accuracy top‑1: {accuracy.toFixed(2)}%</div>
      </div>

      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {items.map((it) => (
          <div key={it.image_id} className="group border border-[var(--pk-border)] rounded-xl p-3 bg-[var(--pk-panel)] hover:brightness-110 transition">
            {it.url ? (
              <div className="relative">
                <img src="/assets/items/poke-ball.png" alt="frame" className="absolute -top-2 -left-2 h-5 w-5 opacity-90"/>
                <img src={it.url} alt={it.image_id} className="w-full h-40 object-contain bg-[var(--pk-panel)] rounded" />
              </div>
            ) : (
              <div className="w-full h-40 bg-[var(--pk-cream)] rounded border border-[var(--pk-brown)] flex items-center justify-center text-gray-400">
                no image
              </div>
            )}
            <div className="mt-3 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-500">ID</span>
                <span className="font-mono">{it.image_id}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">GT</span>
                <span className="font-medium">{it.truth || '-'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">Pred</span>
                <span className={it.correct ? 'font-medium text-green-600' : 'font-medium text-red-600'}>
                  {it.topLabel || '-'} {it.topProb ? `(${(it.topProb * 100).toFixed(1)}%)` : ''}
                </span>
              </div>
              {it.topk && it.topk.length > 0 && (
                <div className="mt-2">
                  <div className="text-gray-500">Top‑5</div>
                  <ul className="mt-1 space-y-1">
                    {it.topk.map(([lab, p]) => (
                      <li key={lab} className="flex justify-between">
                        <span>{lab}</span>
                        <span>{(p * 100).toFixed(1)}%</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

