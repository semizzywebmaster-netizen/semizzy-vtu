import { Head } from '@inertiajs/react';

type Props = { accounts?: any; numbers?: any; orders?: any };

export default function Index({ accounts, numbers, orders }: Props) {
  const buy = async (url: string) => {
    await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', Accept: 'application/json' } });
    window.location.reload();
  };
  return <>
    <Head title="Social Services" />
    <main className="space-y-8 p-6">
      <header><h1 className="text-2xl font-bold">Social Services</h1><p className="text-sm opacity-70">Admin-supplied social media accounts and foreign verification numbers.</p></header>
      <section><h2 className="mb-3 text-xl font-semibold">Social Media Accounts</h2><div className="grid gap-4 md:grid-cols-2">{(accounts?.data || []).map((account: any) => <article key={account.id} className="space-y-2 rounded border p-4"><div className="font-semibold">{account.platform} — {account.title}</div><div className="text-sm">Country: {account.country_code || '—'} · Followers: {account.followers ?? '—'} · Age: {account.account_age_days ?? '—'} days</div><div>{account.currency} {account.price}</div><button onClick={() => buy('/social-services/accounts/' + account.id + '/buy')} className="rounded border px-3 py-2">Purchase</button></article>)}</div></section>
      <section><h2 className="mb-3 text-xl font-semibold">Foreign Verification Numbers</h2><div className="grid gap-4 md:grid-cols-2">{(numbers?.data || []).map((number: any) => <article key={number.id} className="space-y-2 rounded border p-4"><div className="font-semibold">{number.country_name} ({number.country_code})</div><div className="text-sm">Service: {number.service_key || 'General'} · {number.fulfillment_mode}</div><div>{number.currency} {number.price}</div><button onClick={() => buy('/social-services/numbers/' + number.id + '/buy')} className="rounded border px-3 py-2">Purchase number</button></article>)}</div></section>
      <section><h2 className="mb-3 text-xl font-semibold">My Orders & SMS Inbox</h2><div className="space-y-3">{(orders?.data || []).map((order: any) => <article key={order.id} className="rounded border p-4"><div className="font-semibold">{order.reference} · {order.order_type}</div><div className="text-sm">Status: {order.status} · {order.currency} {order.amount}</div>{order.order_type === 'number' && <a className="underline" href={'/social-services/numbers/' + order.id + '/sms'}>Open SMS Inbox</a>}</article>)}</div></section>
    </main>
  </>;
}
