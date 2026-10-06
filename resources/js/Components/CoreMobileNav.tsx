import { Link } from '@inertiajs/react';

type Props = {
  active?: 'home' | 'services' | 'transactions' | 'notifications' | 'profile';
  unreadCount?: number;
};

export default function CoreMobileNav({ active, unreadCount = 0 }: Props) {
  const base = 'flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-2 py-2 text-[11px] font-semibold';
  const activeClass = 'text-indigo-700';
  const idleClass = 'text-slate-600';

  const items = [
    { key: 'home' as const, label: 'Home', href: '/dashboard', icon: '⌂' },
    { key: 'services' as const, label: 'Services', href: '/vtu', icon: '✦' },
    { key: 'transactions' as const, label: 'Transactions', href: '/transactions', icon: '↔' },
    { key: 'notifications' as const, label: 'Notifications', href: '/notifications', icon: '♧' },
    { key: 'profile' as const, label: 'Profile', href: '/profile', icon: '◎' },
  ];

  return (
    <nav aria-label="Main navigation" className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 p-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-[0_-4px_18px_rgba(15,23,42,0.06)] backdrop-blur">
      <div className="mx-auto grid max-w-lg grid-cols-5 gap-1">
        {items.map(item => (
          <Link key={item.key} href={item.href} aria-current={active === item.key ? 'page' : undefined} className={base + ' ' + (active === item.key ? activeClass : idleClass)}>
            <span className="relative text-lg leading-5" aria-hidden="true">
              {item.icon}
              {item.key === 'notifications' && unreadCount > 0 && <span className="absolute -right-3 -top-1 rounded-full bg-indigo-600 px-1 text-[9px] leading-4 text-white">{unreadCount > 99 ? '99+' : unreadCount}</span>}
            </span>
            <span>{item.label}</span>
          </Link>
        ))}
      </div>
    </nav>
  );
}
