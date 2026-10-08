import React from 'react';

type Quote = { last_price?: number|string|null; currency?: string|null; observed_at?: string|null; source?: string|null };
type Security = { id:number; symbol:string; name:string; asset_type:string; market?:string|null; exchange?:string|null; currency?:string|null; quotes?:Quote[] };

export default function Market({ securities }: { securities?: { data?: Security[] } | Security[] }) {
  const rows = Array.isArray(securities) ? securities : (securities?.data ?? []);
  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Stocks &amp; Investments Marketplace</h1>
        <p className="text-sm opacity-70">Browse published investment securities. Prices are shown only when supplied by an approved market-data source.</p>
      </div>
      {rows.length === 0 ? (
        <div className="rounded-xl border p-6 opacity-70">No published securities are available yet.</div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {rows.map((security) => {
            const quote = security.quotes?.[0];
            return (
              <article key={security.id} className="rounded-xl border p-4 space-y-2">
                <div className="flex items-start justify-between gap-3">
                  <div><h2 className="font-semibold">{security.name}</h2><p className="text-sm opacity-70">{security.symbol}</p></div>
                  <span className="rounded-full border px-2 py-1 text-xs">{security.asset_type}</span>
                </div>
                <p className="text-sm">{security.exchange || security.market || 'Market not specified'}</p>
                <div className="text-lg font-semibold">
                  {quote?.last_price != null ? `${quote.currency || security.currency || 'NGN'} ${Number(quote.last_price).toLocaleString()}` : 'Price unavailable'}
                </div>
                <p className="text-xs opacity-60">
                  {quote?.observed_at ? `Observed ${new Date(quote.observed_at).toLocaleString()}` : 'No approved quote available'}
                  {quote?.source ? ` • Source: ${quote.source}` : ''}
                </p>
              </article>
            );
          })}
        </div>
      )}
    </div>
  );
}
