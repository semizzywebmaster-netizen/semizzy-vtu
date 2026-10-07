import { Head } from '@inertiajs/react';
type Product={id:number;name:string;description?:string;price_minor:string;currency:string;stock_quantity:string};
export default function Index({products}:{products:{data:Product[]}}){
 return <><Head title="Marketplace"/><div className="p-6 space-y-6"><h1 className="text-2xl font-bold">Marketplace</h1><div className="grid gap-4 md:grid-cols-3">{products.data.map(p=><article key={p.id} className="rounded-xl border p-4"><h2 className="font-semibold">{p.name}</h2><p className="text-sm opacity-70">{p.description}</p><p className="mt-3 font-bold">{p.currency} {(Number(p.price_minor)/100).toFixed(2)}</p><p className="text-xs opacity-60">Stock: {p.stock_quantity}</p></article>)}</div></div></>;
}