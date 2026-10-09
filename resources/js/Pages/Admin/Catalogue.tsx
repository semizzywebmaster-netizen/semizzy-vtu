import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import ServiceIcon, { SERVICE_ICONS, iconForService } from '../../Components/ServiceIcon';

type Product={id:number;key:string;name:string;enabled:boolean};
type Service={id:number;key:string;name:string;enabled:boolean;metadata?:Record<string,any>|null;products:Product[]};
type Category={id:number;key:string;name:string;enabled:boolean;services:Service[]};

export default function Catalogue({categories=[],search=''}:{categories:Category[];search?:string}) {
  const category=useForm({key:'',name:'',description:'',sort_order:100,enabled:true});
  const service=useForm({category_id:'',key:'',name:'',description:'',icon:'',enabled:true});
  const product=useForm({service_id:'',key:'',name:'',currency:'NGN',enabled:false});
  const iconUpload=useForm<{icon:File|null}>({icon:null});
  const [catalogueSearch,setCatalogueSearch]=useState(search);
  const [iconQuery,setIconQuery]=useState('');
  const [iconService,setIconService]=useState<Service|null>(null);

  useEffect(() => setCatalogueSearch(search), [search]);

  const filteredCategories = useMemo(() => {
    const term = catalogueSearch.trim().toLowerCase();
    if (!term) return categories;
    return categories.map((category) => {
      const categoryMatches = (category.name + ' ' + category.key).toLowerCase().includes(term);
      const services = category.services.map((item) => {
        const serviceMatches = (item.name + ' ' + item.key).toLowerCase().includes(term);
        const matchingProducts = item.products.filter((entry) => (entry.name + ' ' + entry.key).toLowerCase().includes(term));
        return { ...item, products: categoryMatches || serviceMatches ? item.products : matchingProducts };
      }).filter((item) => categoryMatches || (item.name + ' ' + item.key).toLowerCase().includes(term) || item.products.length > 0);
      return { ...category, services };
    }).filter((category) => (category.name + ' ' + category.key).toLowerCase().includes(term) || category.services.length > 0);
  }, [categories, catalogueSearch]);

  const iconChoices=useMemo(()=>Object.keys(SERVICE_ICONS).filter(k=>k!=='default'&&k.includes(iconQuery.trim().toLowerCase())),[iconQuery]);

  const submitIcon=(e:React.FormEvent)=>{e.preventDefault();if(!iconService||!iconUpload.data.icon)return;iconUpload.post('/admin/catalogue/services/'+iconService.id+'/icon',{forceFormData:true,preserveScroll:true,onSuccess:()=>{setIconService(null);iconUpload.reset();}});};

  return <><Head title="Service Catalogue"/><main className="min-h-screen bg-slate-50 p-6 md:p-10">
    <div className="mx-auto max-w-7xl">
      <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
      <div className="mt-3 flex flex-wrap items-end justify-between gap-3"><div><h1 className="text-3xl font-extrabold">Service Catalogue</h1><p className="mt-2 text-slate-600">Manage real categories, services, products and local service icons.</p></div><Link href="/admin/catalogue/services/generate-icons" method="post" as="button" className="rounded-xl bg-indigo-600 px-4 py-3 font-semibold text-white">Generate Icons for All Services</Link></div>

      <div className="mt-8 grid gap-6 lg:grid-cols-3">
        <form onSubmit={e=>{e.preventDefault();category.post('/admin/catalogue/categories')}} className="rounded-2xl bg-white p-5 shadow-sm">
          <h2 className="font-bold">Create Category</h2><input className="mt-4 w-full rounded-xl border p-3" placeholder="key" value={category.data.key} onChange={e=>category.setData('key',e.target.value)}/><input className="mt-3 w-full rounded-xl border p-3" placeholder="name" value={category.data.name} onChange={e=>category.setData('name',e.target.value)}/><button className="mt-4 rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white">Create</button>
        </form>
        <form onSubmit={e=>{e.preventDefault();service.post('/admin/catalogue/services')}} className="rounded-2xl bg-white p-5 shadow-sm">
          <h2 className="font-bold">Create Service</h2><select className="mt-4 w-full rounded-xl border p-3" value={service.data.category_id} onChange={e=>service.setData('category_id',e.target.value)}><option value="">Select category</option>{categories.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}</select>
          <input className="mt-3 w-full rounded-xl border p-3" placeholder="key" value={service.data.key} onChange={e=>service.setData('key',e.target.value)}/><input className="mt-3 w-full rounded-xl border p-3" placeholder="name" value={service.data.name} onChange={e=>service.setData('name',e.target.value)}/>
          <div className="mt-3 flex items-center gap-2 rounded-xl border p-3"><ServiceIcon name={service.data.name||'service'} icon={service.data.icon||iconForService(service.data.name||'',service.data.key)} size="sm"/><select className="min-w-0 flex-1 rounded-lg border p-2" value={service.data.icon} onChange={e=>service.setData('icon',e.target.value)}><option value="">Auto icon</option>{Object.keys(SERVICE_ICONS).filter(k=>k!=='default').map(k=><option key={k} value={k}>{k}</option>)}</select></div>
          <button className="mt-4 rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white" disabled={service.processing}>Create</button>
        </form>
        <form onSubmit={e=>{e.preventDefault();product.post('/admin/catalogue/products')}} className="rounded-2xl bg-white p-5 shadow-sm">
          <h2 className="font-bold">Create Product</h2><select className="mt-4 w-full rounded-xl border p-3" value={product.data.service_id} onChange={e=>product.setData('service_id',e.target.value)}><option value="">Select service</option>{categories.flatMap(c=>c.services).map(s=><option key={s.id} value={s.id}>{s.name}</option>)}</select><input className="mt-3 w-full rounded-xl border p-3" placeholder="key" value={product.data.key} onChange={e=>product.setData('key',e.target.value)}/><input className="mt-3 w-full rounded-xl border p-3" placeholder="name" value={product.data.name} onChange={e=>product.setData('name',e.target.value)}/><button className="mt-4 rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white">Create</button>
        </form>
      </div>

      <section className="mt-8 rounded-2xl border bg-white p-5 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-extrabold">Icon Library</h2><p className="text-sm text-slate-500">Search local icons, assign them instantly, or import a safe SVG for a service.</p></div><input className="rounded-xl border p-3" placeholder="Search icons…" value={iconQuery} onChange={e=>setIconQuery(e.target.value)}/></div><div className="mt-4 flex flex-wrap gap-2">{iconChoices.map(k=><button type="button" key={k} onClick={()=>service.setData('icon',k)} className="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm"><span>{SERVICE_ICONS[k]}</span>{k}</button>)}</div></section>

      {iconService&&<section className="mt-5 rounded-2xl border border-indigo-200 bg-indigo-50 p-5"><div className="flex items-center gap-3"><ServiceIcon name={iconService.name} icon={iconService.metadata?.icon} iconUrl={iconService.metadata?.icon_url} /><div><h2 className="font-extrabold">Import icon for {iconService.name}</h2><p className="text-sm text-slate-600">SVG only, maximum 1 MB. Unsafe script/event markup is rejected.</p></div></div><form onSubmit={submitIcon} className="mt-4 flex flex-wrap gap-3"><input type="file" accept=".svg,image/svg+xml" required onChange={e=>iconUpload.setData('icon',e.target.files?.[0]||null)} className="rounded-xl border bg-white p-3"/><button disabled={iconUpload.processing} className="rounded-xl bg-indigo-700 px-4 py-3 font-semibold text-white">{iconUpload.processing?'Importing…':'Import SVG'}</button><button type="button" onClick={()=>setIconService(null)} className="rounded-xl border bg-white px-4 py-3 font-semibold">Cancel</button></form></section>}

      <section className="mt-8 space-y-4"><div className="rounded-2xl border bg-white p-4 shadow-sm"><label className="block"><span className="mb-2 block text-sm font-semibold text-slate-700">Search categories, services and products</span><input value={catalogueSearch} onChange={e=>setCatalogueSearch(e.target.value)} placeholder="e.g. MTN SME 1 GB or electricity" className="w-full rounded-xl border p-3 text-sm outline-none focus:border-indigo-500"/>{catalogueSearch && <button type="button" onClick={()=>setCatalogueSearch('')} className="mt-2 text-xs font-bold text-indigo-700">Clear search</button>}</label></div>{filteredCategories.length ? filteredCategories.map(c=><article key={c.id} className="rounded-2xl bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><h2 className="text-xl font-bold">{c.name}</h2><span className="text-xs text-slate-500">{c.key}</span></div><div className="mt-4 space-y-3">{c.services.map(s=><div key={s.id} className="flex flex-col gap-4 rounded-xl border p-4 md:flex-row md:items-center md:justify-between"><div className="flex items-center gap-3"><ServiceIcon name={s.name} icon={s.metadata?.icon} iconUrl={s.metadata?.icon_url}/><div><div className="font-semibold">{s.name} <span className="text-xs text-slate-500">({s.key})</span></div><div className="text-xs text-slate-500">{s.products.length} product(s) · {s.enabled?'enabled':'disabled'}</div></div></div><div className="flex flex-wrap gap-2"><button type="button" onClick={()=>setIconService(s)} className="rounded-lg border px-3 py-2 text-sm font-semibold">Import SVG</button><div className="rounded-lg bg-slate-50 px-3 py-2 text-sm">Icon: {s.metadata?.icon||iconForService(s.name,s.key)}</div></div>{s.products.length>0&&<ul className="text-sm text-slate-600 md:max-w-sm">{s.products.slice(0,5).map(p=><li key={p.id}>{p.name} — {p.key} — {p.enabled?'enabled':'disabled'}</li>)}</ul>}</div>)}</div></article>) : <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No categories, services or products match this search.</p>}</section>
    </div>
  </main></>;
}
