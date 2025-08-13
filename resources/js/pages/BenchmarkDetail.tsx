import React, { useMemo, useEffect, useRef } from 'react';
import { Link } from '@inertiajs/react';
import { Bar } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js';
ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);
type SeriesItem = { model: string; modelSlug?: string; link?: string; top1: number; duration_ms?: number; usage?: { prompt_tokens?: number; completion_tokens?: number; total_tokens?: number } };
type Props = { title?: string; slug?: string; series?: SeriesItem[] };

export default function BenchmarkDetail({ title = 'Benchmark', slug = '', series = [] }: Props) {
  const sorted = [...series].sort((a, b) => (b.top1 || 0) - (a.top1 || 0));
  const hasData = sorted.length > 0 && sorted.some((s) => (s.top1 || 0) > 0);
  return (
    <div>
      <div className="mb-6">
        <h1 className="pk-title">{title}</h1>
        <p className="text-xs text-gray-300">Model comparison (Top‑1 %)</p>
      </div>

      {!hasData ? (
        <div className="flex items-center justify-center h-64 rounded-md border border-neutral-700 bg-neutral-850/50">
          <div className="text-center">
            <img src="/assets/ui/pokeball.svg" alt="pokeball" className="mx-auto mb-3 h-8 w-8 opacity-70" />
            <p className="text-sm text-cream-300">No results available for this benchmark.</p>
            <p className="text-xs text-cream-400 mt-1">Run the benchmark to see the chart.</p>
          </div>
        </div>
      ) : (
      <div className="mt-2">
        <Bar
          data={useMemo(() => ({
            labels: sorted.map((s) => s.model.replace(/^.+\//, '')),
            datasets: [
              {
                label: 'Top‑1 %',
                data: sorted.map((s) => Number(s.top1?.toFixed(2))),
                backgroundColor: sorted.map((_, i) => (i === 0 ? '#FFD54F' : '#E53935')),
                borderColor: sorted.map((_, i) => (i === 0 ? '#1b1b1b' : '#ffffff')),
                borderWidth: 2,
                borderRadius: 6,
                barPercentage: 0.38,
                categoryPercentage: 0.7,
                hoverBackgroundColor: sorted.map((_, i) => (i === 0 ? '#FBC02D' : '#C62828')),
              },
            ],
          }), [sorted])}
          options={{
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 28, right: 8, left: 8, bottom: 12 } },
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  title: (ctx) => {
                    const idx = ctx[0].dataIndex;
                    const s = sorted[idx];
                    return s.model; // Show full model name in tooltip title
                  },
                  label: (ctx) => {
                    const idx = ctx.dataIndex;
                    const s = sorted[idx];
                    const base = `${ctx.parsed.y.toFixed(2)}%`;
                    const dur = s.duration_ms ? ` • ${Math.round((s.duration_ms||0)/1000)}s` : '';
                    const u = s.usage; const tok = u ? ` • tok ${u.prompt_tokens||0}/${u.completion_tokens||0}` : '';
                    return base + dur + tok;
                  }
                },
                backgroundColor: '#111',
                borderColor: '#fff',
                borderWidth: 1,
                titleColor: '#fff',
                bodyColor: '#fff',
              },
              title: { display: false },
            },
            scales: {
              x: {
                ticks: {
                  color: '#ddd',
                  font: { family: 'Press Start 2P', size: 9 },
                  maxRotation: 60,
                  minRotation: 60,
                  autoSkip: false
                },
                grid: { display: false },
              },
              y: {
                ticks: {
                  color: '#bbb',
                  callback: (val) => `${val}%`,
                  font: { family: 'Press Start 2P', size: 8 },
                },
                grid: { color: 'rgba(255,255,255,0.08)' },
                min: 0,
                max: 100,
              },
            },
            onClick: (_evt, elements) => {
              if (!elements.length) return;
              const idx = elements[0].index;
              const target = sorted[idx];
              if (target?.modelSlug || target?.link) {
                window.location.href = target.link || `/benchmarks/${slug}/${target.modelSlug}`;
              }
            },
          }}
          plugins={useMemo(() => {
            // Draw item icon above each bar by rank
            const poke = new Image(); poke.src = '/assets/items/poke-ball.png';
            const ultra = new Image(); ultra.src = '/assets/items/ultra-ball.png';
            const master = new Image(); master.src = '/assets/items/master-ball.png';
            const premier = new Image(); premier.src = '/assets/items/premier-ball.png';
            const plugin = {
              id: 'bar-icons',
              afterDatasetsDraw: (chart: any) => {
                const ctx = chart.ctx as CanvasRenderingContext2D;
                const meta = chart.getDatasetMeta(0);
                meta.data.forEach((bar: any, idx: number) => {
                  const x = bar.x - 10;
                  const y = bar.y - 26;
                  let img = poke;
                  if (idx === 0) img = master;
                  else if (idx === 1) img = ultra;
                  else if (idx === 2) img = premier;
                  if (img.complete) {
                    ctx.save();
                    ctx.drawImage(img, x, y, 20, 20);
                    ctx.restore();
                  }
                });
              },
            };
            return [plugin];
          }, [sorted.length])}
          height={340}
        />
      </div>
      )}
      {/* micro-decorations */}
      <div className="mt-3 flex items-center gap-3 opacity-80">
        <img src="/assets/ui/ultraball.svg" alt="ultra" className="h-5 w-5"/>
        <span className="text-[10px] text-gray-400">Tip: click a bar to open the model details</span>
      </div>
    </div>
  );
}

