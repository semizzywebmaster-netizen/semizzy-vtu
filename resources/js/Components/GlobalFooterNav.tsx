import { Link, usePage } from '@inertiajs/react';

type FooterItem = { key: string; label: string; href: string; icon: string };
type PageProps = { platform?: { footer_menu?: FooterItem[] }; navigation?: { unreadNotifications?: number } };

const defaults: FooterItem[] = [
  { key: 'home', label: 'Home', href: '/dashboard', icon: '⌂' },
  { key: 'services', label: 'Services', href: '/vtu', icon: '✦' },
  { key: 'transactions', label: 'Transactions', href: '/transactions', icon: '↔' },
  { key: 'notifications', label: 'Alerts', href: '/notifications', icon: '♧' },
  { key: 'profile', label: 'Profile', href: '/profile', icon: '◎' },
];

export default function GlobalFooterNav() {
  const { platform, navigation } = usePage<PageProps>().props;
  const configured = Array.isArray(platform?.footer_menu) ? platform.footer_menu : [];
  const items = (configured.length === 5 ? configured : defaults).slice(0, 5);
  const current = typeof window !== 'undefined' ? window.location.pathname : '';
  const unread = navigation?.unreadNotifications || 0;

  return <nav aria-label="Global footer navigation" className="fixed inset-x-0 bottom-0 z-50 border-t border-[color:var(--so-border)] bg-[color:var(--so-surface)]/95 p-1.5 pb-[max(0.375rem,env(safe-area-inset-bottom))] shadow-[0_-4px_20px_rgba(15,23,42,0.08)] backdrop-blur">
    <div className="mx-auto grid w-full max-w-xl grid-cols-5 gap-1">
      {items.map(item => <Link key={item.key} href={item.href} aria-current={current === item.href ? 'page' : undefined} className="flex min-w-0 flex-col items-center justify-center rounded-xl px-1 py-2 text-[10px] font-bold text-[color:var(--so-muted)] transition hover:bg-[color:var(--so-primary-soft)] hover:text-[color:var(--so-primary)] aria-[current=page]:text-[color:var(--so-primary)]">
        <span className="relative text-lg leading-5" aria-hidden="true">{item.icon}{item.key === 'notifications' && unread > 0 ? <span className="absolute -right-3 -top-1 rounded-full bg-[color:var(--so-danger)] px-1 text-[8px] leading-4 text-white">{unread > 99 ? '99+' : unread}</span> : null}</span>
        <span className="max-w-full truncate">{item.label}</span>
      </Link>)}
    </div>
  </nav>;
}