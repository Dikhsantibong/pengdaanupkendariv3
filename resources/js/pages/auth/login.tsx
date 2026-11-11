import { Form, Head } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    /** Off unless LOGIN_PASSKEYS_ENABLED is set in .env. */
    canUsePasskeys: boolean;
    /** Set while the administrator closed the application for maintenance. */
    maintenance: { message: string; until: string | null } | null;
};

/* The showcase panel beside this form is rendered by AuthShowcaseLayout. */

export default function Login({
    status,
    canResetPassword,
    canUsePasskeys,
    maintenance,
}: Props) {
    return (
        <>
            <Head title="Masuk" />

            {maintenance && (
                <div
                    role="alert"
                    className="mb-6 flex items-start gap-3 rounded-md border border-amber-500/40 bg-amber-500/10 p-4"
                >
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-amber-500/15 text-amber-700 dark:text-amber-400">
                        <Wrench className="size-4" />
                    </span>
                    <div className="space-y-1">
                        <p className="text-sm font-semibold text-foreground">
                            Aplikasi Sedang dalam Pemeliharaan
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {maintenance.message}
                        </p>
                        {maintenance.until && (
                            <p className="text-xs font-medium text-amber-800 dark:text-amber-300">
                                Perkiraan selesai:{' '}
                                {formatDateTime(maintenance.until)}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Untuk sementara hanya Administrator yang dapat
                            masuk.
                        </p>
                    </div>
                </div>
            )}

            {canUsePasskeys && <PasskeyVerify />}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Alamat Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Kata Sandi</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Lupa kata sandi?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Kata sandi"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Ingat saya</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Masuk
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Masuk ke Sistem',
    description:
        'Gunakan akun yang terdaftar untuk mengakses data pengadaan Anda.',
};
