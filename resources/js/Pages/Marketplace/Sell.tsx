import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type Category = {
  id: number;
  parent_id?: number | null;
  name: string;
  slug: string;
  icon?: string | null;
  product_type: 'physical' | 'digital' | 'service';
  listing_type?: string | null;
  attribute_schema?: Record<string, Field> | null;
};

type Field = {
  type?: 'string' | 'integer' | 'boolean' | 'date' | 'select';
  required?: boolean;
  options?: string[];
  min?: number;
  max?: number;
};

type CategoryResponse = {
  success: boolean;
  category: Category & { attribute_schema: Record<string, Field> };
};

function csrfToken() {
  const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
  return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';
}

function label(key: string) {
  return key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

export default function Sell() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoryId, setCategoryId] = useState('');
  const [schema, setSchema] = useState<Record<string, Field>>({});
  const [productType, setProductType] = useState<'physical' | 'digital' | 'service'>('physical');
  const [attributes, setAttributes] = useState<Record<string, unknown>>({});
  const [form, setForm] = useState({
    name: '',
    description: '',
    price: '',
    stock: '1',
    condition: 'new',
    delivery_type: 'seller_fulfilled',
    requires_shipping: true,
    download_limit: '',
    service_delivery_days: '',
    service_model: 'fixed',
    video_url: '',
  });
  const [message, setMessage] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetch('/marketplace/seller/categories', { credentials: 'same-origin' })
      .then(async (response) => {
        if (!response.ok) throw new Error('Unable to load marketplace categories.');
        const payload = await response.json() as { categories: Category[] };
        setCategories(payload.categories || []);
      })
      .catch((error: Error) => setMessage(error.message));
  }, []);

  const roots = useMemo(() => categories.filter((item) => !item.parent_id), [categories]);
  const children = useMemo(
    () => categories.filter((item) => item.parent_id === Number(categoryId)),
    [categories, categoryId],
  );
  const selected = categories.find((item) => item.id === Number(categoryId));

  const loadCategory = async (id: string) => {
    setCategoryId(id);
    setAttributes({});
    if (!id) {
      setSchema({});
      return;
    }
    const response = await fetch('/marketplace/categories/' + id + '/form', { credentials: 'same-origin' });
    const payload = await response.json() as CategoryResponse;
    if (!response.ok || !payload.success) {
      setMessage('Unable to load this category.');
      return;
    }
    setSchema(payload.category.attribute_schema || {});
    setProductType(payload.category.product_type);
    setForm((current) => ({
      ...current,
      condition: payload.category.product_type === 'physical' ? current.condition : 'new',
      delivery_type: payload.category.product_type === 'digital' ? 'download' : payload.category.product_type === 'service' ? 'service_delivery' : 'seller_fulfilled',
      requires_shipping: payload.category.product_type === 'physical',
    }));
  };

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setMessage('');
    if (!selected) {
      setMessage('Select a category before publishing.');
      return;
    }
    setSaving(true);
    const body = {
      name: form.name,
      description: form.description || null,
      category_id: selected.id,
      product_type: productType,
      condition: productType === 'physical' ? form.condition : null,
      delivery_type: form.delivery_type,
      requires_shipping: productType === 'physical' ? form.requires_shipping : false,
      download_limit: productType === 'digital' && form.download_limit ? Number(form.download_limit) : null,
      service_delivery_days: productType === 'service' && form.service_delivery_days ? Number(form.service_delivery_days) : null,
      service_model: productType === 'service' ? form.service_model : null,
      video_url: form.video_url.trim() || null,
      price_minor: String(Math.round(Number(form.price || 0) * 100)),
      stock_quantity: productType === 'physical' ? form.stock : '0',
      currency: 'NGN',
      attributes,
      status: 'active',
    };
    try {
      const response = await fetch('/marketplace/products', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
      });
      const payload = await response.json() as { success?: boolean; message?: string };
      if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to publish listing.');
      window.location.href = '/marketplace';
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Unable to publish listing.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <Head title="Sell on Marketplace" />
      <div className="min-h-screen bg-slate-50 p-4 text-slate-900 md:p-6">
        <div className="mx-auto max-w-4xl">
          <div className="mb-6">
            <p className="text-sm font-semibold uppercase tracking-wider text-slate-500">Marketplace Seller</p>
            <h1 className="mt-1 text-3xl font-bold">Create a listing</h1>
            <p className="mt-2 text-sm text-slate-500">Choose a category first. The required fields change automatically for that category.</p>
          </div>

          <form onSubmit={submit} className="space-y-5">
            <section className="rounded-2xl border bg-white p-5 shadow-sm">
              <h2 className="font-bold">Category</h2>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <select value={categoryId} onChange={(event) => loadCategory(event.target.value)} className="min-h-12 rounded-xl border px-3">
                  <option value="">Select a category</option>
                  {roots.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
                {children.length > 0 ? (
                  <select value={categoryId} onChange={(event) => loadCategory(event.target.value)} className="min-h-12 rounded-xl border px-3">
                    <option value="">Select a subcategory</option>
                    {children.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                  </select>
                ) : (
                  <div className="flex min-h-12 items-center rounded-xl bg-slate-50 px-3 text-sm text-slate-600">
                    {selected ? selected.name : 'Select a parent category first'}
                  </div>
                )}
              </div>
              {selected ? <p className="mt-3 text-xs text-slate-500">Listing type: <b>{productType}</b>{selected.listing_type ? ` · ${selected.listing_type}` : ''}</p> : null}
            </section>

            <section className="rounded-2xl border bg-white p-5 shadow-sm">
              <h2 className="font-bold">Basic listing information</h2>
              <div className="mt-4 grid gap-4">
                <input required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Listing title" className="min-h-12 rounded-xl border px-3" />
                <textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder="Describe what you are selling or offering" className="min-h-32 rounded-xl border p-3" />
                <div className="grid gap-4 md:grid-cols-2">
                  <label className="text-sm font-medium">Price (NGN)
                    <input required min="0.01" step="0.01" type="number" value={form.price} onChange={(event) => setForm({ ...form, price: event.target.value })} className="mt-2 min-h-12 w-full rounded-xl border px-3" />
                  </label>
                  <section className="rounded-2xl border bg-white p-5 shadow-sm">
              <h2 className="font-bold">Product/item video (optional)</h2>
              <p className="mt-2 text-sm text-slate-500">Paste an external HTTPS video link. YouTube and Vimeo links will be embedded as a player; direct MP4/WebM/OGG links use the browser video player.</p>
              <input
                type="url"
                value={form.video_url}
                onChange={(event) => setForm({ ...form, video_url: event.target.value })}
                placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/..."
                className="mt-4 min-h-12 w-full rounded-xl border px-3"
              />
              <p className="mt-2 text-xs text-slate-400">For safety and reliability, only HTTPS links are accepted. The marketplace does not upload or host seller videos.</p>
            </section>

            {productType === 'physical' ? (
                    <label className="text-sm font-medium">Stock quantity
                      <input required min="0" type="number" value={form.stock} onChange={(event) => setForm({ ...form, stock: event.target.value })} className="mt-2 min-h-12 w-full rounded-xl border px-3" />
                    </label>
                  ) : null}
                </div>
              </div>
            </section>

            {productType === 'physical' ? (
              <section className="rounded-2xl border bg-white p-5 shadow-sm">
                <h2 className="font-bold">Physical item</h2>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  <select value={form.condition} onChange={(event) => setForm({ ...form, condition: event.target.value })} className="min-h-12 rounded-xl border px-3">
                    <option value="new">New</option><option value="used">Used</option><option value="refurbished">Refurbished</option><option value="open_box">Open box</option><option value="like_new">Like new</option><option value="pre_owned">Pre-owned</option><option value="for_parts">For parts</option>
                  </select>
                  <label className="flex items-center gap-3 rounded-xl border px-3 text-sm">
                    <input type="checkbox" checked={form.requires_shipping} onChange={(event) => setForm({ ...form, requires_shipping: event.target.checked })} />
                    Requires delivery/shipping
                  </label>
                </div>
                {form.condition !== 'new' ? (
                  <input required value={String(attributes.condition_notes || '')} onChange={(event) => setAttributes({ ...attributes, condition_notes: event.target.value })} placeholder="Condition notes (required for used/non-new items)" className="mt-4 min-h-12 w-full rounded-xl border px-3" />
                ) : null}
              </section>
            ) : null}

            {productType === 'digital' ? (
              <section className="rounded-2xl border bg-white p-5 shadow-sm">
                <h2 className="font-bold">Digital delivery</h2>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  <select value={form.delivery_type} onChange={(event) => setForm({ ...form, delivery_type: event.target.value })} className="min-h-12 rounded-xl border px-3"><option value="download">Download</option><option value="license_key">License key</option></select>
                  <input min="1" type="number" value={form.download_limit} onChange={(event) => setForm({ ...form, download_limit: event.target.value })} placeholder="Download limit (optional)" className="min-h-12 rounded-xl border px-3" />
                </div>
                <p className="mt-3 text-xs text-slate-500">Digital files/assets are attached after the listing is created through the protected digital-asset flow.</p>
              </section>
            ) : null}

            {productType === 'service' ? (
              <section className="rounded-2xl border bg-white p-5 shadow-sm">
                <h2 className="font-bold">Service delivery</h2>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  <select value={form.service_model} onChange={(event) => setForm({ ...form, service_model: event.target.value })} className="min-h-12 rounded-xl border px-3"><option value="fixed">Fixed price</option><option value="hourly">Hourly</option><option value="custom">Custom quote</option><option value="milestone">Milestone</option></select>
                  <input min="1" type="number" value={form.service_delivery_days} onChange={(event) => setForm({ ...form, service_delivery_days: event.target.value })} placeholder="Delivery days" className="min-h-12 rounded-xl border px-3" />
                </div>
              </section>
            ) : null}

            {Object.keys(schema).length > 0 ? (
              <section className="rounded-2xl border bg-white p-5 shadow-sm">
                <h2 className="font-bold">{selected?.name} details</h2>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  {Object.entries(schema).map(([key, field]) => {
                    const value = attributes[key];
                    if (field.type === 'boolean') {
                      return <label key={key} className="flex items-center gap-3 rounded-xl border px-3 py-3 text-sm"><input type="checkbox" checked={Boolean(value)} onChange={(event) => setAttributes({ ...attributes, [key]: event.target.checked })} />{label(key)}{field.required ? ' *' : ''}</label>;
                    }
                    if (field.type === 'select') {
                      return <label key={key} className="text-sm font-medium">{label(key)}{field.required ? ' *' : ''}<select required={field.required} value={String(value ?? '')} onChange={(event) => setAttributes({ ...attributes, [key]: event.target.value })} className="mt-2 min-h-12 w-full rounded-xl border px-3"><option value="">Select</option>{(field.options || []).map((option) => <option key={option} value={option}>{label(option)}</option>)}</select></label>;
                    }
                    return <label key={key} className="text-sm font-medium">{label(key)}{field.required ? ' *' : ''}<input required={field.required} type={field.type === 'integer' ? 'number' : field.type === 'date' ? 'date' : 'text'} min={field.min} max={field.max} value={String(value ?? '')} onChange={(event) => setAttributes({ ...attributes, [key]: field.type === 'integer' ? Number(event.target.value) : event.target.value })} className="mt-2 min-h-12 w-full rounded-xl border px-3" /></label>;
                  })}
                </div>
              </section>
            ) : null}

            {message ? <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{message}</div> : null}
            <button disabled={saving || !selected} className="min-h-12 w-full rounded-xl bg-slate-950 px-5 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
              {saving ? 'Publishing...' : 'Publish listing'}
            </button>
          </form>
        </div>
      </div>
    </>
  );
}
