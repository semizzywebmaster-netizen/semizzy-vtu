import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';
import AdminLayout from './Layouts/AdminLayout';

type PageModule = { default: ComponentType<Record<string, unknown>> };

type ViteImportMeta = ImportMeta & {
  env: { PROD: boolean };
  glob: (pattern: string) => Record<string, () => Promise<unknown>>;
};

if ((import.meta as ViteImportMeta).env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
      // Offline support is optional; a failed registration must not block the app.
    });
  });
}

createInertiaApp({
  resolve: async (name) => {
    const pages = (import.meta as ViteImportMeta).glob('./Pages/**/*.tsx');
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) throw new Error(`Inertia page not found: ${name}`);

    const module = (await page()) as PageModule;
    const ResolvedPage = module.default;
    const isAdminPage = name === 'Dashboard' || name.startsWith('Admin/');

    if (!isAdminPage) {
      return ResolvedPage;
    }

    return (pageProps: Record<string, unknown>) => (
      <AdminLayout>
        <ResolvedPage {...pageProps} />
      </AdminLayout>
    );
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
