import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

type ViteImportMeta = ImportMeta & {
  glob: (pattern: string) => Record<string, () => Promise<unknown>>;
};

createInertiaApp({
  resolve: async (name) => {
    const pages = (import.meta as ViteImportMeta).glob('./Pages/**/*.tsx');
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) throw new Error(`Inertia page not found: ${name}`);
    return (await page()) as never;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
