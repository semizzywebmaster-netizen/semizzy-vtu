import { Head, useForm } from '@inertiajs/react';
export default function PhoneChangeRequest({currentPhone,pending}:{currentPhone?:string;pending?:{status:string;requested_phone:string;reason:string}|null}){
 const form=useForm({phone:'',reason:'',screenshot:null as File|null});
 const submit=(e:React.FormEvent)=>{e.preventDefault();form.post('/profile/phone-change-request',{forceFormData:true});};
 return <main className="min-h-screen bg-slate-50 p-6"><Head title="Request phone number change"/><div className="mx-auto max-w-xl rounded-3xl bg-white p-7 shadow">
 <h1 className="text-2xl font-black">Change phone number</h1><p className="mt-2 text-sm text-slate-600">Current number: <b>{currentPhone||'Not set'}</b></p>
 <div className="mt-4 rounded-xl bg-amber-50 p-4 text-sm">Phone numbers cannot be changed directly. Send a request to Admin with a reason and optional supporting screenshot.</div>
 {pending?<div className="mt-5 rounded-xl border p-4 text-sm"><b>Pending request</b><p className="mt-1">Requested: {pending.requested_phone}</p><p>Reason: {pending.reason}</p></div>:
 <form onSubmit={submit} className="mt-6 space-y-4">
 <label className="block text-sm font-semibold">New WhatsApp/phone number<input className="mt-1 w-full rounded-xl border p-3" placeholder="08012345678" value={form.data.phone} onChange={e=>form.setData('phone',e.target.value)} required/>{form.errors.phone&&<p className="text-sm text-red-600">{form.errors.phone}</p>}</label>
 <label className="block text-sm font-semibold">Reason<textarea className="mt-1 w-full rounded-xl border p-3" rows={5} value={form.data.reason} onChange={e=>form.setData('reason',e.target.value)} required/></label>
 <label className="block text-sm font-semibold">Supporting screenshot (optional)<input className="mt-1 w-full rounded-xl border p-3" type="file" accept="image/*" onChange={e=>form.setData('screenshot',e.target.files?.[0]||null)}/></label>
 <button disabled={form.processing} className="w-full rounded-xl bg-slate-900 p-3 font-semibold text-white">Send request to Admin</button>
 </form>}
 </div></main>;
}