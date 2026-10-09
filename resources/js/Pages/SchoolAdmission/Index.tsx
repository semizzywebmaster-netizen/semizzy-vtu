import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

type Product = {
  id: number; key: string; name: string; description: string | null; service_type: string;
  currency: string; price_minor: number; institution?: { id: number; name: string; slug: string; state?: string | null } | null;
  programme?: { id: number; name: string; level: string } | null;
};
export default function SchoolAdmissionIndex({ products }: { products: Product[] }) {
  const money = (amount: number, currency: string) => new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(amount / 100);
  return <AppLayout><Head title="School Admission" /><div className="mx-auto max-w-6xl space-y-6 p-4 md:p-8">
    <div><p className="text-sm font-medium text-blue-600">Education services</p><h1 className="text-2xl font-bold">School Admission</h1><p className="mt-1 text-sm text-gray-500">Browse available admission forms, screening services and acceptance-fee products.</p></div>
    {products.length === 0 ? <div className="rounded-xl border p-8 text-center"><h2 className="font-semibold">No admission services available yet</h2><p className="mt-2 text-sm text-gray-500">Services will appear here when an administrator activates verified products.</p></div> : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{products.map(product => <article key={product.id} className="rounded-xl border bg-white p-5 shadow-sm">
      <p className="text-xs uppercase tracking-wide text-gray-500">{product.service_type.replaceAll('_',' ')}</p><h2 className="mt-2 font-semibold">{product.name}</h2><p className="mt-1 text-sm text-gray-600">{product.institution?.name ?? 'General admission service'}</p>{product.programme && <p className="mt-1 text-sm text-gray-500">{product.programme.name}</p>}{product.description && <p className="mt-3 text-sm text-gray-600">{product.description}</p>}
      <div className="mt-5 flex items-center justify-between"><strong>{money(product.price_minor, product.currency)}</strong><span className="rounded-full bg-gray-100 px-3 py-1 text-xs">Available</span></div>
    </article>)}</div>}
  </div></AppLayout>;
}