import CoreMobileNav from '../../Components/CoreMobileNav';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Product = { id: number; name: string; key: string; metadata?: Record<string, unknown> | null };
type Service = { id: number; key: string; name: string; description?: string | null; metadata?: Record<string, unknown> | null; category?: { key: string; name: string; description?: string | null } | null; products: Product[] };

const iconFor = (value: string) => {
  const key = value.toLowerCase();
  if (key.includes('data')) return '▣';
  if (key.includes('airtime') || key.includes('voice')) return '◉';
  if (key.includes('electric') || key.includes('bill')) return '⌁';
  if (key.includes('cable') || key.includes('tv')) return '▤';
  if (key.includes('exam') || key.includes('education')) return '✎';
  if (key.includes('sms') || key.includes('message')) return '✉';
  if (key.includes('cash') || key.includes('payment') || key.includes('wallet')) return '₦';
  if (key.includes('bet') || key.includes('gaming')) return '◎';
  return '✦';
};

const categoryIcon = (value: string) => {
  const key = value.toLowerCase();
  if (key.includes('vtu') || key.includes('digital')) return '⚡';
  if (key.includes('education')) return '✎';
  if (key.includes('bill')) return '⌁';
  if (key.includes('payment')) return '₦';
  return '✦';
};

export default function Services({ services = [] }: { services: Service[] }) {
  const [query, setQuery] = useState('');
  const [activeCategory, setActiveCategory] = useState('all');
  const [selectedService, setSelectedService] = useState<Service | null>(null);

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

  return <main className='min-h-screen bg-slate-50 pb-10 text-slate-900'>
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
          {categories.map(category => <button key={category.key} type='button' onClick={() => setActiveCategory(category.key)} className={'flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold transition ' + (activeCategory === category.key ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200')}><span>{categoryIcon(category.name)}</span>{category.name}</button>)}
        </div>
      </section>

      {filtered.length === 0 ? <section className='mt-6 rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm'>
        <div className='mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-100 text-2xl'>⌕</div><h3 className='mt-4 text-lg font-black'>No matching service</h3><p className='mx-auto mt-1 max-w-md text-sm text-slate-500'>Try another search or choose a different category. Only enabled catalogue services are shown here.</p>
        {(query || activeCategory !== 'all') && <button type='button' onClick={() => { setQuery(''); setActiveCategory('all'); }} className='mt-5 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white'>Clear filters</button>}
      </section> : <section className='mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>
        {filtered.map(service => <button type='button' key={service.id} onClick={() => setSelectedService(service)} className='group text-left rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:ring-indigo-200 hover:shadow-md'>
          <div className='flex items-start justify-between gap-3'><span className='flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-xl font-black text-indigo-700 transition group-hover:bg-indigo-100'>{iconFor(service.name)}</span><span className='rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500'>{service.products.length} {service.products.length === 1 ? 'product' : 'products'}</span></div>
          <p className='mt-5 text-lg font-black'>{service.name}</p><p className='mt-1 min-h-10 text-sm leading-5 text-slate-500'>{service.description || 'Provider-powered digital service.'}</p>
          <div className='mt-5 flex items-center justify-between border-t border-slate-100 pt-4'><span className='text-xs font-semibold text-slate-400'>{service.category?.name || 'Digital service'}</span><span className='text-sm font-black text-indigo-700'>View options →</span></div>
        </button>)}
      </section>}

      <section className='mt-8 rounded-3xl bg-slate-900 p-5 text-white sm:p-6'><div className='flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'><div><p className='text-xs font-bold uppercase tracking-wider text-indigo-300'>Need help?</p><h3 className='mt-1 text-lg font-black'>Not sure which service to choose?</h3><p className='mt-1 text-sm text-slate-400'>Our support team can help you with an available service or product.</p></div><Link href='/support' className='rounded-xl bg-white px-4 py-2.5 text-center text-sm font-bold text-slate-900'>Contact support</Link></div></section>
    </div>

    {selectedService && <div className='fixed inset-0 z-50 flex items-end bg-slate-950/50 p-0 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4' onClick={() => setSelectedService(null)}>
      <section role='dialog' aria-modal='true' aria-label={selectedService.name} className='max-h-[88vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:max-w-lg sm:rounded-3xl' onClick={event => event.stopPropagation()}>
        <div className='flex items-start justify-between gap-4'><div className='flex items-center gap-3'><span className='flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-xl font-black text-indigo-700'>{iconFor(selectedService.name)}</span><div><p className='text-lg font-black'>{selectedService.name}</p><p className='text-xs text-slate-500'>{selectedService.category?.name || 'Digital service'}</p></div></div><button type='button' onClick={() => setSelectedService(null)} className='flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 font-bold'>×</button></div>
        <p className='mt-4 text-sm leading-6 text-slate-500'>{selectedService.description || 'Choose an available product below.'}</p>
        <div className='mt-5'><p className='text-xs font-bold uppercase tracking-wider text-slate-400'>Available products</p>
          {selectedService.products.length === 0 ? <div className='mt-3 rounded-2xl bg-slate-50 p-5 text-sm text-slate-500'>No enabled provider products are currently configured for this service.</div> : <div className='mt-3 space-y-2'>{selectedService.products.map(product => <div key={product.id} className='flex items-center justify-between rounded-2xl border border-slate-200 p-4'><div><p className='text-sm font-bold'>{product.name}</p><p className='mt-0.5 text-[11px] text-slate-400'>Available product</p></div><span className='text-slate-300'>›</span></div>)}</div>}
        </div><p className='mt-5 rounded-2xl bg-amber-50 p-3 text-xs leading-5 text-amber-800'>Prices and transaction options are shown only when the product is fully configured and available. No provider cost is exposed here.</p>
      </section>
    </div>}
  <CoreMobileNav /></main>;
}