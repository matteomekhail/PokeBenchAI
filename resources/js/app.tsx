import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
  resolve: (name: string) => {
    const pagesTsx = import.meta.glob('./pages/**/*.tsx', { eager: true }) as Record<string, any>;
    const mod = pagesTsx[`./pages/${name}.tsx`];
    if (!mod) throw new Error(`Page not found: ${name}`);
    return mod.default || mod;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});

