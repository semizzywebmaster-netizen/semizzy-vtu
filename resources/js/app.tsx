import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';
import AdminLayout from './Layouts/AdminLayout';
import { ThemeBridge } from './Utils/ThemeSystem';

type PageModule = { default: ComponentType<Record<string, unknown>> };
type ViteImportMeta = ImportMeta & { env: { PROD: boolean }; glob: (pattern: string) => Record<string, () => Promise<unknown>> };
type Platform = { theme_key?: string; theme_custom_light?: Record<string, string>; theme_custom_dark?: Record<string, string>; skin_default?: 'light' | 'dark' };

if ((import.meta as ViteImportMeta).env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}

createInertiaApp({
  resolve: async (name) => {
    const pages = (import.meta as ViteImportMeta).glob('./Pages/**/*.tsx');
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) throw new Error(`Inertia page not found: ${name}`);
    const module = (await page()) as PageModule;
    const ResolvedPage = module.default;
    const isAdminPage = name === 'Dashboard' || name.startsWith('Admin/');
    return (pageProps: Record<string, unknown>) => {
      const platform = pageProps.platform as Platform | undefined;
      return <ThemeBridge platform={platform}>{isAdminPage ? <AdminLayout><ResolvedPage {...pageProps} /></AdminLayout> : <ResolvedPage {...pageProps} />}</ThemeBridge>;
    };
  },
  setup({ el, App, props }) { createRoot(el).render(<App {...props} />); },
});
