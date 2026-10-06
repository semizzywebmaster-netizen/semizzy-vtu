import { Head } from '@inertiajs/react';

type Payment = {
  reference: string; user_id: number; amount_minor: string; currency: string; status: string;
  provider_reference?: string | null; expires_at?: string | null; paid_at?: string | null; created_at?: string | null;
};
type Props = { payments?: { data?: Payment[] } | Payment[] };

export default function Payments({ payments }: Props) {
  const rows = Array.isArray(payments) ? payments : (payments?.data ?? []);
  return (
    <>
      <Head title="Payments & Funding" />
      <main className="min-h-screen bg-slate-50 p-4 md:p-8">
        <div className="mx-auto max-w-7xl">
          <header>
            <p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE · ADDON</p>
            <h1 className="mt-1 text-2xl font-extrabold text-slate-900">Payments & Funding</h1>
            <p className="mt-2 text-sm text-slate-600">Monitor funding intents, provider references and verified wallet credits.</p>
          </header>
          <section className="mt-6 overflow-hidden rounded-2xl border bg-white shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="p-4">Reference</th><th className="p-4">User</th><th className="p-4">Amount</th>
                    <th className="p-4">Status</th><th className="p-4">Provider ref.</th><th className="p-4">Paid</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr><td colSpan={6} className="p-8 text-center text-slate-500">No payment intents yet.</td></tr>
                  ) : (
                    rows.map((p) => (
                      <tr key={p.reference} className="border-t">
                        <td className="p-4 font-mono font-semibold">{p.reference}</td>
                        <td className="p-4">{p.user_id}</td>
                        <td className="p-4">{p.amount_minor} {p.currency}</td>
                        <td className="p-4"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold uppercase">{p.status}</span></td>
                        <td className="p-4 font-mono text-xs">{p.provider_reference || '—'}</td>
                        <td className="p-4">{p.paid_at ? new Date(p.paid_at).toLocaleString() : '—'}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </main>
    </>
  );
}