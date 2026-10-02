import { Head, Link, useForm } from '@inertiajs/react';

type Product={id:number;key:string;name:string;enabled:boolean};
type Service={id:number;key:string;name:string;enabled:boolean;products:Product[]};
type Category={id:number;key:string;name:string;enabled:boolean;services:Service[]};

export default function Catalogue({categories=[]}:{categories:Category[]}) {
  const category=useForm({key:'',name:'',description:'',sort_order:100,enabled:true});
  const service=useForm({category_id:'',key:'',name:'',description:'',enabled:true});
  const product=useForm({service_id:'',key:'',name:'',currency:'NGN',enabled:false});

  return <><Head title="Service Catalogue"/><main className="min-h-screen bg-slate-50 p-6 md:p-10">
    <div className="mx-auto max-w-7xl">
      <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
      <h1 className="mt-3 text-3xl font-extrabold">Service Catalogue</h1>
      <p className="mt-2 text-slate-600">Manage real service categories, services and products. Provider catalogue data is never fabricated.</p>

      <div className="mt-8 grid gap-6 lg:grid-cols-3">
        <form onSubmit={e=>{e.preventDefault();category.post('/admin/catalogue/categories')}} className="rounded-2xl bg-white p-5 shadow-sm">
          <h2 className="font-bold">Create Category</h2>
          <input className="mt-4 w-full rounded-xl border p-3" placeholder="key" value={category.data.key} onChange={e=>category.setData('key',e.target.value)}/>
          <input className="mt-3 w-full rounded-xl border p-3" placeholder="name" value={category.data.name} onChange={e=>category.setData('name',e.target.value)}/>
          <button className="mt-4 rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white" disabled={category.processing}>Create</button>
        </form>

        <form onSubmit={e=>{e.preventDefault();service.post('/admin/catalogue/services')}} className="rounded-2xl bg-white p-5 shadow-sm">
          <h2 className="font-bold">Create Service</h2>
          <select className="mt-4 w-full rounded-xl border p-3" value={service.data.category_id} onChange={e=>service.setData('category_id',e.target.value)}>
            <option value="">Select category</option>{categories.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
          <input className="mt-3 w-full rounded-xl border p-3" placeholder="key" value={service.data.key} onChange={e=>service.setData('key',e.target.value)}/>
          <input className="mt-3 w-full rounded-xl border p-3" placeholder="name" value={service.data.name} onChange={e=>service.setData('name',e.target.value)}/>
          <button className="mt-4 rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white" disabled={service.processing}>Create</button>
        </form>

        <form onSubmit={e=>{e.preventDefault();product.post('/admin/catalogue/products')}} className="rounded-2xl bg-white p-5 shadow-sm">
          <h2 className="font-bold">Create Product</h2>
          <select className="mt-4 w-full rounded-xl border p-3" value={product.data.service_id} onChange={e=>product.setData('service_id',e.target.value)}>
            <option value="">Select service</option>{categories.flatMap(c=>c.services).map(s=><option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
          <input className="mt-3 w-full rounded-xl border p-3" placeholder="key" value={product.data.key} onChange={e=>product.setData('key',e.target.value)}/>
          <input className="mt-3 w-full rounded-xl border p-3" placeholder="name" value={product.data.name} onChange={e=>product.setData('name',e.target.value)}/>
          <button className="mt-4 rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white" disabled={product.processing}>Create</button>
        </form>
      </div>

      <section className="mt-8 space-y-4">{categories.map(c=><article key={c.id} className="rounded-2xl bg-white p-5 shadow-sm">
        <div className="flex items-center justify-between"><h2 className="text-xl font-bold">{c.name}</h2><span className="text-xs text-slate-500">{c.key}</span></div>
        <div className="mt-4 space-y-3">{c.services.map(s=><div key={s.id} className="rounded-xl border p-4">
          <div className="font-semibold">{s.name} <span className="text-xs text-slate-500">({s.key})</span></div>
          {s.products.length>0 ? <ul className="mt-3 space-y-1 text-sm text-slate-600">{s.products.map(p=><li key={p.id}>{p.name} — {p.key} — {p.enabled?'enabled':'disabled'}</li>)}</ul> : <p className="mt-2 text-sm text-slate-500">No products yet.</p>}
        </div>)}</div>
      </article>)}</section>
    </div>
  </main></>;
}
