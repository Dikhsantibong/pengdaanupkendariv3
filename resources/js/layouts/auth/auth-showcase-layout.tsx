import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';
import publicMonitoring from '@/routes/public-monitoring';
import type { AuthLayoutProps, StatusCategory } from '@/types';

/** How long each showcase slide stays on screen. */
const SLIDE_DURATION_MS = 8_000;

type Step = { name: string; is_optional: boolean };

type Showcase = {
    unitCount: number;
    units: string[];
    planningSteps: Step[];
    executionSteps: Step[];
    statuses: { name: string; category: StatusCategory }[];
    summary: {
        total: number;
        perencanaan: number;
        pelaksanaan: number;
        menungguApproval: number;
        selesai: number;
        batal: number;
    };
};

const statusDot: Record<StatusCategory, string> = {
    pending: 'bg-status-pending',
    batal: 'bg-status-batal',
    berjalan: 'bg-status-berjalan',
    selesai: 'bg-status-selesai',
};

const flow = [
    {
        title: 'Form Input Awal Pengadaan',
        actor: 'Team Leader Pengadaan',
        detail: 'Nama pekerjaan, direksi pekerjaan, unit tujuan, nomor PR/RO dan PRK, nilai HPE.',
    },
    {
        title: 'Penunjukan PIC Perencana',
        actor: 'Team Leader Pengadaan',
        detail: 'Satu PIC bertanggung jawab menyusun seluruh dokumen perencanaan.',
    },
    {
        title: 'Tahap Perencanaan',
        actor: 'PIC Perencana',
        detail: 'Nota Dinas, TOR, RAB, HPE, UPB, RKS, hingga Smart SCM.',
    },
    {
        title: 'Persetujuan Dokumen',
        actor: 'Team Leader Pengadaan',
        detail: 'Dokumen perencanaan disetujui atau dikembalikan dengan catatan.',
    },
    {
        title: 'Penunjukan PIC Pelaksana',
        actor: 'Team Leader Pengadaan',
        detail: 'Pelaksanaan dibuka setelah dokumen perencanaan disetujui.',
    },
    {
        title: 'Tahap Pelaksanaan',
        actor: 'PIC Pelaksana',
        detail: 'Evaluasi dokumen, HPS, berita acara, kontrak, hingga masa pemeliharaan.',
    },
    {
        title: 'Pengadaan Selesai',
        actor: 'Team Leader Pengadaan',
        detail: 'Kontrak dan masa pemeliharaan selesai, arsip dokumen lengkap.',
    },
];

