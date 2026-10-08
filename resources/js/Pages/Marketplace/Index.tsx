import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Category = {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  description?: string | null;
  product_type: 'physical' | 'digital' | 'service';
  listing_type?: string | null;
  children?: Category[];
};

type Product = {
  id: number;
  name: string;
  description?: string;
  price_minor: string;
  currency: string;
  stock_quantity: string;
  product_type?: 'physical' | 'digital' | 'service';
  condition?: string | null;
  category?: { id: number; name: string; slug: string; icon?: string | null };
  listing_type?: string | null;
};

const typeLabels: Record<string, string> = {
  physical: 'Physical',
  digital: 'Digital',
  service: 'Service',
};

function formatMoney(minor: string, currency: string) {
  const value = Number(minor || 0) / 100;
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: currency || 'NGN', maximumFractionDigits: 2 }).format(value);
}

export default function Index({
  products,
  categories = [],
}: {
  products: { data: Product[] };
  categories?: Category[];
}) {
  const [query, setQuery] = useState('');
  const [type, setType] = useState('all');
  const [category, setCategory] = useState('all');

  const allCategories = useMemo(() => {
    const flatten = (items: Category[]): Category[] => items.flatMap((item) => [item, ...(item.children ? flatten(item.children) : [])]);
    return flatten(categories);
  }, [categories]);

  const selectedCategory = allCategories.find((item) => String(item.id) === category);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return products.data.filter((product) => {
      const matchesQuery = !q || product.name.toLowerCase().includes(q) || (product.description || '').toLowerCase().includes(q);
      const matchesType = type === 'all' || product.product_type === type;
      const selectedIds = category === 'all'
        ? null
        : (() => {
            const selected = allCategories.find((item) => String(item.id) === category);
            return selected ? [selected.id, ...(selected.children || []).map((child) => child.id)] : [];
          })();
      const matchesCategory = selectedIds === null || selectedIds.includes(product.category?.id || -1);
      return matchesQuery && matchesType && matchesCategory;
    });
  }, [products.data, query, type, category, allCategories]);

  return (
    <>
      <Head title="Marketplace" />
      <div className="min-h-screen bg-slate-50 p-4 text-slate-900 md:p-6">
        <div className="mx-auto max-w-7xl space-y-6">
          <section className="rounded-3xl bg-slate-950 p-6 text-white shadow-sm md:p-8">
            <div className="max-w-3xl">
              <p className="text-sm font-semibold uppercase tracking-wider text-white/60">SEMIZZY ONE Marketplace</p>
              <h1 className="mt-2 text-3xl font-bold md:text-4xl">Buy, sell, rent, download or hire.</h1>
              <p className="mt-3 text-sm leading-6 text-white/70 md:text-base">
                One marketplace for new and used physical items, real estate rentals and sales, digital products, and professional services.
              </p>
            </div>
            <div className="mt-6 flex flex-col gap-3 md:flex-row">
              <input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search products, property, services..."
                className="min-h-12 flex-1 rounded-2xl border border-white/10 bg-white px-4 text-slate-900 outline-none ring-0 placeholder:text-slate-400"
              />
              <select
                value={type}
                onChange={(event) => setType(event.target.value)}
                className="min-h-12 rounded-2xl border border-white/10 bg-white px-4 text-slate-900"
              >
                <option value="all">All listing types</option>
                <option value="physical">Physical products</option>
                <option value="digital">Digital products</option>
                <option value="service">Services</option>
              </select>
            </div>
          </section>

          <section>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-lg font-bold">Browse categories</h2>
              <span className="text-xs text-slate-500">{allCategories.length} categories</span>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
              <button
                onClick={() => setCategory('all')}
                className={`rounded-2xl border p-4 text-left transition ${category === 'all' ? 'border-slate-950 bg-slate-950 text-white' : 'bg-white hover:border-slate-400'}`}
              >
                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-700">ALL</div>
                <div className="font-semibold">All categories</div>
              </button>
              {categories.map((item) => (
                <button
                  key={item.id}
                  onClick={() => setCategory(String(item.id))}
                  className={`rounded-2xl border p-4 text-left transition ${category === String(item.id) ? 'border-slate-950 bg-slate-950 text-white' : 'bg-white hover:border-slate-400'}`}
                >
                  <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-[10px] font-bold uppercase text-slate-700">
                    {(item.icon || item.name).replaceAll('-', ' ').slice(0, 8)}
                  </div>
                  <div className="font-semibold">{item.name}</div>
                  <div className="mt-1 text-xs opacity-60">{typeLabels[item.product_type]}</div>
                </button>
              ))}
            </div>
          </section>

          {selectedCategory?.children?.length ? (
            <section className="rounded-2xl border bg-white p-4">
              <div className="mb-3 flex items-center justify-between">
                <h2 className="font-bold">{selectedCategory.name} subcategories</h2>
                <span className="text-xs text-slate-500">Select a subcategory</span>
              </div>
              <div className="flex flex-wrap gap-2">
                {selectedCategory.children.map((child) => (
                  <button
                    key={child.id}
                    onClick={() => setCategory(String(child.id))}
                    className="rounded-full border px-4 py-2 text-sm hover:bg-slate-50"
                  >
                    {child.name}
                  </button>
                ))}
              </div>
            </section>
          ) : null}

          <section>
            <div className="mb-3 flex items-center justify-between">
              <div>
                <h2 className="text-lg font-bold">Latest listings</h2>
                <p className="text-sm text-slate-500">{filtered.length} listing{filtered.length === 1 ? '' : 's'}</p>
              </div>
            </div>
            {filtered.length === 0 ? (
              <div className="rounded-2xl border bg-white p-10 text-center text-sm text-slate-500">
                No listings match your current filters.
              </div>
            ) : (
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {filtered.map((product) => (
                  <article key={product.id} className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div className="flex h-32 items-center justify-center bg-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-400">
                      {product.product_type ? typeLabels[product.product_type] : 'Marketplace'}
                    </div>
                    <div className="space-y-3 p-4">
                      <div className="flex items-start justify-between gap-3">
                        <h3 className="font-semibold leading-5">{product.name}</h3>
                        {product.condition ? <span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] uppercase">{product.condition.replaceAll('_', ' ')}</span> : null}
                      </div>
                      <p className="line-clamp-2 text-sm text-slate-500">{product.description || 'No description provided.'}</p>
                      <div className="font-bold">{formatMoney(product.price_minor, product.currency)}</div>
                      <div className="flex items-center justify-between text-xs text-slate-500">
                        <span>{product.category?.name || 'Uncategorised'}</span>
                        {product.product_type === 'physical' ? <span>{product.stock_quantity} available</span> : <span>{typeLabels[product.product_type || 'physical']}</span>}
                      </div>
                    </div>
                  </article>
                ))}
              </div>
            )}
          </section>
        </div>
      </div>
    </>
  );
}
