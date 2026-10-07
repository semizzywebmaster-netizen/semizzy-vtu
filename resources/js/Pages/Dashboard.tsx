import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Metric = { label: string; value: number | string; description?: string };
type QuickLink = { label: string; url: string };
type ServiceItem = { key: string; name: string; description?: string | null; url: string };
type ServiceCategory = { key: string; name: string; description?: string | null; services: ServiceItem[] };
type Wallet = { available_minor: string; held_minor: string; currency: string; status: string };
type Tier = { id: number; name: string };
type TierLimit = { id: number; name: string; dailyLimitMinor: string; balanceLimitMinor: string | null; upgradeLabel: string | null };
type DashboardMessages = { greeting?: { message: string } | null; quote?: { message: string } | null; seasonal?: { message: string } | null; promotional?: { message: string } | null; seasonalSlides?: { id?: number; title?: string; message: string }[]; promotionSlides?: { id?: number; title?: string; message: string }[] };
type RecentTransaction = { id: number; reference: string; type: string; amountMinor: string; currency: string; createdAt: string | null };
type RequiredAction = { key: string; title: string; message: string; url: string; label: string; priority: string };

const iconFor = (value: string) => {
  const key = value.toLowerCase();
  if (key.includes('data')) return '▣';
  if (key.includes('airtime') || key.includes('voice')) return '◉';
  if (key.includes('electric') || key.includes('bill')) return '⌁';
  if (key.includes('cable') || key.includes('tv')) return '▤';
  if (key.includes('exam') || key.includes('education')) return '✎';
  if (key.includes('sms') || key.includes('message')) return '✉';
  if (key.includes('cash') || key.includes('payment') || key.includes('wallet')) return '₦';
  return '✦';
};

const money = (minor: string | undefined, currency = 'NGN') => {
  if (!minor) return '—';
  try {
    const value = BigInt(minor);
    const major = value / 100n;
    const cents = (value % 100n).toString().padStart(2, '0');
    const symbol = currency === 'NGN' ? '₦' : currency + ' ';
    return symbol + major.toLocaleString() + '.' + cents;
  } catch { return '—'; }
};

