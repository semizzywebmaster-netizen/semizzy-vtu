import { Head, Link } from '@inertiajs/react';

type Transaction = {
  id: number;
  reference: string;
  type: string;
  amountMinor: string;
  currency: string;
  availableAfterMinor: string;
  createdAt: string | null;
  metadata?: Record<string, unknown> | null;
};

type Props = { transactions: Transaction[]; unreadCount?: number };

const money = (minor: string, currency = 'NGN') => {
  try {
    const value = BigInt(minor);
    const major = value / 100n;
    const cents = (value % 100n).toString().padStart(2, '0');
    return (currency === 'NGN' ? '₦' : currency + ' ') + major.toLocaleString() + '.' + cents;
  } catch { return '—'; }
};

const label = (type: string) => type.replace(/[_:-]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

export default function Transactions({ transactions, unreadCount }: Props) {

  return <main className="min-h-screen bg-slate-50 pb-24 text-slate-900">
    <Head title="Transactions" />
    <div className="mx-auto max-w-3xl px-4 py-5 sm:px-8">
      <header className="flex items-center justify-between">
        <div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">SEMIZZY ONE</p><h1 className="mt-1 text-2xl font-black">Transaction History</h1><p className="mt-1 text-sm text-slate-500">Your wallet activity, newest first.</p></div>
        <Link href="/dashboard" className="rounded-xl bg-white px-3 py-2 text-sm font-bold ring-1 ring-slate-200">Home</Link>
      </header>

      <section className="mt-5 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
        {transactions.length === 0 ? <div className="p-10 text-center"><div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl">↔</div><h2 className="mt-4 font-black">No transactions yet</h2><p className="mt-1 text-sm text-slate-500">Completed wallet activity will appear here when it is recorded.</p></div> :
          <div className="divide-y divide-slate-100">{transactions.map(item => {
            const positive = ['admin_fund','funding','wallet_funding','deposit','credit','refund'].some(key => item.type.toLowerCase().includes(key));
            return <article key={item.id} className="flex items-center justify-between gap-4 p-4">
              <div className="min-w-0"><p className="truncate font-bold">{label(item.type)}</p><p className="mt-1 truncate text-xs text-slate-500">{item.reference}</p><time className="mt-1 block text-xs text-slate-400" dateTime={item.createdAt || undefined}>{item.createdAt ? new Date(item.createdAt).toLocaleString() : '—'}</time></div>
              <div className="shrink-0 text-right"><p className={'font-black ' + (positive ? 'text-emerald-600' : 'text-slate-900')}>{positive ? '+' : '−'}{money(item.amountMinor, item.currency)}</p><p className="mt-1 text-[11px] text-slate-400">Balance {money(item.availableAfterMinor, item.currency)}</p><Link href={`/transactions/${item.id}/receipt`} className="mt-2 inline-block text-xs font-bold text-indigo-600">View Receipt</Link></div>
            </article>;
          })}</div>}
      </section>
    </div>

  </main>;
}
