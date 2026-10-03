import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Props = {
  checks: Record<string, boolean>;
  migrations_table: boolean;
  app_url: string | null;
};

export default function Setup({ checks, migrations_table, app_url }: Props) {
  const keyForm = useForm({});
  const migrate = useForm({ confirm: false });
  const admin = useForm({ name: '', email: '', password: '', password_confirmation: '' });
  const ready = Object.values(checks).every(Boolean);
  const checkLabels: Record<string, string> = {
    php: 'PHP 8.4+',
    pdo: 'PDO',
    pdo_mysql: 'PDO MySQL',
    mbstring: 'mbstring',
    openssl: 'OpenSSL',
    json: 'JSON',
    app_key: 'Application key',
    app_url: 'Application URL',
    storage: 'Storage writable',
    bootstrap_cache: 'Bootstrap cache writable',
    database: 'Database connection',
  };

  const generateKey = () => {
    keyForm.post('/setup/key');
  };

  const runMigrations = (e: FormEvent) => {
    e.preventDefault();
    migrate.setData('confirm', true);
    migrate.post('/setup/migrate');
  };

  const createAdmin = (e: FormEvent) => {
    e.preventDefault();
    admin.post('/setup/admin');
  };

  return (
    <>
      <Head title="SEMIZZY ONE Setup" />
      <main className="min-h-screen bg-slate-950 px-5 py-10 text-white">
        <div className="mx-auto max-w-3xl space-y-6">
          <div>
            <p className="text-sm font-semibold uppercase tracking-widest text-slate-400">SEMIZZY ONE CORE V2</p>
            <h1 className="mt-2 text-3xl font-bold">Initial setup</h1>
            <p className="mt-2 text-slate-400">Complete the server checks, database migration, and first administrator setup.</p>
          </div>
          {!checks.app_key && <section className="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 className="text-lg font-semibold">Application key</h2>
            <p className="mt-2 text-sm text-slate-400">The installer can generate APP_KEY for you when the .env file is writable.</p>
            <button type="button" onClick={generateKey} disabled={keyForm.processing} className="mt-4 rounded-xl bg-white px-4 py-2 font-semibold text-slate-950 disabled:opacity-50">
              {keyForm.processing ? 'Generating…' : 'Generate application key'}
            </button>
          </section>}
          <section className="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 className="text-lg font-semibold">Environment checks</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              {Object.entries(checks).map(([key, ok]) => (
                <div key={key} className="flex items-center justify-between rounded-xl border border-slate-800 p-3">
                  <span>{checkLabels[key] ?? key.replaceAll('_', ' ')}</span><strong className={ok ? 'text-emerald-400' : 'text-amber-400'}>{ok ? 'OK' : 'Fix'}</strong>
                </div>
              ))}
            </div>
            <p className="mt-4 text-sm text-slate-400">APP_URL: {app_url || 'not configured'}</p>
            {!ready && <p className="mt-3 rounded-xl bg-amber-950/40 p-3 text-sm text-amber-200">Fix every item marked “Fix”, then refresh this page. The wizard will only run migrations when the server checks are healthy.</p>}
          </section>
          <section className="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 className="text-lg font-semibold">Database</h2>
            <p className="mt-2 text-sm text-slate-400">Migration table: {migrations_table ? 'ready' : 'not initialized'}</p>
            {!migrations_table && ready && (
              <form onSubmit={runMigrations} className="mt-4">
                <button disabled={migrate.processing} className="rounded-xl bg-white px-4 py-2 font-semibold text-slate-950 disabled:opacity-50">
                  {migrate.processing ? 'Running migrations…' : 'Run database migrations'}
                </button>
              </form>
            )}
          </section>
          {migrations_table && ready && (
            <section className="rounded-2xl border border-slate-800 bg-slate-900 p-5">
              <h2 className="text-lg font-semibold">Create initial administrator</h2>
              <form onSubmit={createAdmin} className="mt-4 grid gap-3">
                <input required value={admin.data.name} onChange={e => admin.setData('name', e.target.value)} placeholder="Full name" className="rounded-xl bg-slate-800 p-3" />
                <input required type="email" value={admin.data.email} onChange={e => admin.setData('email', e.target.value)} placeholder="Email" className="rounded-xl bg-slate-800 p-3" />
                <input required minLength={12} type="password" value={admin.data.password} onChange={e => admin.setData('password', e.target.value)} placeholder="Password (12+ characters)" className="rounded-xl bg-slate-800 p-3" />
                <input required minLength={12} type="password" value={admin.data.password_confirmation} onChange={e => admin.setData('password_confirmation', e.target.value)} placeholder="Confirm password" className="rounded-xl bg-slate-800 p-3" />
                <button disabled={admin.processing} className="rounded-xl bg-white px-4 py-2 font-semibold text-slate-950 disabled:opacity-50">
                  {admin.processing ? 'Creating…' : 'Create administrator'}
                </button>
              </form>
            </section>
          )}
        </div>
      </main>
    </>
  );
}