export default function AuthShowcaseLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { showcase } = usePage<{ showcase?: Showcase }>().props;

    const slides = buildSlides(showcase);
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (slides.length < 2) {
            return;
        }

        const timer = setInterval(
            () => setIndex((current) => (current + 1) % slides.length),
            SLIDE_DURATION_MS,
        );

        return () => clearInterval(timer);
    }, [slides.length]);

    const slide = slides[Math.min(index, slides.length - 1)];

    return (
        <div className="min-h-svh lg:grid lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
            <aside className="relative hidden flex-col overflow-hidden bg-sidebar px-10 py-10 text-sidebar-foreground lg:flex xl:px-14">
                <div className="flex items-center gap-3">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-md bg-white p-1">
                        <AppLogoIcon className="h-10" />
                    </span>
                    <div className="leading-tight">
                        <p className="text-[0.6875rem] font-medium tracking-[0.08em] text-sidebar-foreground/60 uppercase">
                            PLN Nusantara Power
                        </p>
                        <p className="text-base font-semibold">
                            Sistem Management Pengadaan UP Kendari
                        </p>
                    </div>
                </div>

                <div className="mt-10 flex min-h-0 flex-1 flex-col">
                    <div
                        key={slide.key}
                        className="flex min-h-0 flex-1 animate-in flex-col duration-700 fade-in slide-in-from-bottom-3"
                    >
                        <p className="text-[0.6875rem] font-semibold tracking-[0.08em] text-sidebar-foreground/50 uppercase">
                            {slide.eyebrow}
                        </p>
                        <h2 className="mt-2 max-w-xl text-2xl font-semibold tracking-tight text-balance">
                            {slide.title}
                        </h2>
                        <div className="mt-6 min-h-0 flex-1 overflow-hidden">
                            {slide.content}
                        </div>
                    </div>

                    <div className="mt-8 flex items-center justify-between gap-4">
                        <div className="flex items-center gap-1.5">
                            {slides.map((entry, position) => (
                                <button
                                    key={entry.key}
                                    type="button"
                                    aria-label={`Tampilkan ${entry.eyebrow}`}
                                    onClick={() => setIndex(position)}
                                    className={cn(
                                        'h-1 rounded-full transition-all',
                                        position === index
                                            ? 'w-8 bg-sidebar-primary'
                                            : 'w-4 bg-sidebar-border hover:bg-sidebar-foreground/40',
                                    )}
                                />
                            ))}
                        </div>

                        <Link
                            href={publicMonitoring.planning()}
                            className="inline-flex items-center gap-1.5 text-sm font-medium text-sidebar-foreground/80 underline-offset-4 hover:text-sidebar-foreground hover:underline"
                        >
                            Lihat monitoring publik
                            <ArrowUpRight className="size-4" />
                        </Link>
                    </div>
                </div>
            </aside>

            <main className="flex min-h-svh flex-col justify-center bg-background px-6 py-10 sm:px-10">
                <div className="mx-auto w-full max-w-sm">
                    <div className="mb-8 flex items-center gap-3 lg:hidden">
                        <span className="flex size-11 shrink-0 items-center justify-center rounded-md bg-white p-1">
                            <AppLogoIcon className="h-9" />
                        </span>
                        <p className="text-sm leading-tight font-semibold">
                            Sistem Management Pengadaan
                            <span className="block text-[0.6875rem] font-medium tracking-[0.06em] text-muted-foreground uppercase">
                                UP Kendari
                            </span>
                        </p>
                    </div>

                    <div className="mb-6 space-y-1.5">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    {children}

                    <p className="mt-8 text-xs text-muted-foreground lg:hidden">
                        <Link
                            href={publicMonitoring.planning()}
                            className="font-medium text-primary hover:underline"
                        >
                            Lihat monitoring publik
                        </Link>
                    </p>
                </div>
            </main>
        </div>
    );
}

type Slide = {
    key: string;
    eyebrow: string;
    title: string;
    content: React.ReactNode;
};

function buildSlides(showcase?: Showcase): Slide[] {
    const slides: Slide[] = [
        {
            key: 'flow',
            eyebrow: 'Alur Proses Pengadaan',
            title: 'Dari usulan sampai masa pemeliharaan, dalam satu jalur yang tercatat.',
            content: <FlowSlide />,
        },
    ];

    if (showcase === undefined) {
        return slides;
    }

    slides.push(
        {
            key: 'figures',
            eyebrow: 'Rekapitulasi Saat Ini',
            title: 'Posisi seluruh pengadaan yang terdaftar di sistem.',
            content: <FiguresSlide showcase={showcase} />,
        },
        {
            key: 'planning',
            eyebrow: 'Tahap Perencanaan',
            title: `${showcase.planningSteps.length} dokumen yang wajib dilengkapi PIC Perencana.`,
            content: <StepsSlide steps={showcase.planningSteps} />,
        },
        {
            key: 'execution',
            eyebrow: 'Tahap Pelaksanaan',
            title: `${showcase.executionSteps.length} tahapan sampai masa pemeliharaan selesai.`,
            content: <StepsSlide steps={showcase.executionSteps} />,
        },
        {
            key: 'units',
            eyebrow: 'Cakupan Layanan',
            title: `${showcase.unitCount} unit pembangkit di wilayah UP Kendari.`,
            content: <UnitsSlide showcase={showcase} />,
        },
    );

    return slides;
}

