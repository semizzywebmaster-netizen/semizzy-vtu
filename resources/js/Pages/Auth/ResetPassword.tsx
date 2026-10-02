import { Form, Head } from '@inertiajs/react';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
    return (
        <>
            <Head title="Set new password" />
            <main className="mx-auto flex min-h-screen max-w-md items-center px-6 py-10">
                <section className="w-full space-y-6 rounded-2xl border p-6">
                    <div>
                        <h1 className="text-2xl font-semibold">Set a new password</h1>
                        <p className="mt-2 text-sm text-muted-foreground">Choose a new password for your account.</p>
                    </div>
                    <Form method="post" action="/reset-password" className="space-y-4">
                        {({ errors, processing }) => (
                            <>
                                <input type="hidden" name="token" value={token} />
                                <input name="email" type="email" defaultValue={email} required autoComplete="email" className="w-full rounded-lg border px-3 py-2" />
                                {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
                                <input name="password" type="password" required autoComplete="new-password" className="w-full rounded-lg border px-3 py-2" placeholder="New password" />
                                {errors.password && <p className="text-sm text-red-600">{errors.password}</p>}
                                <input name="password_confirmation" type="password" required autoComplete="new-password" className="w-full rounded-lg border px-3 py-2" placeholder="Confirm password" />
                                <button disabled={processing} className="w-full rounded-lg border px-4 py-2">
                                    {processing ? 'Saving…' : 'Reset password'}
                                </button>
                            </>
                        )}
                    </Form>
                </section>
            </main>
        </>
    );
}
