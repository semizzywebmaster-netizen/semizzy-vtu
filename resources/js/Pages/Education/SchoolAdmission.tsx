import {Head,Link,router} from '@inertiajs/react';
import {useState} from 'react';
export default function SchoolAdmission({institutions=[],sessions=[],academicUnits=[],departments=[],admissions,filters={}}:any){
 const [f,setF]=useState<any>(filters);
 const units=academicUnits.filter((x:any)=>!f.institution_id||String(x.institution_id)===String(f.institution_id));
 const deps=departments.filter((x:any)=>!f.institution_id||String(x.institution_id)===String(f.institution_id)).filter((x:any)=>!f.academic_unit_id||String(x.academic_unit_id)===String(f.academic_unit_id));
 const apply=(e:any)=>{e.preventDefault();router.get('/education/admission',f,{preserveState:true,preserveScroll:true});};
 const clear=()=>{setF({});router.get('/education/admission',{}, {preserveState:true});};
 return <><Head title="School Admission"/><main className="space-y-6 p-6">
 <header><h1 className="text-3xl font-bold">School Admission</h1><p className="opacity-70">Find verified, published admission requirements by institution, academic structure, programme and session.</p></header>
 <form onSubmit={apply} className="grid gap-3 rounded-xl border p-5 md:grid-cols-5">
  <input value={f.q||''} onChange={e=>setF({...f,q:e.target.value})} placeholder="Search programme" className="rounded-lg border p-3"/>
  <select value={f.institution_id||''} onChange={e=>setF({...f,institution_id:e.target.value,academic_unit_id:'',department_id:''})} className="rounded-lg border p-3"><option value="">All institutions</option>{institutions.map((x:any)=><option key={x.id} value={x.id}>{x.name}</option>)}</select>
  <select value={f.session_id||''} onChange={e=>setF({...f,session_id:e.target.value})} className="rounded-lg border p-3"><option value="">All sessions</option>{sessions.map((x:any)=><option key={x.id} value={x.id}>{x.name}</option>)}</select>
  <select value={f.academic_unit_id||''} onChange={e=>setF({...f,academic_unit_id:e.target.value,department_id:''})} className="rounded-lg border p-3"><option value="">All faculties / schools</option>{units.map((x:any)=><option key={x.id} value={x.id}>{x.name}</option>)}</select>
  <select value={f.department_id||''} onChange={e=>setF({...f,department_id:e.target.value})} className="rounded-lg border p-3"><option value="">All departments</option>{deps.map((x:any)=><option key={x.id} value={x.id}>{x.name}</option>)}</select>
  <div className="flex gap-2 md:col-span-5"><button className="rounded-lg bg-black px-5 py-3 text-white">Search</button><button type="button" onClick={clear} className="rounded-lg border px-5 py-3">Clear</button></div>
 </form>
 <section className="rounded-xl border p-5"><h2 className="mb-4 text-xl font-semibold">Published programme admissions</h2>
  <div className="space-y-3">{admissions?.data?.length?admissions.data.map((a:any)=><Link key={a.id} href={'/education/admission/programmes/'+a.id} className="block rounded-lg border p-4 hover:bg-black/5"><b>{a.programme?.name}</b><div className="text-sm opacity-70">{a.programme?.institution?.name} · {a.programme?.academicUnit?.name||''} · {a.programme?.department?.name||''} · {a.academicSession?.name}</div></Link>):<div className="py-8 text-center opacity-70">No published admission records match your filters.</div>}</div>
 </section>
 </main></>}