function FlowSlide() {
    return (
        <ol className="max-w-2xl">
            {flow.map((step, index) => (
                <li key={step.title} className="flex gap-4">
                    <div className="flex flex-col items-center">
                        <span className="tabular flex size-7 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-sidebar-accent text-xs font-semibold">
                            {index + 1}
                        </span>
                        {index < flow.length - 1 && (
                            <span className="w-px flex-1 bg-sidebar-border" />
                        )}
                    </div>

                    <div
                        className={cn(
                            'min-w-0 pb-5',
                            index === flow.length - 1 && 'pb-0',
                        )}
                    >
                        <p className="text-sm font-semibold">{step.title}</p>
                        <p className="text-[0.6875rem] font-medium tracking-[0.06em] text-sidebar-foreground/50 uppercase">
                            {step.actor}
                        </p>
                        <p className="mt-0.5 text-sm text-sidebar-foreground/70">
                            {step.detail}
                        </p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

function FiguresSlide({ showcase }: { showcase: Showcase }) {
    const figures = [
        { label: 'Total Pengadaan', value: showcase.summary.total },
        { label: 'Tahap Perencanaan', value: showcase.summary.perencanaan },
        { label: 'Tahap Pelaksanaan', value: showcase.summary.pelaksanaan },
        {
            label: 'Menunggu Persetujuan',
            value: showcase.summary.menungguApproval,
        },
        { label: 'Selesai', value: showcase.summary.selesai },
        { label: 'Dibatalkan', value: showcase.summary.batal },
    ];

    return (
        <div className="max-w-2xl space-y-6">
            <dl className="grid grid-cols-3 gap-px overflow-hidden rounded-md border border-sidebar-border bg-sidebar-border">
                {figures.map((figure) => (
                    <div key={figure.label} className="bg-sidebar px-4 py-3">
                        <dt className="text-[0.6875rem] font-medium tracking-[0.06em] text-sidebar-foreground/50 uppercase">
                            {figure.label}
                        </dt>
                        <dd className="tabular mt-1 text-2xl font-semibold">
                            {figure.value}
                        </dd>
                    </div>
                ))}
            </dl>

            <div>
                <p className="text-[0.6875rem] font-semibold tracking-[0.08em] text-sidebar-foreground/50 uppercase">
                    Status Progres Terkonfigurasi
                </p>
                <ul className="mt-2 flex flex-wrap gap-1.5">
                    {showcase.statuses.map((status) => (
                        <li
                            key={status.name}
                            className="flex items-center gap-1.5 rounded-sm border border-sidebar-border px-2 py-1 text-xs"
                        >
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    statusDot[status.category],
                                )}
                                aria-hidden
                            />
                            {status.name}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

function StepsSlide({ steps }: { steps: Step[] }) {
    return (
        <ol className="grid max-w-2xl gap-x-6 gap-y-1.5 sm:grid-cols-2">
            {steps.map((step, index) => (
                <li
                    key={step.name}
                    className="flex items-baseline gap-2.5 text-sm"
                >
                    <span className="tabular w-5 shrink-0 text-right text-xs font-semibold text-sidebar-foreground/40">
                        {index + 1}
                    </span>
                    <span className="min-w-0">
                        {step.name}
                        {step.is_optional && (
                            <span className="ml-1.5 text-xs text-sidebar-foreground/50">
                                opsional
                            </span>
                        )}
                    </span>
                </li>
            ))}
        </ol>
    );
}

function UnitsSlide({ showcase }: { showcase: Showcase }) {
    return (
        <ul className="grid max-w-2xl gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
            {showcase.units.map((unit) => (
                <li key={unit} className="flex items-baseline gap-2.5">
                    <span
                        className="size-1.5 shrink-0 translate-y-[-2px] rounded-full bg-sidebar-primary"
                        aria-hidden
                    />
                    <span className="min-w-0 truncate">{unit}</span>
                </li>
            ))}
        </ul>
    );
}
