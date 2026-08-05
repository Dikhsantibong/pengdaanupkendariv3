import { Head, Link, usePoll } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import publicMonitoring from '@/routes/public-monitoring';

export const BOARD_POLL_INTERVAL_MS = 15_000;

/**
 * Shared chrome for the public wall-display boards: brand header, stage
 * switcher, live refresh indicator and footer. Side padding is kept tight so
 * the content fills a large screen.
 */
export function PublicBoardShell({
    title,
    subtitle,
    active,
    generatedAt,
    children,
}: {
    title: string;
    subtitle: string;
    active: 'planning' | 'execution';
    generatedAt: string;
    children: ReactNode;
}) {
    const [isRefreshing, setIsRefreshing] = useState(false);

    usePoll(
        BOARD_POLL_INTERVAL_MS,
        {
            onStart: () => setIsRefreshing(true),
            onFinish: () => setIsRefreshing(false),
        },
        { mode: 'rest', keepAlive: true },
    );

    return (
        <>
            <Head title={title} />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="border-b border-border bg-card">
                    <div className="flex flex-col gap-4 px-4 py-3 xl:flex-row xl:items-center xl:justify-between 2xl:px-6">
                        <div className="flex items-center gap-3">
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-md bg-white p-1">
                                <AppLogoIcon className="h-10" />
                            </span>
                            <div className="leading-tight">
                                <p className="section-label">
                                    PLN Nusantara Power UP Kendari
                                </p>
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {title}
                                </h1>
                                <p className="text-xs text-muted-foreground">
                                    {subtitle}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-4">
                            <nav className="flex items-center gap-1 rounded-md border border-border p-1">
                                <StageTab
                                    href={publicMonitoring.planning().url}
                                    label="Perencanaan"
                                    isActive={active === 'planning'}
                                />
                                <StageTab
                                    href={publicMonitoring.execution().url}
                                    label="Pelaksanaan"
                                    isActive={active === 'execution'}
                                />
                            </nav>

                            <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        isRefreshing
                                            ? 'animate-pulse bg-status-berjalan'
                                            : 'bg-status-selesai',
                                    )}
                                    aria-hidden
                                />
                                <span className="tabular">
                                    {formatDateTime(generatedAt)}
                                </span>
                            </span>

                            <Link
                                href={login()}
                                className="text-xs font-medium text-primary hover:underline"
                            >
                                Masuk sistem
                            </Link>
                        </div>
                    </div>
                </header>

                <main className="flex flex-1 flex-col gap-4 px-4 py-4 2xl:px-6">
                    {children}
                </main>

                <footer className="border-t border-border px-4 py-3 text-xs text-muted-foreground 2xl:px-6">
                    Papan ini menampilkan progres proses pengadaan. Nilai
                    anggaran dan data personel tidak ditampilkan. Diperbarui
                    otomatis setiap {BOARD_POLL_INTERVAL_MS / 1000} detik.
                </footer>
            </div>
        </>
    );
}

function StageTab({
    href,
    label,
    isActive,
}: {
    href: string;
    label: string;
    isActive: boolean;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'rounded-sm px-3 py-1.5 text-sm font-medium transition-colors',
                isActive
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            )}
        >
            {label}
        </Link>
    );
}

/**
 * A titled panel used across both boards.
 */
export function BoardPanel({
    title,
    caption,
    children,
    className,
    bodyClassName,
}: {
    title: string;
    caption?: string;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
}) {
    return (
        <section
            className={cn(
                'flex flex-col overflow-hidden rounded-md border border-border bg-card',
                className,
            )}
        >
            <header className="flex items-baseline justify-between gap-3 border-b border-border px-4 py-2.5">
                <h2 className="text-sm font-semibold">{title}</h2>
                {caption !== undefined && (
                    <span className="shrink-0 text-xs text-muted-foreground">
                        {caption}
                    </span>
                )}
            </header>
            <div className={cn('flex-1', bodyClassName)}>{children}</div>
        </section>
    );
}
