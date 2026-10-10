import { Head, Link, usePage } from '@inertiajs/react';

type Wallet = { availableMinor: string; currency: string; status: string } | null;
type FundingMethod = { key: string; name: string; description: string; available: boolean };
type Props = { wallet: Wallet; methods: FundingMethod[]; unreadCount?: number };
type SharedProps = { navigation?: { unreadNotifications?: number } };

const money = (minor: string, currency = 'NGN') => {
  try {
    const value = BigInt(minor);
    const major = value / 100n;
    const cents = (value % 100n).toString().padStart(2, '0');
    return (currency === 'NGN' ? '₦' : currency + ' ') + major.toLocaleString() + '.' + cents;
  } catch { return '—'; }
};

export default function WalletFunding({ wallet, methods, unreadCount }: Props) {
  const shared = usePage<SharedProps>().props;
  const badge = unreadCount ?? shared.navigation?.unreadNotifications ?? 0;

  return <main className="min-h-screen bg-slate-50 pb-24 text-slate-900">
    <Head title="Add Money" />
    <div className="mx-auto max-w-3xl px-4 py-5 sm:px-8">
      <header className="flex items-center justify-between">
        <div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">SEMIZZY ONE</p><h1 className="mt-1 text-2xl font-black">Add Money</h1><p className="mt-1 text-sm text-slate-500">Choose an available wallet funding method.</p></div>
        <Link href="/dashboard" className="rounded-xl bg-white px-3 py-2 text-sm font-bold ring-1 ring-slate-200">Home</Link>
      </header>

      <section className="mt-5 rounded-3xl bg-slate-900 p-5 text-white shadow-sm">
        <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Available balance</p>
        <p className="mt-2 text-3xl font-black">{wallet ? money(wallet.availableMinor, wallet.currency) : '—'}</p>
        <p className="mt-1 text-xs text-slate-400">Wallet status: {wallet?.status || 'Not created'}</p>
      </section>

      <section className="mt-5">
        <div className="flex items-end justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Funding methods</p><h2 className="mt-1 text-xl font-black">How do you want to add money?</h2></div><Link href="/transactions" className="text-sm font-bold text-indigo-700">History</Link></div>
        {methods.length === 0 ? <div className="mt-4 rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center"><div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl">₦</div><h3 className="mt-3 font-black">No funding method is configured</h3><p className="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500">A payment channel must be configured and enabled by the platform before users can fund their wallet. No funding success is simulated here.</p></div> :
          <div className="mt-4 space-y-3">{methods.map(method => <article key={method.key} className="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div className="flex items-start justify-between gap-4"><div><h3 className="font-black">{method.name}</h3><p className="mt-1 text-sm text-slate-500">{method.description}</p></div><span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{method.available ? 'Available' : 'Unavailable'}</span></div></article>)}</div>}
      </section>
    </div>

  </main>;
}
