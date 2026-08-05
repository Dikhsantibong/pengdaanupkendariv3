import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    FileCheck2,
    GaugeCircle,
    Layers,
    MonitorPlay,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import publicMonitoring from '@/routes/public-monitoring';
import type { User } from '@/types';

const capabilities = [
    {
        icon: Layers,
        title: 'Perencanaan Terstruktur',
        description:
            'Checklist dokumen perencanaan, penunjukan PIC, dan persetujuan Team Leader dalam satu alur.',
    },
    {
        icon: GaugeCircle,
        title: 'Monitoring Progres',
        description:
            'Status pengadaan, nilai HPE, dan capaian tiap tahap terpantau real time per unit tujuan.',
    },
    {
        icon: FileCheck2,
        title: 'Dokumen Otomatis',
        description:
            'Nota Dinas, TOR, RAB, HPE, UPB, RKS, hingga kontrak digenerate dari data pengadaan.',
    },
];

export default function Welcome() {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const isAuthenticated = auth.user !== null;

    return (
        <>
            <Head title="Beranda" />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="border-b border-border bg-card">
                    <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6">
                        <div className="flex items-center gap-3">
                            <span className="flex size-9 items-center justify-center rounded-md bg-white p-1">
                                <AppLogoIcon className="h-7" />
                            </span>
                            <div className="leading-tight">
                                <p className="text-sm font-semibold">
                                    Management Pengadaan
                                </p>
                                <p className="text-[0.6875rem] font-medium tracking-[0.06em] text-muted-foreground uppercase">
                                    UP Kendari
                                </p>
                            </div>
                        </div>

                        <Button asChild size="sm">
                            <Link
                                href={isAuthenticated ? dashboard() : login()}
                            >
                                {isAuthenticated ? 'Buka Dashboard' : 'Masuk'}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </header>

                <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center gap-10 px-6 py-16">
                    <div className="max-w-3xl space-y-4">
                        <p className="section-label">
                            PLN Nusantara Power &middot; Unit Pembangkitan
                            Kendari
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                            Sistem Management Pengadaan Barang dan Jasa
                        </h1>
                        <p className="max-w-2xl text-muted-foreground">
                            Memusatkan perencanaan, pelaksanaan, monitoring, dan
                            pelaporan pengadaan UP Kendari dalam satu sistem
                            yang transparan dan terdokumentasi.
                        </p>
                        <div className="flex flex-wrap items-center gap-2 pt-2">
                            <Button asChild>
                                <Link
                                    href={
                                        isAuthenticated ? dashboard() : login()
                                    }
                                >
                                    {isAuthenticated
                                        ? 'Buka Dashboard'
                                        : 'Masuk ke Sistem'}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={publicMonitoring.planning()}>
                                    <MonitorPlay className="size-4" />
                                    Monitoring Publik
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-px overflow-hidden rounded-md border border-border bg-border md:grid-cols-3">
                        {capabilities.map((capability) => (
                            <div
                                key={capability.title}
                                className="space-y-2 bg-card p-5"
                            >
                                <capability.icon
                                    className="size-5 text-primary"
                                    strokeWidth={1.75}
                                />
                                <h2 className="text-sm font-semibold">
                                    {capability.title}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {capability.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto w-full max-w-6xl px-6 py-4 text-xs text-muted-foreground">
                        Akses sistem terbatas untuk pegawai PLN Nusantara Power
                        UP Kendari.
                    </div>
                </footer>
            </div>
        </>
    );
}
