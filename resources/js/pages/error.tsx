import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Clock3,
    FileSearch,
    LayoutDashboard,
    LogIn,
    RefreshCw,
    ServerCrash,
    ShieldAlert,
    Timer,
    Wrench,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import type { User } from '@/types';

type Status = 403 | 404 | 419 | 429 | 500 | 503;

type Content = {
    icon: LucideIcon;
    label: string;
    title: string;
    description: string;
    hints: string[];
};

const CONTENT: Record<Status, Content> = {
    403: {
        icon: ShieldAlert,
        label: 'Akses Ditolak',
        title: 'Anda tidak memiliki akses ke halaman ini',
        description:
            'Halaman atau tindakan ini hanya tersedia untuk peran atau PIC tertentu pada pengadaan terkait.',
        hints: [
            'Pastikan Anda masuk dengan akun yang benar.',
            'Bila Anda PIC pengadaan ini, minta TL terkait menunjuk Anda terlebih dahulu.',
            'Hubungi Administrator bila hak akses Anda perlu ditambahkan.',
        ],
    },
    404: {
        icon: FileSearch,
        label: 'Tidak Ditemukan',
        title: 'Halaman tidak ditemukan',
        description:
            'Alamat yang Anda buka tidak tersedia. Data mungkin sudah dihapus, dipindahkan, atau alamatnya salah ketik.',
        hints: [
            'Periksa kembali alamat yang Anda buka.',
            'Buka data melalui menu di Dashboard, bukan dari tautan lama.',
        ],
    },
    419: {
        icon: Clock3,
        label: 'Sesi Berakhir',
        title: 'Sesi halaman sudah berakhir',
        description:
            'Halaman terlalu lama dibiarkan terbuka sehingga sesinya berakhir demi keamanan. Muat ulang halaman, lalu ulangi tindakan Anda.',
        hints: [
            'Isian yang belum tersimpan perlu diisi ulang.',
            'Bila masih berulang, keluar lalu masuk kembali.',
        ],
    },
    429: {
        icon: Timer,
        label: 'Terlalu Banyak Permintaan',
        title: 'Terlalu banyak permintaan dalam waktu singkat',
        description:
            'Demi keamanan, sistem membatasi jumlah permintaan dalam waktu tertentu. Tunggu sebentar sebelum mencoba lagi.',
        hints: [
            'Hindari menekan tombol yang sama berulang kali.',
            'Bila terjadi saat masuk, periksa kembali email dan kata sandi Anda.',
        ],
    },
    500: {
        icon: ServerCrash,
        label: 'Kesalahan Server',
        title: 'Terjadi kesalahan pada sistem',
        description:
            'Permintaan Anda tidak dapat diproses saat ini. Kesalahan sudah tercatat dan akan ditindaklanjuti.',
        hints: [
            'Coba muat ulang halaman beberapa saat lagi.',
            'Bila terus terjadi, laporkan kepada Administrator beserta waktu kejadiannya.',
        ],
    },
    503: {
        icon: Wrench,
        label: 'Dalam Pemeliharaan',
        title: 'Sistem sedang dalam pemeliharaan',
        description:
            'Sistem sementara tidak dapat diakses karena sedang diperbarui. Silakan kembali beberapa saat lagi.',
        hints: ['Data Anda tetap aman selama pemeliharaan berlangsung.'],
    },
};

/**
 * The application's own error page (403, 404, 419, 429, 500, 503), in the
 * corporate blue and white of the rest of the app, with a way back: the
 * previous page, the Dashboard (or the login page for a visitor), and for
 * a 429 a countdown until a retry is allowed.
 */
