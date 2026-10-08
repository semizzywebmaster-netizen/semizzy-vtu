import React, { useState } from 'react';
import { Head } from '@inertiajs/react';

type Requirement = { key: string; label: string; type: string; required?: boolean; options?: string[]; accept?: string };
type Service = { id: number; name: string; agency?: string; description?: string; price: string; currency: string; fulfillment_mode?: string; requirements?: Requirement[] };
type Document = { id: number; document_type: string; status: string; original_name?: string };
type Certificate = { id: number; certificate_type: string; certificate_number?: string; status: string };
type Application = {
  id: number; reference: string; status: string; payment_status: string; amount: string; currency: string;
  service: Service; documents?: Document[]; certificates?: Certificate[];
};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function post(url: string, body: unknown) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
    body: JSON.stringify(body),
  });
  const json = await response.json();
  if (!response.ok) throw new Error(json.message || 'Request failed');
  return json.data;
}

function Field({ requirement, value, onChange }: { requirement: Requirement; value: unknown; onChange: (value: unknown) => void }) {
  const base = 'mt-1 w-full rounded-lg border p-2 dark:border-slate-700 dark:bg-slate-900';
  if (requirement.type === 'textarea') {
    return <textarea className={base} required={!!requirement.required} value={String(value || '')} onChange={(e) => onChange(e.target.value)} />;
  }
  if (requirement.type === 'select') {
    return <select className={base} required={!!requirement.required} value={String(value || '')} onChange={(e) => onChange(e.target.value)}>
      <option value="">Select</option>
      {(requirement.options || []).map((option) => <option key={option} value={option}>{option}</option>)}
    </select>;
  }
  if (requirement.type === 'checkbox') {
    return <input type="checkbox" checked={!!value} onChange={(e) => onChange(e.target.checked)} />;
  }
  return <input className={base} type={requirement.type === 'json' ? 'text' : requirement.type} required={!!requirement.required} value={String(value || '')} onChange={(e) => onChange(e.target.value)} accept={requirement.accept} />;
}

