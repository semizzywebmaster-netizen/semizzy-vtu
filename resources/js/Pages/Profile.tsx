import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

type SharedProps = { navigation?: { unreadNotifications?: number } };
type Props = {
  tier: number;
  tiers: { id:number; name:string; requirements:string[]; upgradeLabel:string|null; current:boolean }[];
  user: {
    name:string; username:string; email:string; phone:string; avatarUrl:string|null; address:string|null; city:string|null; state:string|null;
    country:string|null; postalCode:string|null; dateOfBirth:string|null; gender:string|null; occupation:string|null;
    identityType:string|null; identityNumber:string|null; identityDocumentUrl:string|null; kycStatus:string;
    role:string; status:string; emailVerifiedAt:string|null; phoneVerifiedAt:string|null; referralCode:string; referralLink:string; hasTransactionPin:boolean; isApiUser:boolean;
  };
};

const kycLabel=(v:string)=>v==='pending'?'Pending review':v==='verified'?'Verified':v==='rejected'?'Rejected':'Not started';

export default function Profile({user,tier,tiers}:Props){
  const unreadCount=usePage<SharedProps>().props.navigation?.unreadNotifications??0;
  const [passwordOtpSent, setPasswordOtpSent] = useState(false);
  const [passwordOtpSending, setPasswordOtpSending] = useState(false);
  const passwordForm=useForm({current_password:'',password:'',password_confirmation:'',otp_code:''});
  const requestPasswordOtp=()=>{setPasswordOtpSending(true);router.post('/security/otp/request',{purpose:'password_change'},{preserveScroll:true,onSuccess:()=>setPasswordOtpSent(true),onFinish:()=>setPasswordOtpSending(false)});};
  const profileForm=useForm<{
    email:string; phone:string; avatar:File|null; address:string; city:string; state:string; country:string; postal_code:string; date_of_birth:string;
    gender:string; occupation:string; identity_type:string; identity_number:string; identity_document:File|null; transaction_pin:string;
  }>({
    email:user.email,phone:user.phone??'',avatar:null,address:user.address??'',city:user.city??'',state:user.state??'',country:user.country??'Nigeria',
    postal_code:user.postalCode??'',date_of_birth:user.dateOfBirth??'',gender:user.gender??'',occupation:user.occupation??'',
    identity_type:user.identityType??'',identity_number:user.identityNumber??'',identity_document:null,transaction_pin:'',
  });

  const submitProfile=(event:React.FormEvent)=>{
    event.preventDefault();
    profileForm.post('/profile',{forceFormData:true,preserveScroll:true});
  };
  const submitPassword=(event:React.FormEvent)=>{
    event.preventDefault();
    passwordForm.post('/profile/password',{preserveScroll:true,onSuccess:()=>passwordForm.reset()});
  };
  const copy=(value:string)=>{void navigator.clipboard?.writeText(value);};
  const identityLocked=Boolean(user.identityNumber);
  const phoneLocked=Boolean(user.phone);
  const maskedIdentity=identityLocked ? '••••••••' + user.identityNumber!.slice(-4) : '';
  const avatarPreview=profileForm.data.avatar ? URL.createObjectURL(profileForm.data.avatar) : user.avatarUrl;

  return <main className="min-h-screen bg-slate-50 p-4 pb-24 md:p-8">
    <Head title="Profile" />
    <div className="mx-auto max-w-4xl">
      <div className="mb-4 flex justify-end"><button type="button" onClick={()=>router.post('/logout')} className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Logout</button></div>

      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <p className="text-sm font-semibold text-indigo-600">SEMIZZY ONE</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">Profile & account</h1>
        <p className="mt-2 text-sm text-slate-500">Edit your personal information below. Confidential identity fields are protected permanently after they are saved.</p>

        <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center">
          <div className="h-24 w-24 overflow-hidden rounded-full border-4 border-slate-100 bg-slate-100">
            {avatarPreview?<img src={avatarPreview} alt="Profile avatar" className="h-full w-full object-cover" />:<div className="flex h-full w-full items-center justify-center text-3xl font-black text-slate-400">{user.name.slice(0,1).toUpperCase()}</div>}
          </div>
          <div><h2 className="font-black text-slate-900">{user.name}</h2><p className="text-sm text-slate-500">@{user.username}</p><label className="mt-3 inline-flex cursor-pointer rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:border-indigo-400"><input type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={e=>profileForm.setData('avatar',e.target.files?.[0]??null)} />Change avatar</label></div>
        </div>

        <form onSubmit={submitProfile} className="mt-8 space-y-6">
          <div><h3 className="text-lg font-black text-slate-900">Confidential account information</h3><p className="mt-1 text-xs text-slate-500">These fields are protected. Full name, username and phone cannot be edited once established. Identity number becomes permanently locked after first save.</p></div>
          <div className="grid gap-4 md:grid-cols-2">
            <LockedField label="Full name" value={user.name} />
            <LockedField label="Username" value={'@'+user.username} />
            {phoneLocked ? <LockedField label="Phone number" value={user.phone} /> : <div><label className="text-xs font-bold uppercase tracking-wide text-slate-500">Phone number <span className="normal-case font-medium">(set once)</span></label><input type="tel" className="field mt-1" value={profileForm.data.phone} onChange={e=>profileForm.setData('phone',e.target.value)} placeholder="Phone number" />{profileForm.errors.phone&&<p className="mt-1 text-sm text-red-600">{profileForm.errors.phone}</p>}</div>}
            <div><label className="text-xs font-bold uppercase tracking-wide text-slate-500">Email address <span className="normal-case font-medium">(editable)</span></label><input type="email" className="mt-1 w-full rounded-xl border border-slate-300 p-3" value={profileForm.data.email} onChange={e=>profileForm.setData('email',e.target.value)} required />{profileForm.errors.email&&<p className="mt-1 text-sm text-red-600">{profileForm.errors.email}</p>}</div>
          </div>

          <div><h3 className="text-lg font-black text-slate-900">Personal information</h3><p className="mt-1 text-sm text-slate-500">You can update these details whenever necessary.</p></div>
          <div className="grid gap-4 md:grid-cols-2">
            <Field label="Date of birth"><input type="date" className="field" value={profileForm.data.date_of_birth} onChange={e=>profileForm.setData('date_of_birth',e.target.value)} /></Field>
            <Field label="Gender"><select className="field" value={profileForm.data.gender} onChange={e=>profileForm.setData('gender',e.target.value)}><option value="">Select gender</option><option value="male">Male</option><option value="female">Female</option><option value="non_binary">Non-binary</option><option value="prefer_not_to_say">Prefer not to say</option></select></Field>
            <Field label="Occupation"><input className="field" value={profileForm.data.occupation} onChange={e=>profileForm.setData('occupation',e.target.value)} maxLength={120} placeholder="Occupation" /></Field>
          </div>

          <div><h3 className="text-lg font-black text-slate-900">Address</h3></div>
          <div className="grid gap-4 md:grid-cols-2">
            <Field label="Address" full><textarea className="field min-h-24" value={profileForm.data.address} onChange={e=>profileForm.setData('address',e.target.value)} maxLength={1000} /></Field>
            <Field label="City"><input className="field" value={profileForm.data.city} onChange={e=>profileForm.setData('city',e.target.value)} /></Field>
            <Field label="State"><input className="field" value={profileForm.data.state} onChange={e=>profileForm.setData('state',e.target.value)} /></Field>
            <Field label="Country"><input className="field" value={profileForm.data.country} onChange={e=>profileForm.setData('country',e.target.value)} /></Field>
            <Field label="Postal code"><input className="field" value={profileForm.data.postal_code} onChange={e=>profileForm.setData('postal_code',e.target.value)} /></Field>
          </div>

          <div className="rounded-2xl border border-indigo-100 bg-indigo-50 p-5">
            <div className="flex flex-wrap items-center justify-between gap-3"><div><h3 className="font-black text-slate-900">KYC / identity verification</h3><p className="mt-1 text-sm text-slate-600">Your identity number is write-once. Documents can be replaced and will trigger a fresh review.</p></div><span className="rounded-full bg-white px-3 py-1.5 text-xs font-black text-indigo-700">{kycLabel(user.kycStatus)}</span></div>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <Field label="Identity type"><select className="field" value={profileForm.data.identity_type} onChange={e=>profileForm.setData('identity_type',e.target.value)}><option value="">Select document</option><option value="nin">NIN</option><option value="bvn">BVN</option><option value="passport">International Passport</option><option value="drivers_license">Driver's Licence</option><option value="voters_card">Voter's Card</option><option value="other">Other</option></select></Field>
              <div><label className="text-xs font-bold uppercase tracking-wide text-slate-500">Identity number</label><input className="field mt-1 disabled:bg-slate-100 disabled:text-slate-500" value={identityLocked?maskedIdentity:profileForm.data.identity_number} disabled={identityLocked} onChange={e=>profileForm.setData('identity_number',e.target.value)} placeholder="Enter identity number" />{identityLocked&&<p className="mt-1 text-xs font-semibold text-amber-700">Locked permanently after saving.</p>}{profileForm.errors.identity_number&&<p className="mt-1 text-sm text-red-600">{profileForm.errors.identity_number}</p>}</div>
              <div><label className="text-xs font-bold uppercase tracking-wide text-slate-500">Identity document</label><input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm" onChange={e=>profileForm.setData('identity_document',e.target.files?.[0]??null)} />{user.identityDocumentUrl&&<a href={user.identityDocumentUrl} target="_blank" rel="noreferrer" className="mt-2 inline-block text-sm font-bold text-indigo-700">View current document</a>}<p className="mt-1 text-xs text-slate-500">JPG, PNG, WEBP or PDF · max 10MB</p></div>
            </div>
          </div>

          <label className="block"><span className="text-xs font-bold uppercase tracking-wide text-slate-500">Transaction PIN</span><input required inputMode="numeric" pattern="\\d{4}" maxLength={4} type="password" className="field mt-1" placeholder="4-digit PIN" value={profileForm.data.transaction_pin} onChange={e=>profileForm.setData('transaction_pin',e.target.value.replace(/\\D/g,'').slice(0,4))}/><span className="mt-1 block text-xs text-slate-500">Required to save protected profile changes.</span></label>{Object.values(profileForm.errors).length>0&&<div className="rounded-xl bg-red-50 p-3 text-sm text-red-700">Please correct the highlighted profile fields and try again.</div>}
          <button disabled={profileForm.processing} className="w-full rounded-xl bg-indigo-600 p-3.5 font-black text-white disabled:opacity-50">{profileForm.processing?'Saving profile…':'Save profile changes'}</button>
          {profileForm.recentlySuccessful&&<p className="text-center text-sm font-bold text-emerald-700">Profile updated successfully.</p>}
        </form>
      </section>

      {user.isApiUser&&<section className="mt-5 rounded-3xl border border-indigo-200 bg-indigo-50 p-6"><p className="text-xs font-bold uppercase tracking-wider text-indigo-700">API USER</p><h2 className="mt-1 text-xl font-black">Platform API</h2><p className="mt-1 text-sm text-slate-600">Your API User level has platform API-key access. API keys are private and shown only once when created.</p><Link href="/api-access" className="mt-4 inline-flex rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white">Manage API keys →</Link></section>}

<section className="mt-5 rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
        <p className="text-xs font-bold uppercase tracking-wider text-amber-700">Account tier</p><div className="mt-1 flex items-center justify-between gap-4"><div><h2 className="text-xl font-black text-slate-900">Tier {tier}</h2><p className="mt-1 text-sm text-slate-600">Your account tier and verification requirements belong here.</p></div><span className="rounded-full bg-amber-400 px-3 py-1.5 text-xs font-black text-amber-950">TIER {tier}</span></div>
        <div className="mt-5 grid gap-3 sm:grid-cols-2">{tiers.map(item=><article key={item.id} className={'rounded-2xl border p-4 '+(item.current?'border-amber-300 bg-white':'border-slate-200 bg-white/70')}><div className="flex items-center justify-between gap-3"><h3 className="font-black">{item.name}</h3>{item.current&&<span className="rounded-full bg-amber-400 px-2 py-1 text-[10px] font-black text-amber-950">CURRENT</span>}</div><p className="mt-2 text-xs font-semibold uppercase text-slate-400">Requirements</p><ul className="mt-1 space-y-1 text-sm text-slate-600">{item.requirements.map(requirement=><li key={requirement}>• {requirement}</li>)}</ul>{!item.current&&item.id>tier&&item.upgradeLabel&&<Link href="/support?subject=Tier%20Upgrade" className="mt-3 inline-flex rounded-xl bg-slate-900 px-3 py-2 text-xs font-black text-white">{item.upgradeLabel} →</Link>}</article>)}</div>
      </section>

      <section className="mt-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Referral</p><h2 className="mt-1 text-xl font-black">Invite friends and earn through referrals</h2><div className="mt-5 grid gap-3 sm:grid-cols-2"><div className="rounded-2xl bg-slate-50 p-4"><p className="text-xs font-semibold text-slate-500">Your referral code</p><div className="mt-2 flex items-center justify-between gap-3"><span className="font-black tracking-wider">{user.referralCode}</span><button type="button" onClick={()=>copy(user.referralCode)} className="rounded-lg bg-white px-3 py-2 text-xs font-bold shadow-sm">Copy</button></div></div><div className="rounded-2xl bg-slate-50 p-4"><p className="text-xs font-semibold text-slate-500">Your referral link</p><div className="mt-2 flex items-center justify-between gap-3"><span className="truncate text-xs font-medium text-slate-700">{user.referralLink}</span><button type="button" onClick={()=>copy(user.referralLink)} className="rounded-lg bg-white px-3 py-2 text-xs font-bold shadow-sm">Copy</button></div></div></div></section>

      <section className="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Security PIN</p><h2 className="mt-1 text-lg font-black">Transaction PIN</h2><p className="mt-1 text-sm text-slate-600">{user.hasTransactionPin?'Your 4-digit transaction PIN is active.':'Set a 4-digit transaction PIN before transactions or protected profile changes.'}</p><Link href="/profile/transaction-pin" className="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">{user.hasTransactionPin?'Change transaction PIN':'Set transaction PIN'} →</Link></section>

<form onSubmit={submitPassword} className="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 className="text-lg font-bold text-slate-900">Change password</h2><p className="mt-1 text-sm text-slate-600">Password changes require your current password and a one-time OTP sent to your registered email.</p><Link href="/forgot-password" className="mt-2 inline-block text-sm font-bold text-indigo-700 hover:underline">Forgot your current password? Reset with email OTP →</Link><button type="button" onClick={requestPasswordOtp} disabled={passwordOtpSending} className="mt-4 w-full rounded-xl border border-indigo-200 bg-indigo-50 p-3 font-bold text-indigo-700 disabled:opacity-50">{passwordOtpSending?'Sending OTP…':passwordOtpSent?'Resend password OTP':'Send password OTP'}</button>{passwordOtpSent&&<p className="mt-2 text-sm font-semibold text-emerald-700">OTP sent. It expires in 10 minutes.</p>}<div className="mt-5 space-y-3"><input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="current-password" placeholder="Current password" value={passwordForm.data.current_password} onChange={e=>passwordForm.setData('current_password',e.target.value)} />{passwordForm.errors.current_password&&<p className="text-sm text-red-600">{passwordForm.errors.current_password}</p>}<input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="new-password" placeholder="New password (12+ chars, upper/lower/number/symbol)" value={passwordForm.data.password} onChange={e=>passwordForm.setData('password',e.target.value)} />{passwordForm.errors.password&&<p className="text-sm text-red-600">{passwordForm.errors.password}</p>}<input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="new-password" placeholder="Confirm new password" value={passwordForm.data.password_confirmation} onChange={e=>passwordForm.setData('password_confirmation',e.target.value)} />{passwordForm.errors.password_confirmation&&<p className="text-sm text-red-600">{passwordForm.errors.password_confirmation}</p>}<input required inputMode="numeric" pattern="\\d{6}" maxLength={6} type="text" autoComplete="one-time-code" className="w-full rounded-xl border border-slate-300 p-3 tracking-[0.3em]" placeholder="6-digit OTP" value={passwordForm.data.otp_code} onChange={e=>passwordForm.setData('otp_code',e.target.value.replace(/\\D/g,'').slice(0,6))}/>{passwordForm.errors.otp_code&&<p className="text-sm text-red-600">{passwordForm.errors.otp_code}</p>}</div><button disabled={passwordForm.processing || passwordForm.data.otp_code.length!==6} className="mt-5 w-full rounded-xl bg-slate-900 p-3 font-semibold text-white disabled:opacity-50">{passwordForm.processing?'Updating…':'Change password securely'}</button></form>

    </div>
  </main>;
}

function LockedField({label,value,locked=true}:{label:string;value:string;locked?:boolean}){return <div><label className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</label><div className="mt-1 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-100 p-3 text-slate-600"><span className="min-w-0 flex-1 truncate">{value}</span>{locked&&<span title="Confidential and locked" aria-label="Confidential and locked">🔒</span>}</div></div>;}
function Field({label,children,full=false}:{label:string;children:React.ReactNode;full?:boolean}){return <label className={full?'block md:col-span-2':'block'}><span className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</span>{children}</label>;}