export default function ErrorPage({
    status,
    reason = null,
    retryAfter = null,
}: {
    status: Status;
    /** Our own reason for a refusal, when one was given. */
    reason?: string | null;
    /** Seconds until a retry is allowed (429). */
    retryAfter?: number | null;
}) {
    const { auth } = usePage<{ auth?: { user: User | null } }>().props;
    const signedIn = Boolean(auth?.user);
    const content = CONTENT[status] ?? CONTENT[500];
    const Icon = content.icon;

    const [waiting, setWaiting] = useState(retryAfter ?? 0);

    useEffect(() => {
        if (waiting <= 0) {
            return;
        }

        const timer = window.setTimeout(() => setWaiting(waiting - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [waiting]);

    const reload = () => window.location.reload();
    const back = () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.assign(signedIn ? dashboard().url : login().url);
        }
    };

    return (
        <>
            <Head title={`${status} · ${content.label}`} />

            <div className="flex min-h-svh flex-col bg-background text-foreground">
                <header className="border-b border-border bg-card">
                    <div className="mx-auto flex h-16 w-full max-w-5xl items-center gap-3 px-4 sm:px-6">
                        <span className="flex size-9 items-center justify-center rounded-md bg-white p-1">
                            <AppLogoIcon className="h-7" />
                        </span>
                        <div className="leading-tight">
                            <p className="text-sm font-semibold">SIM-SCM</p>
                            <p className="text-[0.6875rem] font-medium tracking-[0.06em] text-muted-foreground uppercase">
                                UP Kendari
                            </p>
                        </div>
                    </div>
                </header>

                <main className="flex flex-1 items-center justify-center px-4 py-10 sm:px-6">
                    <section className="w-full max-w-3xl overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                        <div className="h-1 bg-primary" />
                        <div className="grid gap-8 p-6 sm:p-10 md:grid-cols-[minmax(0,11rem)_1fr]">
                            <div className="flex flex-row items-center gap-4 md:flex-col md:items-start">
                                <span className="flex size-12 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Icon className="size-6" />
                                </span>
                                <div>
                                    <p className="tabular text-5xl leading-none font-semibold tracking-tight text-primary sm:text-6xl">
                                        {status}
                                    </p>
                                    <p className="mt-2 text-[0.6875rem] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                                        {content.label}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-5">
                                <div className="space-y-2">
                                    <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
                                        {content.title}
                                    </h1>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {content.description}
                                    </p>
                                </div>

                                {reason && (
                                    <p className="rounded-md border border-border bg-muted px-3 py-2 text-sm">
                                        {reason}
                                    </p>
                                )}

                                {status === 429 && waiting > 0 && (
                                    <p className="text-sm font-medium">
                                        Coba lagi dalam{' '}
                                        <span className="tabular text-primary">
                                            {waiting} detik
                                        </span>
                                        .
                                    </p>
                                )}

                                <ul className="space-y-1.5 border-t border-border pt-4 text-sm text-muted-foreground">
                                    {content.hints.map((hint) => (
                                        <li key={hint} className="flex gap-2">
                                            <span className="mt-2 size-1 shrink-0 rounded-full bg-primary" />
                                            {hint}
                                        </li>
                                    ))}
                                </ul>

                                <div className="flex flex-wrap gap-2 pt-1">
                                    {[419, 429, 500, 503].includes(status) && (
                                        <Button
                                            type="button"
                                            onClick={reload}
                                            disabled={waiting > 0}
                                        >
                                            <RefreshCw className="size-4" />
                                            Muat Ulang
                                        </Button>
                                    )}
                                    {signedIn ? (
                                        <Button
                                            asChild
                                            variant={
                                                [403, 404].includes(status)
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            <Link href={dashboard()}>
                                                <LayoutDashboard className="size-4" />
                                                Ke Dashboard
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            asChild
                                            variant={
                                                [403, 404].includes(status)
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            <Link href={login()}>
                                                <LogIn className="size-4" />
                                                Masuk
                                            </Link>
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={back}
                                    >
                                        <ArrowLeft className="size-4" />
                                        Kembali
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-border bg-card">
                    <p className="mx-auto w-full max-w-5xl px-4 py-4 text-xs text-muted-foreground sm:px-6">
                        PT PLN Nusantara Power · UP Kendari
                    </p>
                </footer>
            </div>
        </>
    );
}