export default function Dashboard({ role, user, metrics = [], quickLinks = [], serviceCategories = [], wallet = null, tier = null, dashboardMessages = {}, recentTransactions = [], requiredActions = [], unreadNotifications = 0, platform }: {
  role: string; user?: { name?: string; email?: string; username?: string; initials?: string }; platform?: { platform_name?: string }; metrics?: Metric[]; quickLinks?: QuickLink[];
  serviceCategories?: ServiceCategory[]; wallet?: Wallet | null; tier?: Tier | null; tierLimits?: TierLimit[]; dashboardMessages?: DashboardMessages; recentTransactions?: RecentTransaction[]; requiredActions?: RequiredAction[]; unreadNotifications?: number;
}) {
  const siteName = platform?.platform_name || 'SEMIZZY ONE';
  const isUser = !['ADMIN', 'STAFF', 'SUPPORT'].includes(role);

  if (isUser) {
    const [showBalance, setShowBalance] = useState(true);
      const [promotionSlide, setPromotionSlide] = useState(0);
    const firstName = (user?.name || user?.username || 'there').trim().split(/\s+/)[0];
    const visibleCategories = serviceCategories.filter(category => category.services.length > 0);
    const featuredServices = visibleCategories
      .flatMap(category => category.services.map(service => ({ ...service, categoryName: category.name })))
      .filter((service, index, all) => all.findIndex(item => item.key === service.key) === index)
      .slice(0, 24);
    const promotionSlides = dashboardMessages.promotionSlides?.length
      ? dashboardMessages.promotionSlides
      : [dashboardMessages.promotional].filter(Boolean) as { message: string; title?: string }[];
    const initials = user?.initials || firstName.charAt(0).toUpperCase();
    const recent = recentTransactions || [];
    const required = requiredActions || [];

    useEffect(() => {
      if (promotionSlides.length < 2) return;
      const timer = window.setInterval(() => setPromotionSlide(value => (value + 1) % promotionSlides.length), 6000);
      return () => window.clearInterval(timer);
    }, [promotionSlides.length]);

    const formatTransactionType = (value: string) => value.replace(/[_:-]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    const isCredit = (value: string) => ['admin_fund','funding','wallet_funding','deposit','credit','refund'].some(key => value.toLowerCase().includes(key));

    return <main className="min-h-screen bg-slate-50 pb-24 text-slate-900">
      <Head title={`Home · ${siteName}`} />
      <header className="bg-white px-4 pb-4 pt-4 shadow-sm sm:px-8">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
          <Link href="/profile" className="flex min-w-0 items-center gap-3" aria-label="Open profile">
            <span className="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-black text-white ring-2 ring-slate-100">
              {initials}
              <span className="absolute -bottom-1 -right-1 rounded-full border-2 border-white bg-amber-400 px-1.5 py-0.5 text-[8px] font-black uppercase text-amber-950">T{tier?.id || 1}</span>
            </span>
            <span className="min-w-0"><span className="block text-xs font-semibold text-slate-500">Hi,</span><span className="block truncate text-lg font-black text-slate-900">{user?.username || firstName}</span></span>
          </Link>
          <div className="flex items-center gap-2">
            <Link href="/help" className="flex h-10 items-center gap-1.5 rounded-full bg-slate-100 px-3 text-sm font-bold text-slate-700" aria-label="Help">❓ <span className="hidden sm:inline">Help</span></Link>
            <Link href="/notifications" className="relative flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-lg" aria-label="Notifications">
              🔔
              {!!unreadNotifications && <span className="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-500 px-1 text-center text-[9px] font-black leading-5 text-white">{unreadNotifications > 99 ? '99+' : unreadNotifications}</span>}
            </Link>
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-6xl px-4 sm:px-8">
        <section className="mt-4 rounded-3xl bg-slate-900 p-5 text-white shadow-lg">
          <div className="flex items-start justify-between gap-4">
            <div><p className="text-xs font-bold uppercase tracking-wider text-slate-400">Available balance</p><p className="mt-2 text-3xl font-black tracking-tight">{showBalance ? (wallet ? money(wallet.available_minor, wallet.currency) : '₦0.00') : '₦••••••••'}</p></div>
            <button type="button" onClick={() => setShowBalance(value => !value)} className="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-base" aria-label={showBalance ? 'Hide balance' : 'Show balance'}>{showBalance ? '◉' : '◌'}</button>
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            <Link href="/wallet/fund" className="rounded-xl bg-white px-4 py-2 text-sm font-black text-slate-900">Add Money</Link>
            <Link href="/transactions" className="rounded-xl bg-white/10 px-4 py-2 text-sm font-bold text-white">Transaction History</Link>
          </div>
        </section>

        <section className="mt-3 rounded-2xl bg-white px-4 py-2 shadow-sm ring-1 ring-slate-200">
          <div className="mb-1 flex items-center justify-between"><p className="text-xs font-bold uppercase tracking-wider text-slate-400">Recent Transactions</p><Link href="/transactions" className="text-xs font-bold text-indigo-700">View all</Link></div>
          {recent.length === 0 ? <p className="py-2 text-xs text-slate-500">No transactions yet.</p> : recent.map(item => <div key={item.id} className="flex min-w-0 items-center justify-between gap-3 border-t border-slate-100 py-2 text-xs"><span className="min-w-0 truncate font-semibold text-slate-700">{formatTransactionType(item.type)} <span className="font-normal text-slate-400">• {item.createdAt ? new Date(item.createdAt).toLocaleString() : '—'}</span></span><span className={'shrink-0 font-black ' + (isCredit(item.type) ? 'text-emerald-600' : 'text-slate-900')}>{isCredit(item.type) ? '+' : '−'}{money(item.amountMinor, item.currency)}</span></div>)}
        </section>

        <section className="mt-3 grid grid-cols-3 gap-2">
          <Link href="/withdraw" className="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200"><span className="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-lg">↗</span><span className="mt-2 block text-xs font-black">Withdraw</span></Link>
          <Link href="/wallet/fund" className="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200"><span className="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-lg">₦</span><span className="mt-2 block text-xs font-black">Fund Wallet</span></Link>
          <Link href="/send-money" className="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200"><span className="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-lg">↔</span><span className="mt-2 block text-xs font-black">Send Money</span></Link>
        </section>

        {required.length > 0 && <section className="mt-5 rounded-3xl border border-amber-200 bg-amber-50 p-5">
          <div className="flex items-start gap-3"><span className="text-xl">⚠️</span><div className="min-w-0 flex-1"><p className="text-xs font-black uppercase tracking-wider text-amber-800">Important action required</p><h2 className="mt-1 text-lg font-black text-slate-900">{required[0].title}</h2><p className="mt-1 text-sm text-slate-600">{required[0].message}</p><Link href={required[0].url} className="mt-3 inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white">{required[0].label} →</Link></div></div>
          {required.length > 1 && <p className="mt-3 text-xs font-bold text-amber-800">+{required.length - 1} other required action{required.length > 2 ? 's' : ''}</p>}
        </section>}

        <section className="mt-6">
          <div className="flex items-end justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Services</p>
              <h2 className="mt-1 text-2xl font-black">What do you need today?</h2>
            </div>
            <Link href="/vtu" className="shrink-0 text-sm font-bold text-indigo-700">View all services →</Link>
          </div>

          {featuredServices.length === 0 ? (
            <div className="mt-5 rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center">
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl">✦</div>
              <h3 className="mt-3 font-bold">Services are being prepared</h3>
              <p className="mt-1 text-sm text-slate-500">Available services will appear here automatically when enabled in the catalogue.</p>
            </div>
          ) : (
            <div className="mt-5 grid grid-cols-2 gap-2 min-[480px]:grid-cols-4 md:grid-cols-6">
              {featuredServices.map((service, index) => (
                <Link
                  key={service.key}
                  href={service.url}
                  className={'group rounded-2xl border border-slate-100 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:bg-indigo-50 ' + (index >= 16 ? 'hidden md:block' : '')}
                >
                  <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-lg font-black text-indigo-700 group-hover:bg-white">
                    {iconFor(service.name)}
                  </span>
                  <p className="mt-2 line-clamp-2 text-xs font-black leading-4 sm:text-sm">{service.name}</p>
                  <p className="mt-1 truncate text-[10px] font-semibold text-slate-400">{service.categoryName}</p>
                </Link>
              ))}
            </div>
          )}

          <div className="mt-4 flex justify-center">
            <Link href="/vtu" className="rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-3 text-sm font-black text-indigo-700 hover:bg-indigo-100">
              Browse all services by category →
            </Link>
          </div>
        </section>

        {promotionSlides.length > 0 && <section className="mt-6 overflow-hidden rounded-3xl bg-indigo-600 text-white shadow-lg">
          <div className="p-5"><p className="text-xs font-black uppercase tracking-wider text-indigo-200">Promotions & offers</p><p className="mt-2 min-h-14 text-xl font-black">{promotionSlides[promotionSlide]?.title || 'Special offer'}</p><p className="mt-1 min-h-12 text-sm leading-6 text-indigo-100">{promotionSlides[promotionSlide]?.message}</p><div className="mt-4 flex items-center justify-between"><button type="button" onClick={() => setPromotionSlide(value => (value - 1 + promotionSlides.length) % promotionSlides.length)} className="rounded-full bg-white/15 px-3 py-1 text-xs font-bold">←</button><div className="flex gap-1.5">{promotionSlides.map((_, index) => <button key={index} type="button" onClick={() => setPromotionSlide(index)} className={'h-1.5 rounded-full ' + (index === promotionSlide ? 'w-5 bg-white' : 'w-1.5 bg-white/40')} aria-label={'Promotion slide ' + (index + 1)} />)}</div><button type="button" onClick={() => setPromotionSlide(value => (value + 1) % promotionSlides.length)} className="rounded-full bg-white/15 px-3 py-1 text-xs font-bold">→</button></div></div>
        </section>}
      </div>

      <section className="mt-5 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div className="flex items-center justify-between gap-3">
          <div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Everything in one place</p><h2 className="mt-1 text-xl font-black">All available features</h2><p className="mt-1 text-sm text-slate-500">Core account, wallet, security, support and service actions.</p></div>
          <Link href="/help" className="text-sm font-bold text-indigo-700">Help</Link>
        </div>
        <div className="mt-4 grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
          {quickLinks.map(link => <Link key={link.url + link.label} href={link.url} className="rounded-2xl border border-slate-200 p-3 text-sm font-bold hover:border-indigo-200 hover:bg-indigo-50">{link.label}</Link>)}
        </div>
      </section>

    </main>;
  }

  return <main className="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-8"><Head title={`Dashboard · ${siteName}`} /><div className="mx-auto max-w-7xl">
    <header className="relative rounded-3xl bg-slate-900 p-6 text-white shadow-xl"><button type="button" onClick={() => router.post('/logout')} className="absolute right-5 top-5 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold hover:bg-white/20">Logout</button><p className="text-xs font-semibold uppercase tracking-wider text-slate-300">{siteName}</p><h1 className="mt-2 text-3xl font-black">Dashboard</h1><p className="mt-2 text-sm text-slate-300">Role: {role}</p></header>
    {metrics.length > 0 && <section className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{metrics.map(metric => <article key={metric.label} className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{metric.label}</p><p className="mt-2 text-3xl font-black">{metric.value}</p><p className="mt-1 text-sm text-slate-500">{metric.description}</p></article>)}</section>}
    <section className="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><div className="flex items-center justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Workspace</p><h2 className="mt-1 text-xl font-black">Quick actions</h2></div><Link href="/profile" className="text-sm font-bold text-indigo-700">Profile</Link></div><div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{quickLinks.map(link => <Link key={link.url + link.label} href={link.url} className="rounded-2xl border border-slate-200 p-4 font-semibold hover:border-indigo-200 hover:bg-indigo-50">{link.label}</Link>)}</div></section>
  </div></main>;
}