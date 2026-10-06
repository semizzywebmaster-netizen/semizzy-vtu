export const SERVICE_ICONS: Record<string,string> = {
  phone:'📱', data:'📶', airtime:'☎️', electricity:'⚡', cable:'📺', tv:'📺',
  exam:'🎓', education:'🎓', sms:'💬', whatsapp:'🟢', payment:'💳', wallet:'💰',
  banking:'🏦', gaming:'🎮', betting:'🎟️', shopping:'🛒', marketplace:'🛍️',
  insurance:'🛡️', loan:'💵', savings:'🏦', investment:'📈', internet:'🌐',
  hosting:'☁️', domain:'🌍', identity:'🪪', cac:'🏢', nin:'🪪', bvn:'🪪',
  transport:'🚕', travel:'✈️', food:'🍔', support:'🛠️', default:'✦',
};

export const iconForService = (name:string,key?:string):string => {
  const value=((key||'')+' '+name).toLowerCase();
  const found=Object.keys(SERVICE_ICONS).find(k=>k!=='default' && value.includes(k));
  return SERVICE_ICONS[found||'default'];
};

export default function ServiceIcon({name,icon,iconUrl,size='md'}:{name:string;icon?:string|null;iconUrl?:string|null;size?:'sm'|'md'|'lg'}){
  const sizes={sm:'h-9 w-9 text-lg',md:'h-12 w-12 text-2xl',lg:'h-16 w-16 text-3xl'};
  return iconUrl
    ? <img src={iconUrl} alt="" className={sizes[size]+' rounded-2xl object-cover'} />
    : <span aria-hidden="true" className={sizes[size]+' flex shrink-0 items-center justify-center rounded-2xl bg-indigo-50 ring-1 ring-indigo-100'}>{SERVICE_ICONS[icon||'']||iconForService(name,icon||undefined)}</span>;
}
