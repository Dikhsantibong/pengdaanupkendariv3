import { Link } from '@inertiajs/react';
import { ArrowLeft, Folder, FolderOpen, FolderX } from 'lucide-react';
import type { ReactNode } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/** One partner's folder on a monitoring board. */
export type PartnerFolder = {
    /** What opens the folder (`mitra`); the folder without a partner has its own. */
    key: string;
    name: string;
    /** The rows it holds: procurements, amendments, DOs or contracts. */
    total: number;
    /** Over how many works. */
    works: number;
    /** How many of the rows are done. */
    done: number;
    latest_at: string | null;
};

/** The folder open on a monitoring board. */
export type OpenPartner = { key: string; name: string };

/**
 * One folder per partner, the partner busiest most recently first, laid out
 * like the folders of Penilaian Penyedia and Arsip Dokumen.
 */
export function PartnerFolderGrid({
    page,
    href,
    details,
    emptyTitle,
}: {
    page: Paginated<PartnerFolder>;
    href: (folder: PartnerFolder) => string;
    /** The lines under the partner's name. */
    details: (folder: PartnerFolder) => ReactNode;
    emptyTitle: string;
}) {
    if (page.data.length === 0) {
        return (
            <div className="rounded-md border border-border bg-card">
                <EmptyState
                    icon={FolderX}
                    title={emptyTitle}
                    description="Ubah atau reset filter untuk melihat mitra lainnya."
                />
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                {page.data.map((folder) => (
                    <Link
                        key={folder.key}
                        href={href(folder)}
                        title={folder.name}
                        className="group flex h-full items-start gap-2 rounded-md border border-border bg-card p-2.5 text-left transition-colors hover:border-primary/50 hover:bg-accent/40"
                    >
                        <Folder className="mt-0.5 size-5 shrink-0 text-amber-500 group-hover:hidden dark:text-amber-400" />
                        <FolderOpen className="mt-0.5 hidden size-5 shrink-0 text-primary group-hover:block" />
                        <span className="min-w-0 space-y-0.5">
                            <span className="line-clamp-2 text-xs font-semibold text-foreground">
                                {folder.name}
                            </span>
                            {details(folder)}
                        </span>
                    </Link>
                ))}
            </div>

            <div className="overflow-hidden rounded-md border border-border bg-card">
                <DataPagination page={page} />
            </div>
        </div>
    );
}

/** The open folder's name, with the way back to every partner. */
export function OpenPartnerBar({
    partner,
    backHref,
    children,
}: {
    partner: OpenPartner;
    backHref: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2 rounded-md border border-border bg-card px-3 py-2">
            <Button asChild variant="ghost" size="sm">
                <Link href={backHref}>
                    <ArrowLeft className="size-4" />
                    Semua Mitra
                </Link>
            </Button>
            <FolderOpen className="size-4 text-primary" />
            <span className="text-sm font-semibold">{partner.name}</span>
            {children && (
                <span className={cn('text-xs text-muted-foreground')}>
                    {children}
                </span>
            )}
        </div>
    );
}

/** A small badge on a folder: how many are done. */
export function FolderBadge({
    done,
    total,
    label,
}: {
    done: number;
    total: number;
    label: string;
}) {
    const complete = total > 0 && done === total;

    return (
        <span
            className={cn(
                'tabular inline-block rounded-full px-1.5 py-0.5 text-[10px] font-medium',
                complete
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                    : 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            )}
        >
            {complete ? `Semua ${label}` : `${done}/${total} ${label}`}
        </span>
    );
}
