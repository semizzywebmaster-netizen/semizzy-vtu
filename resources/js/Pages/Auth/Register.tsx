import { Head, useForm } from '@inertiajs/react';

export default function Register() {
 const form=useForm({name:'',email:'',password:'',password_confirmation:''});
 return <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6"><Head title="Register"/><form onSubmit={e=>{e.preventDefault();form.post('/register')}} className="w-full max-w-md rounded-2xl bg-white p-7 shadow"><h1 className="text-2xl font-bold">Create account</h1>{(['name','email','password','password_confirmation'] as const).map(k=><input key={k} className="mt-4 w-full rounded-xl border p-3" type={k.includes('password')?'password':k==='email'?'email':'text'} placeholder={k.replace('_',' ')} value={form.data[k]} onChange={e=>form.setData(k,e.target.value)} />)}{form.errors.email&&<p className="mt-2 text-sm text-red-600">{form.errors.email}</p>}<button disabled={form.processing} className="mt-5 w-full rounded-xl bg-slate-900 p-3 font-semibold text-white">Create account</button></form></main>;
}
