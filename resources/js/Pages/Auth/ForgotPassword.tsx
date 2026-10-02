import { Form, Head } from '@inertiajs/react';

export default function ForgotPassword() {
    return (
        <>
            <Head title="Forgot password" />
            <main className="mx-auto flex min-h-screen max-w-md items-center px-6 py-10">
                <section className="w-full space-y-6 rounded-2xl border p-6">
                    <div>
                        <h1 className="text-2xl font-semibold">Reset your password</h1>
                        <p className="mt-2 text-sm text-muted-foreground">Enter your email and we will send reset instructions if an account exists.</p>
                    </div>
                    <Form method="post" action="/forgot-password" className="space-y-4">
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                <input name="email" type="email" required autoComplete="email" className="w-full rounded-lg border px-3 py-2" placeholder="you@example.com" />
                                {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
                                {recentlySuccessful && <p className="text-sm">Reset instructions sent.</p>}
                                <button disabled={processing} className="w-full rounded-lg border px-4 py-2">
                                    {processing ? 'Sending…' : 'Send reset link'}
                                </button>
                            </>
                        )}
                    </Form>
                </section>
            </main>
        </>
    );
}
