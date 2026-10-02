import { Link } from '@inertiajs/react';

type Props = {
  active?: 'home' | 'notifications' | 'profile';
  unreadCount?: number;
};

export default function CoreMobileNav({ active, unreadCount = 0 }: Props) {
  const base = 'flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-2 py-2 text-[11px] font-semibold';
  const activeClass = 'text-indigo-700';
  const idleClass = 'text-slate-600';
  const disabledClass = 'cursor-not-allowed text-slate-300';

  return (
    <nav aria-label="Main navigation" className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 p-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] backdrop-blur md:static md:mt-8 md:border-0 md:bg-transparent md:p-0">
      <div className="mx-auto flex max-w-6xl items-stretch justify-around gap-1 md:justify-start md:gap-3">
        <Link href="/dashboard" aria-current={active === 'home' ? 'page' : undefined} className={`${base} ${active === 'home' ? activeClass : idleClass}`}>
          <span aria-hidden="true" className="text-lg leading-5">⌂</span><span>Home</span>
        </Link>
        <button type="button" disabled aria-disabled="true" title="Available when a service addon is installed" className={`${base} ${disabledClass}`}>
          <span aria-hidden="true" className="text-lg leading-5">▦</span><span>Services</span>
        </button>
        <button type="button" disabled aria-disabled="true" title="Available when a transaction addon is installed" className={`${base} ${disabledClass}`}>
          <span aria-hidden="true" className="text-lg leading-5">↔</span><span>Transactions</span>
        </button>
        <Link href="/notifications" aria-current={active === 'notifications' ? 'page' : undefined} className={`${base} ${active === 'notifications' ? activeClass : idleClass}`}>
          <span className="relative text-lg leading-5" aria-hidden="true">♧{unreadCount > 0 && <span className="absolute -right-3 -top-1 rounded-full bg-indigo-600 px-1 text-[9px] leading-4 text-white">{unreadCount > 99 ? '99+' : unreadCount}</span>}</span><span>Notifications</span>
        </Link>
        <Link href="/profile" aria-current={active === 'profile' ? 'page' : undefined} className={`${base} ${active === 'profile' ? activeClass : idleClass}`}>
          <span aria-hidden="true" className="text-lg leading-5">◎</span><span>Profile</span>
        </Link>
      </div>
    </nav>
  );
}
