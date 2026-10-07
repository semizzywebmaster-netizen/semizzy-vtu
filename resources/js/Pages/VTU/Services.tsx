import CoreMobileNav from '../../Components/CoreMobileNav';
import ServiceIcon, { iconForService } from '../../Components/ServiceIcon';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Product = { id: number; name: string; key: string; metadata?: Record<string, unknown> | null };
type Service = { id: number; key: string; name: string; description?: string | null; metadata?: Record<string, unknown> | null; category?: { key: string; name: string; description?: string | null } | null; products: Product[] };

const categoryIcon = (value: string) => iconForService(value);

export default function Services({ services = [] }: { services: Service[] }) {
  const [query, setQuery] = useState('');
  const [activeCategory, setActiveCategory] = useState('all');
  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [quote, setQuote] = useState<{ customer_price: string; currency: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [bulkOpen, setBulkOpen] = useState(false);
  const [bulkDataOpen, setBulkDataOpen] = useState(false);
  const [bulkRows, setBulkRows] = useState<Array<{ phone: string; amount: string; network: string }>>([]);
  const [bulkDataProduct, setBulkDataProduct] = useState<Product | null>(null);
  const [bulkDataRows, setBulkDataRows] = useState<Array<{ phone: string; network: string }>>([]);
  const [bulkDataInput, setBulkDataInput] = useState('');
  const [bulkDataPin, setBulkDataPin] = useState('');
  const [bulkDataBusy, setBulkDataBusy] = useState(false);
  const [bulkDataQuote, setBulkDataQuote] = useState<{ total_customer_price: string; currency: string; total_items: number; quote_fingerprint: string } | null>(null);
  const [bulkDataResult, setBulkDataResult] = useState<string | null>(null);
  const [bulkInput, setBulkInput] = useState('');
  const [bulkPin, setBulkPin] = useState('');
  const [bulkBusy, setBulkBusy] = useState(false);
  const [bulkResult, setBulkResult] = useState<string | null>(null);
  const [bulkQuote, setBulkQuote] = useState<{ total_customer_price: string; currency: string; total_items: number; quote_fingerprint: string; items: Array<{ index: number; product_id: number; customer_price: string; currency: string }> } | null>(null);
  const [bulkQuoting, setBulkQuoting] = useState(false);
  const [bulkResolving, setBulkResolving] = useState(false);
  const [bulkResultItems, setBulkResultItems] = useState<Array<{ sequence?: number; recipient?: string; status?: string; reference?: string | null; error_message?: string | null }>>([]);

  const airtimeService = useMemo(() => services.find(service => service.key === 'airtime'), [services]);
  const airtimeProduct = airtimeService?.products?.[0] ?? null;
  const dataService = useMemo(() => services.find(service => service.key === 'data'), [services]);
  const detectNetwork = (phone: string) => {
    const normalized = phone.replace(/\D/g, '');
    if (normalized.startsWith('234')) return detectNetwork('0' + normalized.slice(3));
    if (/^0724\d{7}$/.test(normalized)) return 'lebara';
    if (/^(0803|0806|0810|0813|0814|0816|0703|0706|0903|0906)\d{7}$/.test(normalized)) return 'mtn';
    if (/^(0802|0808|0812|0701|0708|0810|0818|0901|0907)\d{7}$/.test(normalized)) return 'airtel';
    if (/^(0805|0807|0811|0815|0705|0905|0915)\d{7}$/.test(normalized)) return 'glo';
    if (/^(0809|0817|0818|0908|0909)\d{7}$/.test(normalized)) return '9mobile';
    return '';
  };
  const parseBulkRows = () => {
    const seen = new Set<string>();
    const rows = bulkInput.split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => {
      const [phone, amount] = line.split(/[,;\t]/g).map(value => value.trim());
      return { phone: phone || '', amount: amount || '', network: detectNetwork(phone || '') };
    }).filter(row => row.phone);
    const normalized = rows.filter(row => {
      const key = row.phone.replace(/\D/g, '').replace(/^234/, '0');
      if (!key || seen.has(key)) return false;
      seen.add(key);
      return true;
    }).slice(0, 500);
    setBulkRows(normalized);
    setBulkQuote(null);
    setBulkResult(null);
    setBulkResultItems([]);
  };
  const importBulkFile = async (file: File) => {
    const text = await file.text();
    setBulkInput(text);
    setBulkRows([]);
    setBulkQuote(null);
    setBulkResult(null);
    setBulkResultItems([]);
  };

  const resolveBulkNetworks = async () => {
    if (!bulkRows.length) return;
    setBulkResolving(true); setBulkQuote(null); setBulkResult(null);
    try {
      const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
      const rows = await Promise.all(bulkRows.map(async row => {
        const response=await fetch('/vtu/network-lookup',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({phone:row.phone})});
        const body=await response.json();
        return response.ok && body?.data?.network ? {...row, network: body.data.network} : row;
      }));
      setBulkRows(rows);
      const unresolved=rows.filter(row=>!row.network).length;
      if (unresolved) setBulkResult(unresolved + ' recipient' + (unresolved === 1 ? '' : 's') + ' could not be verified. Review the network before quoting.');
    } catch(e) {
      setBulkResult(e instanceof Error ? e.message : 'Network verification could not be completed.');
    } finally { setBulkResolving(false); }
  };

  const quoteBulk = async () => {
    if (!airtimeProduct || !bulkRows.length) return;
    setBulkQuoting(true); setBulkResult(null);
    try {
      const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
      const items=bulkRows.map(row=>({
        product_id: airtimeProduct.id,
        payload: { network: row.network, phone: row.phone, amount: row.amount },
      }));
      const response=await fetch('/vtu/bulk-quote',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({items})});
      const body=await response.json();
      if(!response.ok) throw new Error(body.message || 'Bulk quote could not be calculated.');
      setBulkQuote(body.data);
    } catch(e) {
      setBulkQuote(null);
      setBulkResult(e instanceof Error ? e.message : 'Bulk quote could not be calculated.');
    } finally { setBulkQuoting(false); }
  };

  const submitBulk = async () => {
    if (!airtimeProduct || !bulkRows.length || bulkPin.length !== 4) return;
    setBulkBusy(true); setBulkResult(null); setBulkResultItems([]);
    try {
      const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
      const items=bulkRows.map((row,index)=>({
        product_id: airtimeProduct.id,
        payload: { network: row.network, phone: row.phone, amount: row.amount },
        idempotency_key: `bulk-airtime-${Date.now()}-${index}-${row.phone}`,
      }));
      const response=await fetch('/vtu/bulk',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({
        items, idempotency_key: crypto.randomUUID(), quote_fingerprint: bulkQuote?.quote_fingerprint, transaction_pin: bulkPin,
      })});
      const body=await response.json();
      if(!response.ok) throw new Error(body.message || 'Bulk airtime could not be processed.');
      const data=body.data;
      const resultItems=Array.isArray(data?.items) ? data.items : [];
      setBulkResultItems(resultItems.map((item: any) => ({ sequence: item.sequence, recipient: item.recipient, status: item.status, reference: item.reference ?? item.transaction_reference ?? null, error_message: item.error_message ?? null })));
      setBulkResult(`Bulk ${data.reference || 'request'}: ${data.successful_items ?? 0} successful, ${data.failed_items ?? 0} failed, ${data.total_items ?? bulkRows.length} total.`);
      setBulkPin('');
    } catch(e) {
      setBulkResult(e instanceof Error ? e.message : 'Bulk airtime could not be processed.');
    } finally { setBulkBusy(false); }
  };

  const parseBulkDataRows = () => {
    const seen = new Set<string>();
    const rows = bulkDataInput.split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => {
      const [phone] = line.split(/[,;\t]/g).map(value => value.trim());
      return { phone: phone || '', network: detectNetwork(phone || '') };
    }).filter(row => row.phone);
    const normalized = rows.filter(row => {
      const key = row.phone.replace(/\D/g, '').replace(/^234/, '0');
      if (!key || seen.has(key)) return false;
      seen.add(key);
      return true;
    }).slice(0, 500);
    setBulkDataRows(normalized);
    setBulkDataQuote(null);
    setBulkDataResult(null);
  };

  const resolveBulkDataNetworks = async () => {
    if (!bulkDataRows.length) return;
    try {
      setBulkDataResult('Checking current networks…');
      const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
      const rows=await Promise.all(bulkDataRows.map(async row=>{
        const response=await fetch('/vtu/network-lookup',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({phone:row.phone})});
        const body=await response.json();
        return response.ok && body?.data?.network ? {...row,network:body.data.network} : row;
      }));
      setBulkDataRows(rows);
      const unresolved=rows.filter(row=>!row.network).length;
      setBulkDataResult(unresolved ? unresolved+' recipient'+(unresolved===1?'':'s')+' could not be verified.' : 'All recipient networks verified.');
      setBulkDataQuote(null);
    } catch(e) { setBulkDataResult(e instanceof Error ? e.message : 'Network verification failed.'); }
  };

  const quoteBulkData = async () => {
    if (!bulkDataProduct || !bulkDataRows.length) return;
    try {
      setBulkDataBusy(true); setBulkDataResult(null);
      const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
      const items=bulkDataRows.map(row=>({product_id:bulkDataProduct.id,payload:{network:row.network,phone:row.phone,plan:bulkDataProduct.name}}));
      const response=await fetch('/vtu/bulk-quote',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({items})});
      const body=await response.json(); if(!response.ok) throw new Error(body.message || 'Bulk data quote could not be calculated.');
      setBulkDataQuote(body.data); 
    } catch(e) { setBulkDataQuote(null); setBulkDataResult(e instanceof Error ? e.message : 'Bulk data quote could not be calculated.'); }
    finally { setBulkDataBusy(false); }
  };

  const submitBulkData = async () => {
    if (!bulkDataProduct || !bulkDataRows.length || bulkDataPin.length !== 4 || !bulkDataQuote) return;
    try {
      setBulkDataBusy(true); setBulkDataResult(null);
      const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
      const items=bulkDataRows.map((row,index)=>({product_id:bulkDataProduct.id,payload:{network:row.network,phone:row.phone,plan:bulkDataProduct.name},idempotency_key:'bulk-data-'+Date.now()+'-'+index+'-'+row.phone}));
      const response=await fetch('/vtu/bulk',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({items,idempotency_key:crypto.randomUUID(),quote_fingerprint:bulkDataQuote?.quote_fingerprint,transaction_pin:bulkDataPin})});
      const body=await response.json(); if(!response.ok) throw new Error(body.message || 'Bulk data could not be processed.');
      const data=body.data; setBulkDataResult('Bulk '+(data.reference||'request')+': '+(data.successful_items??0)+' successful, '+(data.failed_items??0)+' failed, '+(data.total_items??bulkDataRows.length)+' total.');
      setBulkDataPin('');
    } catch(e) { setBulkDataResult(e instanceof Error ? e.message : 'Bulk data could not be processed.'); }
    finally { setBulkDataBusy(false); }
  };

  const categories = useMemo(() => {
    const map = new Map<string, { key: string; name: string; description?: string | null }>();
    services.forEach(service => {
      const category = service.category;
      if (category && !map.has(category.key)) map.set(category.key, category);
    });
    return Array.from(map.values());
  }, [services]);

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    return services.filter(service => {
      const categoryMatch = activeCategory === 'all' || service.category?.key === activeCategory;
      const searchMatch = !term || [service.name, service.description, service.category?.name, ...(service.products || []).map(product => product.name)]
        .filter(Boolean).join(' ').toLowerCase().includes(term);
      return categoryMatch && searchMatch;
    });
  }, [services, query, activeCategory]);

  const totalProducts = services.reduce((sum, service) => sum + service.products.length, 0);

  return <main className='min-h-screen bg-slate-50 pb-24 text-slate-900'>
    <Head title='Services' />
    <section className='bg-slate-950 text-white'>
      <div className='mx-auto max-w-6xl px-4 pb-7 pt-5 sm:px-8'>
        <div className='flex items-center justify-between gap-3'>
          <div className='flex items-center gap-3'>
            <Link href='/dashboard' className='flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-lg hover:bg-white/15' aria-label='Back to dashboard'>←</Link>
            <div><p className='text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400'>SEMIZZY ONE</p><h1 className='mt-0.5 text-2xl font-black'>Services</h1></div>
          </div>
          <Link href='/notifications' className='flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-lg'>♧</Link>
        </div>
        <div className='mt-6 max-w-2xl'><p className='text-sm font-semibold text-indigo-300'>Everything you need, in one place</p><h2 className='mt-1 text-3xl font-black tracking-tight sm:text-4xl'>What would you like to do today?</h2><p className='mt-2 text-sm leading-6 text-slate-400'>Choose a service category, find an available product, and continue when you are ready.</p></div>
        <label className='mt-6 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-slate-900 shadow-lg ring-1 ring-white/10'>
          <span className='text-lg text-slate-400'>⌕</span><input value={query} onChange={event => setQuery(event.target.value)} placeholder='Search services or products' className='w-full bg-transparent text-sm font-medium outline-none placeholder:text-slate-400' />
        </label>
      </div>
    </section>

    <div className='mx-auto max-w-6xl px-4 sm:px-8'>
      <section className='-mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3'>
        <div className='rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200'><p className='text-xs font-semibold text-slate-500'>Services</p><p className='mt-1 text-2xl font-black'>{services.length}</p></div>
        <div className='rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200'><p className='text-xs font-semibold text-slate-500'>Categories</p><p className='mt-1 text-2xl font-black'>{categories.length}</p></div>
        <div className='hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:block'><p className='text-xs font-semibold text-slate-500'>Available products</p><p className='mt-1 text-2xl font-black'>{totalProducts}</p></div>
      </section>

      <section className='mt-5 overflow-x-auto pb-1'>
        <div className='flex min-w-max gap-2'>
          <button type='button' onClick={() => setActiveCategory('all')} className={'rounded-full px-4 py-2.5 text-sm font-bold transition ' + (activeCategory === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200')}>All services</button>
          {categories.map(category => <button key={category.key} type='button' onClick={() => setActiveCategory(category.key)} className={'flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold transition ' + (activeCategory === category.key ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200')}><ServiceIcon name={category.name} icon={categoryIcon(category.name)} size='sm'/>{category.name}</button>)}
        </div>
      </section>

      {filtered.length === 0 ? <section className='mt-6 rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm'>
        <div className='mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-100 text-2xl'>⌕</div><h3 className='mt-4 text-lg font-black'>No matching service</h3><p className='mx-auto mt-1 max-w-md text-sm text-slate-500'>Try another search or choose a different category. Only enabled catalogue services are shown here.</p>
        {(query || activeCategory !== 'all') && <button type='button' onClick={() => { setQuery(''); setActiveCategory('all'); }} className='mt-5 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white'>Clear filters</button>}
      </section> : <section className='mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>
        {filtered.map(service => <button type='button' key={service.id} onClick={() => setSelectedService(service)} className='group text-left rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:ring-indigo-200 hover:shadow-md'>
          <div className='flex items-start justify-between gap-3'><ServiceIcon name={service.name} icon={(service.metadata as any)?.icon} iconUrl={(service.metadata as any)?.icon_url} size='lg'/><span className='rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500'>{service.products.length} {service.products.length === 1 ? 'product' : 'products'}</span></div>
          <p className='mt-5 text-lg font-black'>{service.name}</p><p className='mt-1 min-h-10 text-sm leading-5 text-slate-500'>{service.description || 'Provider-powered digital service.'}</p>
          <div className='mt-5 flex items-center justify-between border-t border-slate-100 pt-4'><span className='text-xs font-semibold text-slate-400'>{service.category?.name || 'Digital service'}</span><span className='text-sm font-black text-indigo-700'>View options →</span></div>
        </button>)}
      </section>}

      {dataService?.products?.length ? <section className='mt-5 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200'>
        <p className='text-xs font-black uppercase tracking-wider text-indigo-600'>Bulk Purchase</p><h3 className='mt-1 text-lg font-black'>Bulk Data</h3><p className='mt-1 text-sm text-slate-500'>Send the same selected data plan to up to 500 verified recipients.</p>
        <button type='button' onClick={()=>{setBulkDataOpen(true);setBulkDataProduct(dataService.products[0]??null);setBulkDataResult(null);}} className='mt-4 w-full rounded-2xl bg-indigo-600 px-4 py-3.5 text-sm font-black text-white'>Open Bulk Data</button>
      </section> : null}

      <section className='mt-8 rounded-3xl bg-slate-900 p-5 text-white sm:p-6'><div className='flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'><div><p className='text-xs font-bold uppercase tracking-wider text-indigo-300'>Need help?</p><h3 className='mt-1 text-lg font-black'>Not sure which service to choose?</h3><p className='mt-1 text-sm text-slate-400'>Our support team can help you with an available service or product.</p></div><Link href='/support' className='rounded-xl bg-white px-4 py-2.5 text-center text-sm font-bold text-slate-900'>Contact support</Link></div></section>
    </div>

    {selectedService && <div className='fixed inset-0 z-50 flex items-end bg-slate-950/50 p-0 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4' onClick={() => setSelectedService(null)}>
      <section role='dialog' aria-modal='true' aria-label={selectedService.name} className='max-h-[88vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:max-w-lg sm:rounded-3xl' onClick={event => event.stopPropagation()}>
        <div className='flex items-start justify-between gap-4'><div className='flex items-center gap-3'><ServiceIcon name={selectedService.name} icon={(selectedService.metadata as any)?.icon} iconUrl={(selectedService.metadata as any)?.icon_url}/><div><p className='text-lg font-black'>{selectedService.name}</p><p className='text-xs text-slate-500'>{selectedService.category?.name || 'Digital service'}</p></div></div><button type='button' onClick={() => setSelectedService(null)} className='flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 font-bold'>×</button></div>
        <p className='mt-4 text-sm leading-6 text-slate-500'>{selectedService.description || 'Choose an available product below.'}</p>
        <div className='mt-5'><p className='text-xs font-bold uppercase tracking-wider text-slate-400'>Available products</p>
          {selectedService.products.length === 0 ? <div className='mt-3 rounded-2xl bg-slate-50 p-5 text-sm text-slate-500'>No enabled provider products are currently configured for this service.</div> : <div className='mt-3 space-y-2'>{selectedService.products.map(product => <button type='button' key={product.id} onClick={() => { setSelectedProduct(product); setQuote(null); setMessage(null); setForm({ plan: product.name }); }} className='flex w-full items-center justify-between rounded-2xl border border-slate-200 p-4 text-left transition hover:border-indigo-200 hover:bg-indigo-50'><div><p className='text-sm font-bold'>{product.name}</p><p className='mt-0.5 text-[11px] text-slate-400'>Select to purchase</p></div><span className='font-black text-indigo-700'>›</span></button>)}</div>}
        </div><p className='mt-5 rounded-2xl bg-amber-50 p-3 text-xs leading-5 text-amber-800'>Prices and transaction options are shown only when the product is fully configured and available. No provider cost is exposed here.</p>
      </section>
    </div>}
    {selectedProduct && selectedService && <div className='fixed inset-0 z-[60] flex items-end bg-slate-950/60 p-0 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4' onClick={() => setSelectedProduct(null)}>
      <section role='dialog' aria-modal='true' aria-label={'Purchase ' + selectedService.name} className='max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:max-w-lg sm:rounded-3xl' onClick={event => event.stopPropagation()}>
        <div className='flex items-start justify-between gap-3'>
          <div><p className='text-xs font-bold uppercase tracking-wider text-indigo-600'>{selectedService.name}</p><h3 className='mt-1 text-xl font-black'>{selectedProduct.name}</h3></div>
          <button type='button' onClick={() => setSelectedProduct(null)} className='flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 font-bold'>×</button>
        </div>
        <div className='mt-5 space-y-4'>
          {['airtime','data'].includes(selectedService.key) && <>
            <label className='block text-sm font-bold'>Network<select value={form.network || ''} onChange={e => setForm({...form, network:e.target.value})} className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-indigo-500'><option value=''>Select network</option><option value='mtn'>MTN</option><option value='airtel'>Airtel</option><option value='glo'>Glo</option><option value='9mobile'>9mobile</option><option value='lebara'>Lebara</option></select></label>
            <label className='block text-sm font-bold'>Phone number<input value={form.phone || ''} onChange={e => setForm({...form, phone:e.target.value})} placeholder='0803XXXXXXXX' inputMode='tel' className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-indigo-500' /></label>
            {selectedService.key === 'airtime' && <label className='block text-sm font-bold'>Amount<input value={form.amount || ''} onChange={e => setForm({...form, amount:e.target.value})} placeholder='1000' inputMode='decimal' className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-indigo-500' /><div className='mt-2 flex flex-wrap gap-2'>{['100','200','500','1000','2000','5000'].map(v => <button key={v} type='button' onClick={() => setForm({...form, amount:v})} className='rounded-full bg-slate-100 px-3 py-2 text-xs font-bold'>₦{v}</button>)}</div></label>}
          </>}
          {selectedService.key === 'electricity' && <>
            <label className='block text-sm font-bold'>Disco<input value={form.disco || ''} onChange={e => setForm({...form, disco:e.target.value})} placeholder='e.g. AEDC' className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none' /></label>
            <label className='block text-sm font-bold'>Meter number<input value={form.meter_number || ''} onChange={e => setForm({...form, meter_number:e.target.value})} className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none' /></label>
            <label className='block text-sm font-bold'>Meter type<select value={form.meter_type || 'prepaid'} onChange={e => setForm({...form, meter_type:e.target.value})} className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium'><option value='prepaid'>Prepaid</option><option value='postpaid'>Postpaid</option></select></label>
            <label className='block text-sm font-bold'>Amount<input value={form.amount || ''} onChange={e => setForm({...form, amount:e.target.value})} placeholder='5000' inputMode='decimal' className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none' /></label>
          </>}
          {['cable_tv','broadband'].includes(selectedService.key) && <>
            <label className='block text-sm font-bold'>Provider<input value={form.provider || ''} onChange={e => setForm({...form, provider:e.target.value})} placeholder='Provider' className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none' /></label>
            <label className='block text-sm font-bold'>{selectedService.key === 'cable_tv' ? 'Customer / IUC number' : 'Account ID'}<input value={form.customer_number || form.account_id || ''} onChange={e => setForm({...form, ...(selectedService.key === 'cable_tv' ? {customer_number:e.target.value} : {account_id:e.target.value})})} className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none' /></label>
            <label className='block text-sm font-bold'>{selectedService.key === 'cable_tv' ? 'Package' : 'Plan'}<input value={form.package || ''} onChange={e => setForm({...form, package:e.target.value})} placeholder={selectedProduct.name} className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none' /></label>
          </>}
          {selectedService.key === 'data' && <label className='block text-sm font-bold'>Plan<input value={form.plan || selectedProduct.name} onChange={e => setForm({...form, plan:e.target.value})} className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none' /></label>}
          {!['airtime','data','electricity','cable_tv','broadband'].includes(selectedService.key) && <label className='block text-sm font-bold'>Recipient / account details<input value={form.recipient || ''} onChange={e => setForm({...form, recipient:e.target.value})} className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none' /></label>}
          {message && <div className='rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700'>{message}</div>}
          {quote && <div className='rounded-2xl bg-slate-50 p-4'><p className='text-xs font-bold uppercase tracking-wider text-slate-400'>Total to pay</p><p className='mt-1 text-2xl font-black'>{quote.currency === 'NGN' ? '₦' : quote.currency + ' '}{(Number(quote.customer_price) / 100).toLocaleString(undefined,{minimumFractionDigits:2})}</p></div>}
          <button type='button' disabled={busy} onClick={async () => {
            setBusy(true); setMessage(null);
            try {
              const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
              const response=await fetch('/vtu/quote',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({product_id:selectedProduct.id})});
              const body=await response.json(); if(!response.ok) throw new Error(body.message || 'Unable to get price.');
              setQuote(body.data);
            } catch(e) { setMessage(e instanceof Error ? e.message : 'Unable to get price.'); } finally { setBusy(false); }
          }} className='w-full rounded-2xl bg-indigo-600 px-4 py-3.5 text-sm font-black text-white disabled:opacity-50'>{busy ? 'Checking price…' : quote ? 'Refresh price' : 'Get price'}</button>
          {quote && <label className='block text-sm font-bold'>Transaction PIN<input value={form.transaction_pin || ''} onChange={e => setForm({...form, transaction_pin:e.target.value.replace(/\D/g,'').slice(0,4)})} type='password' inputMode='numeric' maxLength={4} placeholder='••••' className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 text-center tracking-[0.5em] outline-none' /></label>}
          {quote && <button type='button' disabled={busy || (form.transaction_pin || '').length !== 4} onClick={async () => {
            setBusy(true); setMessage(null);
            try {
              const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '';
              const {transaction_pin, ...payload}=form;
              const response=await fetch('/vtu/purchase',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({product_id:selectedProduct.id,payload,idempotency_key:crypto.randomUUID(),transaction_pin})});
              const body=await response.json(); if(!response.ok) throw new Error(body.message || 'Purchase could not be completed.');
              setMessage('Transaction submitted successfully. Reference: ' + (body.data?.reference || 'pending'));
              setForm({}); setQuote(null);
            } catch(e) { setMessage(e instanceof Error ? e.message : 'Purchase could not be completed.'); } finally { setBusy(false); }
          }} className='w-full rounded-2xl bg-slate-900 px-4 py-3.5 text-sm font-black text-white disabled:opacity-50'>{busy ? 'Processing…' : 'Confirm purchase'}</button>}
        </div>
      </section>
    </div>}
  {bulkDataOpen && bulkDataProduct && <div className='fixed inset-0 z-[75] flex items-end bg-slate-950/60 p-0 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4' onClick={()=>!bulkDataBusy&&setBulkDataOpen(false)}>
    <section role='dialog' aria-modal='true' aria-label='Bulk Data' className='max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:max-w-2xl sm:rounded-3xl' onClick={event=>event.stopPropagation()}>
      <div className='flex items-start justify-between gap-4'><div><p className='text-xs font-black uppercase tracking-wider text-indigo-600'>Bulk Data</p><h3 className='mt-1 text-2xl font-black'>Send one plan to many numbers</h3></div><button type='button' onClick={()=>setBulkDataOpen(false)} disabled={bulkDataBusy} className='flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 font-bold'>×</button></div>
      <label className='mt-5 block text-sm font-bold'>Data plan<select value={bulkDataProduct.id} onChange={e=>{const p=dataService?.products.find(x=>x.id===Number(e.target.value))||null;setBulkDataProduct(p);setBulkDataQuote(null);}} className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 font-medium'>{dataService?.products.map(p=><option key={p.id} value={p.id}>{p.name}</option>)}</select></label>
      <div className='mt-4 rounded-2xl bg-slate-50 p-4'><p className='text-xs font-black uppercase tracking-wider text-slate-500'>Recipients</p><p className='mt-2 text-xs text-slate-500'>One phone number per line, or CSV/TXT with the phone in the first column. Duplicates are removed. Maximum 500.</p><textarea value={bulkDataInput} onChange={e=>setBulkDataInput(e.target.value)} placeholder={'08012345678\n08123456789\n0724xxxxxxx'} rows={6} className='mt-3 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm outline-none'/><button type='button' onClick={parseBulkDataRows} className='mt-3 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white'>Validate & preview</button></div>
      {bulkDataRows.length>0 && <div className='mt-4 overflow-x-auto rounded-2xl border border-slate-200'><table className='w-full text-left text-xs'><thead className='bg-slate-50'><tr><th className='p-3'>Phone</th><th className='p-3'>Current network</th></tr></thead><tbody>{bulkDataRows.map((row,i)=><tr key={i} className='border-t'><td className='p-3 font-mono'>{row.phone}</td><td className='p-3 font-bold'>{row.network?row.network.toUpperCase():<span className='text-amber-600'>Unknown</span>}</td></tr>)}</tbody></table></div>}
      {bulkDataRows.length>0 && <div className='mt-4 rounded-2xl border border-slate-200 p-4'><button type='button' onClick={resolveBulkDataNetworks} disabled={bulkDataBusy} className='w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black'>Verify current networks</button><button type='button' onClick={quoteBulkData} disabled={bulkDataBusy||bulkDataRows.some(r=>!r.network||!/^\\+?(234|0)\\d{10}$/.test(r.phone.replace(/\\s|-/g,'')))} className='mt-2 w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-black text-white disabled:opacity-40'>{bulkDataBusy?'Working…':'Get exact bulk quote'}</button>{bulkDataQuote&&<div className='mt-3 rounded-xl bg-indigo-50 p-3 text-sm flex justify-between'><span>{bulkDataQuote.total_items} data purchases</span><strong>{bulkDataQuote.currency} {Number(bulkDataQuote.total_customer_price).toLocaleString()}</strong></div>}<label className='mt-4 block text-sm font-bold'>Transaction PIN<input value={bulkDataPin} onChange={e=>setBulkDataPin(e.target.value.replace(/\\D/g,'').slice(0,4))} type='password' inputMode='numeric' maxLength={4} placeholder='••••' className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 text-center tracking-[0.5em]'/></label>{bulkDataResult&&<div className='mt-3 rounded-xl bg-slate-50 p-3 text-sm font-semibold'>{bulkDataResult}</div>}<button type='button' onClick={submitBulkData} disabled={bulkDataBusy||!bulkDataQuote||bulkDataPin.length!==4} className='mt-4 w-full rounded-2xl bg-indigo-600 px-4 py-3.5 text-sm font-black text-white disabled:opacity-40'>{bulkDataBusy?'Processing bulk data…':'Confirm & purchase all'}</button></div>}
    </section></div>}
\n  {bulkOpen && airtimeProduct && <div className='fixed inset-0 z-[70] flex items-end bg-slate-950/60 p-0 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4' onClick={() => !bulkBusy && setBulkOpen(false)}>
    <section role='dialog' aria-modal='true' aria-label='Bulk Airtime' className='max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:max-w-2xl sm:rounded-3xl' onClick={event => event.stopPropagation()}>
      <div className='flex items-start justify-between gap-4'><div><p className='text-xs font-black uppercase tracking-wider text-indigo-600'>Bulk Airtime</p><h3 className='mt-1 text-2xl font-black'>Auto-detect & recharge</h3><p className='mt-1 text-sm text-slate-500'>All rows use the configured airtime product. Mixed networks are supported.</p></div><button type='button' onClick={() => setBulkOpen(false)} disabled={bulkBusy} className='flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 font-bold'>×</button></div>
      <div className='mt-5 rounded-2xl bg-slate-50 p-4'>
        <p className='text-xs font-black uppercase tracking-wider text-slate-500'>Paste list</p>
        <p className='mt-2 text-xs text-slate-500'>CSV/TXT format: phone,amount. Duplicate numbers are removed before processing.<br/>Format: <span className='font-mono'>08012345678,1000</span> — one recipient per line. CSV-style commas, semicolons and tabs are accepted.</p>
        <textarea value={bulkInput} onChange={e => setBulkInput(e.target.value)} placeholder={'08012345678,1000\n08123456789,2000\n0724xxxxxxx,1500'} rows={6} className='mt-3 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm outline-none focus:border-indigo-500' />
        <label className='mt-3 inline-flex cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700'>Upload CSV / TXT<input type='file' accept='.csv,.txt,text/csv,text/plain' className='hidden' onChange={e => { const file=e.target.files?.[0]; if(file) void importBulkFile(file); e.currentTarget.value=''; }} /></label>
        <button type='button' onClick={parseBulkRows} className='mt-3 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white'>Validate & preview</button>
      </div>
      {bulkRows.length > 0 && <div className='mt-4 overflow-x-auto rounded-2xl border border-slate-200'><table className='w-full text-left text-xs'><thead className='bg-slate-50'><tr><th className='p-3'>Phone</th><th className='p-3'>Amount</th><th className='p-3'>Detected network</th></tr></thead><tbody>{bulkRows.map((row,index) => <tr key={index} className='border-t'><td className='p-3 font-mono'>{row.phone}</td><td className='p-3'>₦{Number(row.amount || 0).toLocaleString()}</td><td className='p-3 font-bold'>{row.network ? row.network.toUpperCase() : <span className='text-amber-600'>Unknown — review</span>}</td></tr>)}</tbody></table></div>}
      {bulkRows.length > 0 && <div className='mt-4 rounded-2xl border border-slate-200 p-4'>
        <button type='button' onClick={resolveBulkNetworks} disabled={bulkResolving || !bulkRows.length} className='mb-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 disabled:opacity-40'>{bulkResolving ? 'Checking current networks…' : 'Verify current networks'}</button>
        <button type='button' onClick={quoteBulk} disabled={bulkQuoting || bulkRows.some(row => !row.network || !/^\+?(234|0)\d{10}$/.test(row.phone.replace(/\s|-/g,'')) || !(Number(row.amount) > 0))} className='w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-black text-white disabled:opacity-40'>{bulkQuoting ? 'Calculating secure quote…' : 'Get exact bulk quote'}</button>
        {bulkQuote && <div className='mt-3 rounded-xl bg-indigo-50 p-3 text-sm'><div className='flex justify-between'><span className='text-slate-600'>{bulkQuote.total_items} purchases</span><strong>{bulkQuote.currency} {Number(bulkQuote.total_customer_price).toLocaleString()}</strong></div><p className='mt-1 text-[11px] text-slate-500'>Final total from the server-side Price Engine.</p></div>}
        <div className='mt-4'>
        <p className='text-sm font-black'>Confirm bulk purchase</p><p className='mt-1 text-xs text-slate-500'>{bulkRows.length} recipients · estimated airtime value ₦{bulkRows.reduce((sum,row) => sum + (Number(row.amount) || 0), 0).toLocaleString()}</p>
        <label className='mt-4 block text-sm font-bold'>Transaction PIN<input value={bulkPin} onChange={e => setBulkPin(e.target.value.replace(/\D/g,'').slice(0,4))} type='password' inputMode='numeric' maxLength={4} placeholder='••••' className='mt-1 w-full rounded-xl border border-slate-200 px-3 py-3 text-center tracking-[0.5em] outline-none' /></label>
        <p className='mt-2 text-[11px] leading-5 text-slate-500'>Network detection uses a safe prefix fallback. Where the provider supports current-network/MNP verification, that provider result should take precedence before fulfillment.</p>
        {bulkResult && <div className='mt-3 rounded-xl bg-slate-50 p-3 text-sm font-semibold text-slate-700'>{bulkResult}</div>}
        {bulkResultItems.length > 0 && <div className='mt-3 max-h-56 overflow-auto rounded-xl border border-slate-200'><table className='w-full text-left text-[11px]'><thead className='sticky top-0 bg-slate-50'><tr><th className='p-2'>#</th><th className='p-2'>Recipient</th><th className='p-2'>Status</th><th className='p-2'>Reference / Error</th></tr></thead><tbody>{bulkResultItems.map((item,index)=><tr key={`${item.sequence ?? index}-${item.recipient ?? ''}`} className='border-t'><td className='p-2'>{item.sequence ?? index+1}</td><td className='p-2 font-mono'>{item.recipient || '—'}</td><td className='p-2 font-bold'>{item.status || '—'}</td><td className='p-2'>{item.reference || item.error_message || '—'}</td></tr>)}</tbody></table></div>}
        <button type='button' onClick={submitBulk} disabled={bulkBusy || !bulkQuote || bulkPin.length !== 4 || bulkRows.some(row => !row.network || !/^\+?(234|0)\d{10}$/.test(row.phone.replace(/\s|-/g,'')) || !(Number(row.amount) > 0))} className='mt-4 w-full rounded-2xl bg-indigo-600 px-4 py-3.5 text-sm font-black text-white disabled:opacity-40'>{bulkBusy ? 'Processing bulk airtime…' : 'Confirm & purchase all'}</button>
      </div>}
    </section>
  </div>}
  <CoreMobileNav /></main>;
}