export default function GovernmentServices({ services = [] }: { services: Service[] }) {
  const [service, setService] = useState<Service | null>(null);
  const [application, setApplication] = useState<Application | null>(null);
  const [data, setData] = useState<Record<string, unknown>>({});
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');

  const start = async () => {
    if (!service) return;
    setBusy(true);
    try {
      setApplication(await post('/government-services/' + service.id + '/apply', { data }));
      setMessage('Application created.');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Unable to create application.');
    } finally { setBusy(false); }
  };

  const pay = async () => {
    if (!application) return;
    setBusy(true);
    try {
      setApplication(await post('/government-services/applications/' + application.id + '/pay', {}));
      setMessage('Payment completed.');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Payment failed.');
    } finally { setBusy(false); }
  };

  const action = async (path: string) => {
    if (!application) return;
    setBusy(true);
    try {
      setApplication(await post('/government-services/applications/' + application.id + path, {}));
      setMessage('Application updated.');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Request failed.');
    } finally { setBusy(false); }
  };

  const upload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    if (!application || !event.target.files?.[0]) return;
    const file = event.target.files[0];
    const documentType = window.prompt('Document type', file.name.replace(/\.[^.]+$/, '')) || 'supporting_document';
    const form = new FormData();
    form.append('document_type', documentType);
    form.append('document', file);
    setBusy(true);
    try {
      const response = await fetch('/government-services/applications/' + application.id + '/documents', {
        method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: form,
      });
      const json = await response.json();
      if (!response.ok) throw new Error(json.message || 'Upload failed');
      const refreshed = await fetch('/government-services/applications/' + application.id, { headers: { Accept: 'application/json' } });
      const payload = await refreshed.json();
      setApplication(payload.data);
      setMessage('Document uploaded.');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Upload failed.');
    } finally { setBusy(false); event.target.value = ''; }
  };

  return <>
    <Head title="Government Registration & Certificates" />
    <main className="space-y-6 p-4 md:p-6">
      <header><h1 className="text-2xl font-bold">Government Registration & Certificates</h1><p className="text-sm text-slate-500">Services are supplied through configured government providers or controlled manual fulfillment.</p></header>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {services.map((item) => <article key={item.id} className="rounded-xl border p-4 dark:border-slate-800">
          <h2 className="font-semibold">{item.name}</h2><p className="text-sm text-slate-500">{item.agency || 'Government service'}</p>
          {item.description && <p className="mt-2 text-sm">{item.description}</p>}<p className="mt-3 font-semibold">{item.currency} {item.price}</p>
          <button className="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white" onClick={() => { setService(item); setData({}); setMessage(''); }}>Start application</button>
        </article>)}
      </div>

      {service && !application && <div className="fixed inset-0 z-50 overflow-auto bg-black/50 p-4">
        <div className="mx-auto max-w-2xl rounded-2xl bg-white p-5 dark:bg-slate-900">
          <div className="flex justify-between"><div><h2 className="text-xl font-bold">{service.name}</h2><p className="text-sm text-slate-500">{service.agency}</p></div><button onClick={() => setService(null)}>Close</button></div>
          <div className="mt-5 space-y-4">
            {(service.requirements || []).map((requirement) => <label key={requirement.key} className="block text-sm font-medium">{requirement.label}{requirement.required && <span className="text-red-500"> *</span>}<Field requirement={requirement} value={data[requirement.key]} onChange={(value) => setData({ ...data, [requirement.key]: value })} /></label>)}
            {!service.requirements?.length && <p className="text-sm text-slate-500">No additional requirements configured. Admin can add them.</p>}
            <p className="text-sm text-amber-700">Requirements are configurable by Admin and should be verified against the issuing authority before submission.</p>
            {message && <p className="text-sm">{message}</p>}
            <button disabled={busy} onClick={start} className="w-full rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white">{busy ? 'Processing…' : 'Create application'}</button>
          </div>
        </div>
      </div>}

      {application && <div className="fixed inset-0 z-50 overflow-auto bg-black/50 p-4">
        <div className="mx-auto max-w-2xl rounded-2xl bg-white p-5 dark:bg-slate-900">
          <div className="flex justify-between"><h2 className="text-xl font-bold">{application.reference}</h2><button onClick={() => { setApplication(null); setService(null); }}>Close</button></div>
          <p className="mt-2">Status: <b>{application.status}</b></p><p>Amount: <b>{application.currency} {application.amount}</b></p><p>Payment: <b>{application.payment_status}</b></p>
          {message && <p className="mt-3 text-sm">{message}</p>}
          {application.payment_status !== 'paid' && <button disabled={busy} onClick={pay} className="mt-5 w-full rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white">{busy ? 'Processing…' : 'Pay from wallet'}</button>}
          <div className="mt-5 rounded-xl border p-4"><h3 className="font-semibold">Documents</h3><p className="text-xs text-slate-500">Upload PDF, JPG or PNG documents up to 10 MB.</p><input className="mt-3 block w-full text-sm" type="file" accept=".pdf,.jpg,.jpeg,.png" disabled={busy} onChange={upload}/>{(application.documents || []).map((document) => <div key={document.id} className="mt-2 flex justify-between rounded border p-2 text-sm"><span>{document.document_type}</span><b>{document.status}</b></div>)}</div>
          <div className="mt-5 flex flex-wrap gap-2">
            {application.payment_status === 'paid' && <button disabled={busy} onClick={() => action('/submit')} className="rounded-lg border px-3 py-2">Submit application</button>}
            {application.payment_status === 'paid' && application.service.fulfillment_mode !== 'manual' && <button disabled={busy} onClick={() => action('/provider-submit')} className="rounded-lg border px-3 py-2">Submit to provider</button>}
            {application.payment_status === 'paid' && <button disabled={busy} onClick={() => action('/requery')} className="rounded-lg border px-3 py-2">Requery status</button>}
          </div>
          <div className="mt-5"><h3 className="font-semibold">Certificates</h3>{application.certificates?.length ? application.certificates.map((certificate) => <div key={certificate.id} className="mt-2 flex items-center justify-between rounded border p-3 text-sm"><span>{certificate.certificate_type}{certificate.certificate_number && ' · ' + certificate.certificate_number}</span>{certificate.status === 'active' && <a className="rounded border px-2 py-1" href={'/government-services/certificates/' + certificate.id + '/download'}>Download</a>}</div>) : <p className="text-sm text-slate-500">No certificate has been issued yet.</p>}</div>
          <p className="mt-4 text-xs text-slate-500">Provider submission and requery are only available when the configured provider supports them. Manual services remain under controlled fulfillment.</p>
        </div>
      </div>}
    </main>
  </>;
}
