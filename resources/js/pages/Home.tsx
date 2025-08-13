import React from 'react';
import { Head, usePage } from '@inertiajs/react';

interface PageProps {
  tests: Array<{
    name: string;
    slug: string;
    modelsCount: number;
  }>;
  [key: string]: any;
}

const GEN_META: Record<number, { icon: string; ball: string; count: number }> = {
  1: { icon: '🌱', ball: 'pokeball.svg', count: 151 },
  2: { icon: '🌿', ball: 'premierball.svg', count: 100 },
  3: { icon: '🔥', ball: 'ultraball.svg', count: 135 },
  4: { icon: '💎', ball: 'masterball.svg', count: 107 },
  5: { icon: '⚡', ball: 'pokeball.svg', count: 156 },
  6: { icon: '✨', ball: 'premierball.svg', count: 72 },
  7: { icon: '🌙', ball: 'ultraball.svg', count: 33 },
  8: { icon: '⚔️', ball: 'masterball.svg', count: 89 },
  9: { icon: '🦎', ball: 'pokeball.svg', count: 127 },
};

function parseGenFromName(name: string): number | null {
  // Tries to extract Gen number from strings like: "PokeBench v1 (Gen1 151)"
  const m = name.match(/gen\s*([0-9]+)/i);
  return m ? Number(m[1]) : null;
}

export default function Home() {
  const { tests } = usePage<PageProps>().props;

  return (
    <>
      <Head title="PokeBenchAI - AI Benchmark Leaderboard" />

      <div className="min-h-screen bg-neutral-900 text-cream-100 p-6">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-10">
            <h1 className="text-4xl font-bold mb-3 text-cream-100 font-pixel">PokeBenchAI</h1>
            <p className="text-base text-cream-300 max-w-2xl mx-auto">
              Seleziona una generazione per vedere i risultati del benchmark AI sul riconoscimento di Pokémon
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {tests.map((t) => {
              const gen = parseGenFromName(t.name);
              const meta = gen ? GEN_META[gen] : undefined;
              const icon = meta?.icon ?? '🎯';
              const ball = meta?.ball ?? 'pokeball.svg';
              const count = meta?.count ?? 0;

              return (
                <a
                  key={t.slug}
                  href={`/benchmarks/${t.slug}`}
                  className="block text-left bg-neutral-850/50 hover:bg-neutral-800 border border-neutral-700 rounded-md p-5 transition-colors"
                >
                  <div className="flex items-center mb-3">
                    <img src={`/assets/ui/${ball}`} alt="ball" className="w-7 h-7 mr-3" />
                    <span className="text-2xl mr-2">{icon}</span>
                    <h2 className="text-xl font-bold text-cream-100 font-pixel truncate" title={t.name}>
                      {t.name}
                    </h2>
                  </div>
                  <div className="text-sm text-cream-300 mb-3">
                    Modelli: {t.modelsCount}
                  </div>
                  <div className="text-xs text-cream-400">
                    {count > 0 ? `${count} Pokémon` : 'Benchmark'}
                  </div>
                </a>
              );
            })}
          </div>
        </div>
      </div>
    </>
  );
}

