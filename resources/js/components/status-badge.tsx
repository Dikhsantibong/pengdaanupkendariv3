import { cn } from '@/lib/utils';
import type { StatusCategory } from '@/types';

const categoryClasses: Record<StatusCategory, string> = {
    pending: 'bg-status-pending-surface text-status-pending',
    batal: 'bg-status-batal-surface text-status-batal',
    berjalan: 'bg-status-berjalan-surface text-status-berjalan',
    selesai: 'bg-status-selesai-surface text-status-selesai',
};

export function StatusBadge({
    label,
    category,
    className,
}: {
    label: string;
    category: StatusCategory;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                categoryClasses[category],
                className,
            )}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {label}
        </span>
    );
}

const approvalClasses: Record<string, string> = {
    belum_diajukan: 'bg-status-pending-surface text-status-pending',
    menunggu_persetujuan: 'bg-status-berjalan-surface text-status-berjalan',
    disetujui: 'bg-status-selesai-surface text-status-selesai',
    ditolak: 'bg-status-batal-surface text-status-batal',
};

export function ApprovalBadge({
    state,
    label,
    className,
}: {
    state: string;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex w-fit items-center rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                approvalClasses[state] ?? approvalClasses.belum_diajukan,
                className,
            )}
        >
            {label}
        </span>
    );
}
