import '../css/app.css';
import { createInertiaApp, usePage } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType, PropsWithChildren } from 'react';
import AdminLayout from './Layouts/AdminLayout';

type PageModule = { default: ComponentType<Record<string, unknown>> };
type ViteImportMeta = ImportMeta & { env: { PROD: boolean }; glob: (pattern: string) => Record<string, () => Promise<unknown>> };

function applyTheme(primary: string) {
  const safe = /^#[0-9A-Fa-f]{6}$/.test(primary) ? primary : '#2563EB';
  document.documentElement.style.setProperty('--brand-primary', safe);
  document.documentElement.style.setProperty('--brand-primary-soft', 'color-mix(in srgb, ' + safe + ' 10%, white)');
  document.documentElement.style.setProperty('--brand-primary-medium', 'color-mix(in srgb, ' + safe + ' 18%, white)');
  document.documentElement.style.setProperty('--brand-primary-dark', 'color-mix(in srgb, ' + safe + ' 82%, black)');
}

function ThemeBridge({ children }: PropsWithChildren) {
  const page = usePage<{ platform?: { theme_primary?: string } }>();
  applyTheme(page.props.platform?.theme_primary ?? '#2563EB');
  return <>{children}</>;
}

if ((import.meta as ViteImportMeta).env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
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

    const themed = (pageProps: Record<string, unknown>) => (
      <ThemeBridge>
        {isAdminPage ? <AdminLayout><ResolvedPage {...pageProps} /></AdminLayout> : <ResolvedPage {...pageProps} />}
      </ThemeBridge>
    );

    return themed;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
