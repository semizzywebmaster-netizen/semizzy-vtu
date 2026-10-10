import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import ServiceIcon, { SERVICE_ICONS, iconForService } from '../../Components/ServiceIcon';

type Product={id:number;key:string;name:string;enabled:boolean;publication_status?:string;published_at?:string|null};
type Service={id:number;key:string;name:string;enabled:boolean;metadata?:Record<string,any>|null;products:Product[]};
type Category={id:number;key:string;name:string;enabled:boolean;services:Service[]};
type TierQuote={provider_cost:string;selling_price:string;gross_profit:string;currency:string;provider_id:number;price_rule_id:number|null};
type PublicationAssessment={ready:boolean;blockers:string[];tiers:string[];tier_quotes:Record<string,TierQuote>;provider?:{id:number;name:string;external_product_id:string;source_cost:string;currency:string;last_synced_at:string|null}|null};

export default function Catalogue({categories=[],search=''}:{categories:Category[];search?:string}) {
  const category=useForm({key:'',name:'',description:'',sort_order:100,enabled:true});
  const service=useForm({category_id:'',key:'',name:'',description:'',icon:'',enabled:true});
  const product=useForm({service_id:'',key:'',name:'',currency:'NGN',enabled:false});
  const iconUpload=useForm<{icon:File|null}>({icon:null});
  const [catalogueSearch,setCatalogueSearch]=useState(search);
  const [publicationProduct,setPublicationProduct]=useState<Product|null>(null);
  const [publicationAssessment,setPublicationAssessment]=useState<PublicationAssessment|null>(null);
  const [publicationLoading,setPublicationLoading]=useState(false);
  const [publicationBusy,setPublicationBusy]=useState(false);
  const [publicationError,setPublicationError]=useState('');
  const [publicationMessage,setPublicationMessage]=useState('');
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

  const openPublication = async (item: Product) => {
    setPublicationProduct(item);
    setPublicationAssessment(null);
    setPublicationError('');
    setPublicationMessage('');
    setPublicationLoading(true);
    try {
      const response = await fetch('/admin/catalogue/products/' + item.id + '/publication-readiness', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.message || 'Could not check publication readiness.');
      setPublicationAssessment(data as PublicationAssessment);
    } catch (error) {
      setPublicationError(error instanceof Error ? error.message : 'Could not check publication readiness.');
    } finally {
      setPublicationLoading(false);
    }
  };

  const changePublication = async (action: 'publish' | 'unpublish') => {
    if (!publicationProduct) return;
    setPublicationBusy(true);
    setPublicationError('');
    setPublicationMessage('');
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    const token = meta || (cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '');
    try {
      const response = await fetch('/admin/catalogue/products/' + publicationProduct.id + '/' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
        credentials: 'same-origin',
        body: JSON.stringify({}),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        if (data.assessment) setPublicationAssessment(data.assessment as PublicationAssessment);
        throw new Error(data.message || 'Publication action was not completed.');
      }
      setPublicationMessage(data.message || (action === 'publish' ? 'Product added to My Services.' : 'Product unpublished.'));
      setPublicationProduct(null);
      router.reload({ only: ['categories'] });
    } catch (error) {
      setPublicationError(error instanceof Error ? error.message : 'Publication action was not completed.');
    } finally {
      setPublicationBusy(false);
    }
  };

  const submitIcon=(e:React.FormEvent)=>{e.preventDefault();if(!iconService||!iconUpload.data.icon)return;iconUpload.post('/admin/catalogue/services/'+iconService.id+'/icon',{forceFormData:true,preserveScroll:true,onSuccess:()=>{setIconService(null);iconUpload.reset();}});};

  return <><Head title="Service Catalogue"/><main className="min-h-screen bg-slate-50 p-6 md:p-10">
    <div className="mx-auto max-w-7xl">
      <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
      <div className="mt-3 flex flex-wrap items-end justify-between gap-3"><div><h1 className="text-3xl font-extrabold">Service Catalogue</h1><p className="mt-2 text-slate-600">Manage real categories, services, products and local service icons.</p></div><Link href="/admin/catalogue/services/generate-icons" method="post" as="button" className="rounded-xl bg-indigo-600 px-4 py-3 font-semibold text-white">Generate Icons for All Services</Link></div>

      {publicationMessage && <p role="status" className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{publicationMessage}</p>}

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
          <h2 className="font-bold">Save Product as Draft</h2><p className="mt-1 text-xs text-slate-500">New products stay unpublished until provider, routing, source-cost and all tier-price checks pass.</p><select className="mt-4 w-full rounded-xl border p-3" value={product.data.service_id} onChange={e=>product.setData('service_id',e.target.value)}><option value="">Select service</option>{categories.flatMap(c=>c.services).map(s=><option key={s.id} value={s.id}>{s.name}</option>)}</select><input className="mt-3 w-full rounded-xl border p-3" placeholder="key" value={product.data.key} onChange={e=>product.setData('key',e.target.value)}/><input className="mt-3 w-full rounded-xl border p-3" placeholder="name" value={product.data.name} onChange={e=>product.setData('name',e.target.value)}/><button className="mt-4 rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white" disabled={product.processing}>Save as Draft</button>
        </form>
      </div>

      <section className="mt-8 rounded-2xl border bg-white p-5 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-extrabold">Icon Library</h2><p className="text-sm text-slate-500">Search local icons, assign them instantly, or import a safe SVG for a service.</p></div><input className="rounded-xl border p-3" placeholder="Search icons…" value={iconQuery} onChange={e=>setIconQuery(e.target.value)}/></div><div className="mt-4 flex flex-wrap gap-2">{iconChoices.map(k=><button type="button" key={k} onClick={()=>service.setData('icon',k)} className="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm"><span>{SERVICE_ICONS[k]}</span>{k}</button>)}</div></section>

      {iconService&&<section className="mt-5 rounded-2xl border border-indigo-200 bg-indigo-50 p-5"><div className="flex items-center gap-3"><ServiceIcon name={iconService.name} icon={iconService.metadata?.icon} iconUrl={iconService.metadata?.icon_url} /><div><h2 className="font-extrabold">Import icon for {iconService.name}</h2><p className="text-sm text-slate-600">SVG only, maximum 1 MB. Unsafe script/event markup is rejected.</p></div></div><form onSubmit={submitIcon} className="mt-4 flex flex-wrap gap-3"><input type="file" accept=".svg,image/svg+xml" required onChange={e=>iconUpload.setData('icon',e.target.files?.[0]||null)} className="rounded-xl border bg-white p-3"/><button disabled={iconUpload.processing} className="rounded-xl bg-indigo-700 px-4 py-3 font-semibold text-white">{iconUpload.processing?'Importing…':'Import SVG'}</button><button type="button" onClick={()=>setIconService(null)} className="rounded-xl border bg-white px-4 py-3 font-semibold">Cancel</button></form></section>}

      <section className="mt-8 space-y-4"><div className="rounded-2xl border bg-white p-4 shadow-sm"><label className="block"><span className="mb-2 block text-sm font-semibold text-slate-700">Search categories, services and products</span><input value={catalogueSearch} onChange={e=>setCatalogueSearch(e.target.value)} placeholder="e.g. MTN SME 1 GB or electricity" className="w-full rounded-xl border p-3 text-sm outline-none focus:border-indigo-500"/>{catalogueSearch && <button type="button" onClick={()=>setCatalogueSearch('')} className="mt-2 text-xs font-bold text-indigo-700">Clear search</button>}</label></div>{filteredCategories.length ? filteredCategories.map(c=><article key={c.id} className="rounded-2xl bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><h2 className="text-xl font-bold">{c.name}</h2><span className="text-xs text-slate-500">{c.key}</span></div><div className="mt-4 space-y-3">{c.services.map(s=><div key={s.id} className="flex flex-col gap-4 rounded-xl border p-4 md:flex-row md:items-center md:justify-between"><div className="flex items-center gap-3"><ServiceIcon name={s.name} icon={s.metadata?.icon} iconUrl={s.metadata?.icon_url}/><div><div className="font-semibold">{s.name} <span className="text-xs text-slate-500">({s.key})</span></div><div className="text-xs text-slate-500">{s.products.length} product(s) · {s.enabled?'enabled':'disabled'}</div></div></div><div className="flex flex-wrap gap-2"><button type="button" onClick={()=>setIconService(s)} className="rounded-lg border px-3 py-2 text-sm font-semibold">Import SVG</button><div className="rounded-lg bg-slate-50 px-3 py-2 text-sm">Icon: {s.metadata?.icon||iconForService(s.name,s.key)}</div></div>{s.products.length>0&&<ul className="max-h-72 space-y-2 overflow-y-auto text-sm text-slate-600 md:max-w-xl">{s.products.map(p=><li key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-2"><span>{p.name} — {p.key} <span className="text-xs text-slate-500">({p.publication_status || (p.enabled ? 'published' : 'draft')})</span></span><button type="button" onClick={()=>void openPublication(p)} className="shrink-0 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50">{p.publication_status==='published'?'Publication details':'Review / Add to My Services'}</button></li>)}</ul>}</div>)}</div></article>) : <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No categories, services or products match this search.</p>}</section>

      {publicationProduct && <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 p-0 sm:items-center sm:p-4" role="presentation" onMouseDown={(event)=>{if(event.target===event.currentTarget)setPublicationProduct(null);}}>
        <section role="dialog" aria-modal="true" aria-labelledby="publication-title" className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-3xl md:p-7">
          <div className="flex items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wider text-indigo-700">Publication readiness</p><h2 id="publication-title" className="mt-1 text-xl font-extrabold text-slate-950">{publicationProduct.name}</h2><p className="mt-1 text-sm text-slate-500">Product key: {publicationProduct.key}</p></div><button type="button" onClick={()=>setPublicationProduct(null)} aria-label="Close publication readiness" className="rounded-xl border px-3 py-2 text-lg">×</button></div>
          {publicationLoading ? <p className="mt-5 rounded-xl bg-slate-50 p-5 text-sm text-slate-600">Checking provider, routing, source-cost freshness and all tier prices…</p> : publicationAssessment && <>
            <div className={'mt-5 rounded-xl border p-4 text-sm '+(publicationAssessment.ready?'border-emerald-200 bg-emerald-50 text-emerald-900':'border-amber-200 bg-amber-50 text-amber-950')}><strong>{publicationAssessment.ready?'Ready to publish':'Publication blocked'}</strong><p className="mt-1">{publicationAssessment.ready?'All required provider, routing and tier-price checks passed.':'Resolve the following blockers before this product can be added to My Services.'}</p></div>
            {publicationAssessment.provider && <div className="mt-4 rounded-xl border border-slate-200 p-4"><h3 className="font-bold">Selected provider</h3><p className="mt-1 text-sm text-slate-700">{publicationAssessment.provider.name} · External ID: {publicationAssessment.provider.external_product_id}</p><p className="mt-1 text-sm text-slate-600">Source cost: {publicationAssessment.provider.currency} {publicationAssessment.provider.source_cost}</p><p className="mt-1 text-xs text-slate-500">Last sync: {publicationAssessment.provider.last_synced_at || 'Never recorded'}</p></div>}
            {publicationAssessment.blockers.length>0 && <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4"><h3 className="font-bold text-rose-900">Required actions</h3><ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-900">{publicationAssessment.blockers.map((blocker,index)=><li key={index}>{blocker}</li>)}</ul></div>}
            {Object.keys(publicationAssessment.tier_quotes).length>0 && <div className="mt-4"><h3 className="font-bold text-slate-900">PriceEngine tier preview</h3><div className="mt-2 grid gap-2 sm:grid-cols-2">{Object.entries(publicationAssessment.tier_quotes).map(([tier,quote])=><div key={tier} className="rounded-xl border border-slate-200 p-3"><p className="text-xs font-bold uppercase tracking-wider text-slate-500">{tier}</p><p className="mt-1 text-sm">Cost: <strong>{quote.currency} {quote.provider_cost}</strong></p><p className="text-sm">Selling: <strong>{quote.currency} {quote.selling_price}</strong></p><p className={'text-sm '+(Number(quote.gross_profit)<0?'text-rose-700':'text-emerald-700')}>Gross profit: {quote.currency} {quote.gross_profit}</p></div>)}</div></div>}
          </>}
          {publicationError && <p role="alert" className="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{publicationError}</p>}
          <div className="mt-6 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4"><button type="button" onClick={()=>setPublicationProduct(null)} className="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold">Close</button>{publicationProduct.publication_status==='published' ? <button type="button" disabled={publicationBusy} onClick={()=>void changePublication('unpublish')} className="rounded-xl border border-rose-200 px-4 py-3 text-sm font-bold text-rose-700 disabled:opacity-50">{publicationBusy?'Working…':'Unpublish'}</button> : <button type="button" disabled={publicationBusy||publicationLoading||!publicationAssessment?.ready} onClick={()=>void changePublication('publish')} className="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">{publicationBusy?'Publishing…':'Add to My Services'}</button>}</div>
        </section>
      </div>}
    </div>
  </main></>;
}
