import { Head, Link, router } from '@inertiajs/react';

type Metric = { label: string; value: number | string; description?: string };
type QuickLink = { label: string; url: string };
type ServiceItem = { key: string; name: string; description?: string | null; url: string };
type ServiceCategory = { key: string; name: string; description?: string | null; services: ServiceItem[] };
type Wallet = { available_minor: string; held_minor: string; currency: string; status: string };
type Tier = { id: number; name: string };
type TierLimit = { id: number; name: string; dailyLimitMinor: string; balanceLimitMinor: string | null; upgradeLabel: string | null };
type DashboardMessages = { greeting?: { message: string } | null; quote?: { message: string } | null; seasonal?: { message: string } | null; promotional?: { message: string } | null };

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

const limitMoney = (minor: string | null, currency = 'NGN') => minor === null ? 'Unlimited' : money(minor, currency);

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

export default function Dashboard({ role, user, metrics = [], quickLinks = [], serviceCategories = [], wallet = null, tier = null, tierLimits = [], dashboardMessages = {} }: {
  role: string; user?: { name?: string; email?: string; username?: string }; metrics?: Metric[]; quickLinks?: QuickLink[];
  serviceCategories?: ServiceCategory[]; wallet?: Wallet | null; tier?: Tier | null; tierLimits?: TierLimit[]; dashboardMessages?: DashboardMessages;
}) {
  const isUser = !['ADMIN', 'STAFF', 'SUPPORT'].includes(role);

  if (isUser) {
    const firstName = (user?.name || 'there').trim().split(/\s+/)[0];
    const visibleCategories = serviceCategories.filter(category => category.services.length > 0);
    const greeting = dashboardMessages.greeting?.message || `Welcome back, ${firstName}`;
    return <main className="min-h-screen bg-slate-50 pb-24 text-slate-900">
      <Head title="Home" />
      <section className="bg-slate-900 px-5 pb-7 pt-5 text-white sm:px-8"><div className="mx-auto max-w-6xl">
        <div className="flex items-center justify-between"><div><p className="text-xs font-medium text-slate-300">{greeting}</p><h1 className="mt-1 text-2xl font-black">{firstName} 👋</h1></div>
          <div className="flex items-center gap-2"><Link href="/notifications" className="flex h-10 w-10 items-center justify-center rounded-full bg-white/10">♧</Link><Link href="/profile" className="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-sm font-black">{firstName.charAt(0).toUpperCase()}</Link></div></div>
        <div className="mt-5 rounded-3xl bg-gradient-to-br from-indigo-600 to-violet-700 p-5 shadow-xl"><div className="flex items-start justify-between"><div>
          <p className="text-xs font-semibold uppercase tracking-wider text-indigo-100">Available balance</p><p className="mt-2 text-3xl font-black tracking-tight">{wallet ? money(wallet.available_minor, wallet.currency) : 'Wallet not funded'}</p>
          {wallet?.held_minor && wallet.held_minor !== '0' && <p className="mt-1 text-xs text-indigo-100">Held: {money(wallet.held_minor, wallet.currency)}</p>}</div>
          <span className="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">{wallet?.status || 'Ready'}</span></div>
          <div className="mt-6 flex gap-3"><Link href="/vtu" className="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-indigo-700">Use services</Link><Link href="/profile" className="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white">Wallet</Link></div>
        </div></div></section>
      <div className="mx-auto max-w-6xl px-4 sm:px-8">
        <section className="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><div className="grid grid-cols-4 gap-2">{[['Services','/vtu','✦'],['Notifications','/notifications','♧'],['Support','/support','?'],['Profile','/profile','●']].map(([label,url,icon]) => <Link key={label} href={url} className="flex flex-col items-center gap-2 rounded-2xl px-2 py-3 text-center hover:bg-slate-50"><span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-lg font-black text-indigo-700">{icon}</span><span className="text-xs font-semibold text-slate-700">{label}</span></Link>)}</div></section>
        <section className="mt-6 grid gap-4 md:grid-cols-2">{dashboardMessages.quote?.message && <article className="rounded-3xl bg-slate-900 p-5 text-white shadow-sm"><p className="text-xs font-bold uppercase tracking-wider text-slate-400">Daily inspiration</p><p className="mt-2 text-lg font-bold leading-relaxed">“{dashboardMessages.quote.message}”</p></article>}{dashboardMessages.seasonal?.message && <article className="rounded-3xl bg-amber-50 p-5 text-slate-900 ring-1 ring-amber-100"><p className="text-xs font-bold uppercase tracking-wider text-amber-700">Seasonal message</p><p className="mt-2 text-lg font-bold">{dashboardMessages.seasonal.message}</p></article>}</section>{dashboardMessages.promotional?.message && <section className="mt-4 rounded-3xl bg-indigo-600 p-5 text-white shadow-sm"><p className="text-xs font-bold uppercase tracking-wider text-indigo-200">Special for you</p><p className="mt-2 text-lg font-black">{dashboardMessages.promotional.message}</p></section>}<section className="mt-6 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
          <div className="flex items-start justify-between gap-4">
            <div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Account level</p><h2 className="mt-1 text-2xl font-black">{tier?.name || 'Tier 1'}</h2><p className="mt-1 text-sm text-slate-500">Higher tiers unlock higher transaction capacity after the required verification.</p></div>
            {tier && tier.id < 3 && <Link href="/support?subject=Tier%20Upgrade" className="shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">{tierLimits.find(item => item.id === tier.id)?.upgradeLabel || 'Upgrade tier'}</Link>}
          </div>
          <div className="mt-5 grid gap-3 md:grid-cols-3">
            {tierLimits.map(item => <article key={item.id} className={`rounded-2xl border p-4 ${tier?.id === item.id ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 bg-slate-50'}`}>
              <div className="flex items-center justify-between"><span className="font-black">{item.name}</span>{tier?.id === item.id && <span className="rounded-full bg-indigo-600 px-2.5 py-1 text-[10px] font-bold text-white">CURRENT</span>}</div>
              <div className="mt-4 grid grid-cols-2 gap-3"><div><p className="text-[11px] font-semibold uppercase text-slate-500">Daily limit</p><p className="mt-1 font-extrabold">{limitMoney(item.dailyLimitMinor)}</p></div><div><p className="text-[11px] font-semibold uppercase text-slate-500">Overall balance</p><p className="mt-1 font-extrabold">{limitMoney(item.balanceLimitMinor)}</p></div></div>
            </article>)}
          </div>
          <p className="mt-4 text-[11px] text-slate-400">Limits are configurable in Core and should be aligned with the applicable regulatory/product rules before production financial activation.</p>
        </section>
        <section className="mt-6"><div className="flex items-end justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Services</p><h2 className="mt-1 text-2xl font-black">What do you need today?</h2></div><Link href="/vtu" className="text-sm font-bold text-indigo-700">View all</Link></div>
          {visibleCategories.length === 0 ? <div className="mt-5 rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center"><div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl">✦</div><h3 className="mt-3 font-bold">Services are being prepared</h3><p className="mt-1 text-sm text-slate-500">Available services will appear here automatically when enabled in the catalogue.</p></div> :
          <div className="mt-5 space-y-6">{visibleCategories.map(category => <section key={category.key} className="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div className="mb-4 flex items-center justify-between"><div><h3 className="font-extrabold">{category.name}</h3>{category.description && <p className="mt-0.5 text-xs text-slate-500">{category.description}</p>}</div><Link href={'/vtu?category=' + encodeURIComponent(category.key)} className="text-xs font-bold text-indigo-700">See all</Link></div>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">{category.services.slice(0, 12).map(service => <Link key={service.key} href={service.url} className="group rounded-2xl border border-slate-100 p-3 transition hover:-translate-y-0.5 hover:border-indigo-100 hover:bg-indigo-50/40"><span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-lg font-black text-indigo-700 group-hover:bg-white">{iconFor(service.name)}</span><p className="mt-2 line-clamp-2 text-sm font-bold">{service.name}</p>{service.description && <p className="mt-1 line-clamp-1 text-[11px] text-slate-400">{service.description}</p>}</Link>)}</div>
          </section>)}</div>}
        </section>
        <section className="mt-6 grid gap-4 md:grid-cols-2"><Link href="/notifications" className="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p className="text-xs font-bold uppercase tracking-wider text-slate-400">Stay updated</p><h3 className="mt-2 text-lg font-black">Notifications & updates</h3><p className="mt-1 text-sm text-slate-500">View account and service notifications.</p></Link><Link href="/support" className="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p className="text-xs font-bold uppercase tracking-wider text-slate-400">Need help?</p><h3 className="mt-2 text-lg font-black">Contact support</h3><p className="mt-1 text-sm text-slate-500">Open or follow up on a support ticket.</p></Link></section>
      </div>
      <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-3 py-2 backdrop-blur"><div className="mx-auto grid max-w-lg grid-cols-4">{[['Home','/dashboard','⌂'],['Services','/vtu','✦'],['Notifications','/notifications','♧'],['Profile','/profile','●']].map(([label,url,icon]) => <Link key={label} href={url} className="flex flex-col items-center gap-1 py-1 text-[11px] font-semibold text-slate-600"><span className="text-lg">{icon}</span><span>{label}</span></Link>)}</div></nav>
    </main>;
  }

  return <main className="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-8"><Head title="Dashboard" /><div className="mx-auto max-w-7xl">
    <header className="relative rounded-3xl bg-slate-900 p-6 text-white shadow-xl"><button type="button" onClick={() => router.post('/logout')} className="absolute right-5 top-5 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold hover:bg-white/20">Logout</button><p className="text-xs font-semibold uppercase tracking-wider text-slate-300">SEMIZZY ONE</p><h1 className="mt-2 text-3xl font-black">Dashboard</h1><p className="mt-2 text-sm text-slate-300">Role: {role}</p></header>
    {metrics.length > 0 && <section className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{metrics.map(metric => <article key={metric.label} className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{metric.label}</p><p className="mt-2 text-3xl font-black">{metric.value}</p><p className="mt-1 text-sm text-slate-500">{metric.description}</p></article>)}</section>}
    <section className="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><div className="flex items-center justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Workspace</p><h2 className="mt-1 text-xl font-black">Quick actions</h2></div><Link href="/profile" className="text-sm font-bold text-indigo-700">Profile</Link></div><div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{quickLinks.map(link => <Link key={link.url + link.label} href={link.url} className="rounded-2xl border border-slate-200 p-4 font-semibold hover:border-indigo-200 hover:bg-indigo-50">{link.label}</Link>)}</div></section>
  </div></main>;
}