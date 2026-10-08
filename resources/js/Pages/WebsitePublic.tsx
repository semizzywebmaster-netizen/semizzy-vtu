import React from 'react';
import {Head} from '@inertiajs/react';

type Section={id:string,type:string,data:Record<string,any>};
type Props={site:{id:number,name:string,slug:string,template_key:string,settings?:Record<string,any>},page:{id:number,title:string,slug:string,content?:{sections?:Section[]},seo?:Record<string,any>},preview?:boolean};

const themes:Record<string,{bg:string,fg:string,muted:string,accent:string,card:string}>={
 'modern-corporate':{bg:'#f8fafc',fg:'#0f172a',muted:'#475569',accent:'#2563eb',card:'#ffffff'},
 'clean-saas':{bg:'#ffffff',fg:'#111827',muted:'#6b7280',accent:'#7c3aed',card:'#f9fafb'},
 'opay-inspired':{bg:'#f7fff9',fg:'#12301f',muted:'#52705d',accent:'#16a34a',card:'#ffffff'},
 'palmpay-inspired':{bg:'#f7fbff',fg:'#10233f',muted:'#526784',accent:'#1677ff',card:'#ffffff'},
 'luxury-executive':{bg:'#111111',fg:'#f5f5f5',muted:'#b7b7b7',accent:'#d4af37',card:'#1c1c1c'},
 'sky-enterprise':{bg:'#eff6ff',fg:'#102a43',muted:'#486581',accent:'#0284c7',card:'#ffffff'},
 'forest-growth':{bg:'#f2f8f3',fg:'#17351f',muted:'#52705b',accent:'#15803d',card:'#ffffff'},
 'crimson-modern':{bg:'#fff7f7',fg:'#351316',muted:'#755357',accent:'#dc2626',card:'#ffffff'},
 'sunset-commerce':{bg:'#fff8f2',fg:'#3b2010',muted:'#795548',accent:'#ea580c',card:'#ffffff'},
 'slate-professional':{bg:'#f1f5f9',fg:'#172033',muted:'#526070',accent:'#334155',card:'#ffffff'}
};

const safeUrl=(value:any)=>typeof value==='string' && /^(https?:\/\/|\/|#|mailto:|tel:)/i.test(value) ? value : '#';
const Text=({children,muted=false}:{children:any,muted?:boolean})=><p style={{color:muted?'var(--muted)':'var(--fg)',lineHeight:1.7}}>{children}</p>;
function SectionView({s,t}:{s:Section,t:any}){
 const d=s.data||{};
 if(s.type==='hero') return <section style={{padding:'90px 24px',textAlign:'center'}}><h1 style={{fontSize:'clamp(2.4rem,7vw,5rem)',margin:0}}>{d.heading}</h1><Text muted>{d.text}</Text>{d.button&&<a href="#contact" style={{display:'inline-block',marginTop:18,padding:'13px 22px',borderRadius:12,background:t.accent,color:'#fff',textDecoration:'none'}}>{d.button}</a>}</section>;
 if(s.type==='text') return <section style={{padding:'55px 24px',maxWidth:900,margin:'auto'}}><h2>{d.heading}</h2><Text>{d.text}</Text></section>;
 if(s.type==='image') return d.url?<section style={{padding:'40px 24px',textAlign:'center'}}><img src={d.url} alt={d.alt||''} style={{maxWidth:'100%',borderRadius:18}}/></section>:null;
 if(['features','services','pricing','testimonials','faq'].includes(s.type)) return <section style={{padding:'55px 24px',maxWidth:1100,margin:'auto'}}><h2>{d.heading}</h2><div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(220px,1fr))',gap:18}}>{(d.items||[]).map((x:any,i:number)=><article key={i} style={{background:'var(--card)',padding:22,borderRadius:16,boxShadow:'0 6px 24px rgba(0,0,0,.06)'}}><Text>{x}</Text></article>)}</div></section>;
 if(s.type==='cta') return <section style={{padding:'65px 24px',textAlign:'center',background:t.accent,color:'#fff'}}><h2>{d.heading}</h2>{d.button&&<a href="#contact" style={{display:'inline-block',marginTop:10,padding:'12px 20px',borderRadius:10,background:'#fff',color:t.accent,textDecoration:'none'}}>{d.button}</a>}</section>;
 if(s.type==='contact') return <section id="contact" style={{padding:'55px 24px',maxWidth:800,margin:'auto'}}><h2>{d.heading}</h2><Text>{d.text}</Text><div style={{marginTop:20,padding:18,borderRadius:14,background:'var(--card)',border:'1px solid rgba(127,127,127,.18)'}}>Contact form available on the published site.</div></section>;
 if(s.type==='footer') return <footer style={{padding:30,textAlign:'center',borderTop:'1px solid rgba(127,127,127,.2)'}}><Text muted>{d.text}</Text></footer>;
 return null;
}
export default function WebsitePublic({site,page,preview=false}:Props){
 const settings=site.settings||{}; const branding=settings.branding||{}; const custom=settings.theme||{};
 const base=themes[site.template_key]??themes['modern-corporate'];
 const t={...base,bg:custom.background||base.bg,fg:custom.text||base.fg,accent:custom.primary||base.accent,card:custom.card||base.card};
 const sections=page.content?.sections??[];
 const pages=(site as any).pages||[];
 const seo={...(settings.seo||{}),...(page.seo||{})};
 const title=seo.title||page.title||site.name;
 const description=seo.description||'';
 const nav=pages.slice().sort((a:any,b:any)=>(a.sort_order||0)-(b.sort_order||0));
 return <><Head title={title}><meta name="description" content={description}/>{seo.keywords&&<meta name="keywords" content={seo.keywords}/>} {seo.og_image&&<meta property="og:image" content={seo.og_image}/>} {branding.favicon_url&&<link rel="icon" href={safeUrl(branding.favicon_url)}/>}</Head><div style={{'--bg':t.bg,'--fg':t.fg,'--muted':t.muted,'--accent':t.accent,'--card':t.card} as React.CSSProperties,minHeight:'100vh',background:t.bg,color:t.fg,fontFamily:'Inter,system-ui,sans-serif'}}>
  {preview&&<div style={{position:'sticky',top:0,zIndex:20,padding:'9px 14px',background:t.accent,color:'#fff',textAlign:'center',fontSize:13}}>Preview — this website is not public until published.</div>}
  <header style={{position:'sticky',top:preview?38:0,zIndex:10,background:t.card+'ee',backdropFilter:'blur(10px)',borderBottom:'1px solid rgba(127,127,127,.15)'}}>
   <div style={{maxWidth:1100,margin:'auto',padding:'15px 24px',display:'flex',gap:20,alignItems:'center'}}><strong style={{display:'flex',alignItems:'center',gap:10}}>{branding.logo_url&&<img src={safeUrl(branding.logo_url)} alt="" style={{width:34,height:34,objectFit:'contain',borderRadius:8}}/>}{site.name}</strong><nav style={{marginLeft:'auto',display:'flex',gap:16,flexWrap:'wrap'}}>{preview?null:nav.map((p:any)=><a key={p.id} href={'/sites/'+site.slug+(p.is_home?'':'/'+p.slug)} style={{color:t.fg}}>{p.title}</a>)}</nav></div>
  </header>
  <main>{sections.length?sections.map(s=><SectionView key={s.id} s={s} t={t}/>):<section style={{padding:'100px 24px',textAlign:'center'}}><h1>{site.name}</h1><Text muted>Add sections in Website Builder to publish your content.</Text></section>}</main>
 </div>;
}