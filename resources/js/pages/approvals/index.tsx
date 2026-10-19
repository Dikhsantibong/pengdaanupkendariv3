import { Head, router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { ApprovalRequestTable } from '@/components/approval-request-table';
import type { ApprovalRequestRow } from '@/components/approval-request-table';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementTable } from '@/components/procurement-table';
import type { ProcurementColumn } from '@/components/procurement-table';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import approvals from '@/routes/approvals';
import type {
    FilterOptions,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
} from '@/types';

type ProcurementQueueKey = 'planning' | 'execution';

/** Amendments and Delivery Orders: requests, not procurements. */
type RequestQueueKey = 'amendment' | 'delivery-order';

type QueueKey = ProcurementQueueKey | RequestQueueKey;

const isRequestQueue = (key: QueueKey): key is RequestQueueKey =>
    key === 'amendment' || key === 'delivery-order';

/** Who acts on each request queue, and what an empty one says. */
const requestCopy: Record<
    RequestQueueKey,
    Record<QueueView, [string, string]>
> = {
    amendment: {
        waiting: [
            'Tidak ada amandemen yang menunggu Anda',
            'Amandemen yang perlu persetujuan TL / Asman atau penerbitan Laksdan akan tampil di sini.',
        ],
        decided: [
            'Belum ada amandemen yang Anda putuskan',
            'Amandemen yang Anda setujui, kembalikan, atau terbitkan akan tampil di sini.',
        ],
    },
    'delivery-order': {
        waiting: [
            'Tidak ada Delivery Order yang menunggu Anda',
            'DO yang perlu persetujuan TL / Asman, Nota Dinas DO (Rendan), atau penerbitan (Laksdan) akan tampil di sini.',
        ],
        decided: [
            'Belum ada Delivery Order yang Anda proses',
            'DO yang Anda setujui, kembalikan, teruskan, atau terbitkan akan tampil di sini.',
        ],
    },
};

type QueueView = 'waiting' | 'decided';

type Queue = {
    key: QueueKey;
    label: string;
    waiting: number;
};

/** What each queue shows, and who decides it. */
const queueCopy: Record<
    ProcurementQueueKey,
    {
        decider: string;
        columns: Record<QueueView, ProcurementColumn[]>;
        empty: Record<QueueView, [string, string]>;
    }
> = {
    planning: {
        decider: 'TL ICC',
        columns: {
            waiting: ['unit', 'hpe', 'planner', 'planningProgress', 'status'],
            decided: ['unit', 'hpe', 'planner', 'approval', 'status'],
        },
        empty: {
            waiting: [
                'Tidak ada antrean persetujuan perencanaan',
                'Semua dokumen perencanaan sudah ditinjau.',
            ],
            decided: [
                'Belum ada keputusan perencanaan',
                'Perencanaan yang disetujui atau ditolak akan tampil di sini.',
            ],
        },
    },
    execution: {
        decider: 'TL Pengadaan',
        columns: {
            waiting: ['unit', 'hpe', 'executor', 'executionProgress', 'status'],
            decided: ['unit', 'hpe', 'executor', 'executionApproval', 'status'],
        },
        empty: {
            waiting: [
                'Tidak ada antrean persetujuan pelaksanaan',
                'Semua pelaksanaan yang diajukan sudah ditinjau.',
            ],
            decided: [
                'Belum ada keputusan pelaksanaan',
                'Pelaksanaan yang disetujui atau dikembalikan akan tampil di sini.',
            ],
        },
    },
};

export default function ApprovalIndex({
    queues,
    queue,
    view,
    procurements: page,
    requests = null,
    filters,
    options,
}: {
    queues: Queue[];
    queue: QueueKey | null;
    view: QueueView;
    procurements: Paginated<ProcurementRow> | null;
    /** The amendment or DO queue's page, when that queue is open. */
    requests?: Paginated<ApprovalRequestRow> | null;
    filters: ProcurementFilters;
    options: FilterOptions;
}) {
    const url = approvals.index().url;
    const persist: Record<string, string> = {
        ...(queue ? { queue } : {}),
        view,
    };

    // Switching a tab keeps the filters and starts the
    // list again from its first page.
    const open = (next: Record<string, string>) => {
        const query: Record<string, string> = {
            ...Object.fromEntries(new URLSearchParams(window.location.search)),
            ...persist,
            ...next,
        };
        delete query.page;

        router.get(url, query, { preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title="Approval" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Persetujuan"
                    title="Antrean Approval"
                    description="Hanya antrean yang menjadi keputusan Anda yang ditampilkan: perencanaan untuk TL ICC, pelaksanaan untuk TL Pengadaan, serta amandemen dan Delivery Order untuk TL, Asman, Rendan, dan Laksdan yang terlibat."
                />

                {queue === null ||
                (isRequestQueue(queue) ? requests === null : page === null) ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={ShieldCheck}
                            title="Tidak ada antrean persetujuan untuk Anda"
                            description="Akun ini tidak memegang hak persetujuan perencanaan, pelaksanaan, amandemen, maupun Delivery Order."
                        />
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            {queues.length > 1 && (
                                <div
                                    role="tablist"
                                    className="inline-flex rounded-md border border-border bg-muted/50 p-0.5"
                                >
                                    {queues.map((item) => (
                                        <button
                                            key={item.key}
                                            type="button"
                                            role="tab"
                                            aria-selected={item.key === queue}
                                            onClick={() =>
                                                open({
                                                    queue: item.key,
                                                    view: 'waiting',
                                                })
                                            }
                                            className={cn(
                                                'inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                                item.key === queue
                                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:bg-primary/10 hover:text-primary',
                                            )}
                                        >
                                            {item.label}
                                            <span
                                                className={cn(
                                                    'tabular rounded-full px-1.5 text-xs',
                                                    item.key === queue
                                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                                        : 'bg-primary/10 text-primary',
                                                )}
                                            >
                                                {item.waiting}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            )}

                            <div
                                role="tablist"
                                className="inline-flex rounded-md border border-border bg-muted/50 p-0.5"
                            >
                                {(
                                    [
                                        ['waiting', 'Menunggu Keputusan'],
                                        ['decided', 'Riwayat Keputusan'],
                                    ] as const
                                ).map(([key, label]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        role="tab"
                                        aria-selected={key === view}
                                        onClick={() => open({ view: key })}
                                        className={cn(
                                            'rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                            key === view
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'text-muted-foreground hover:bg-primary/10 hover:text-primary',
                                        )}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {isRequestQueue(queue)
                            ? requests && (
                                  <ApprovalRequestTable
                                      page={requests}
                                      decided={view === 'decided'}
                                      emptyTitle={requestCopy[queue][view][0]}
                                      emptyDescription={
                                          requestCopy[queue][view][1]
                                      }
                                  />
                              )
                            : page && (
                                  <>
                                      <ProcurementFilterBar
                                          url={url}
                                          filters={filters}
                                          options={options}
                                          persist={persist}
                                      />

                                      <ProcurementTable
                                          page={page}
                                          columns={
                                              queueCopy[queue].columns[view]
                                          }
                                          emptyTitle={
                                              queueCopy[queue].empty[view][0]
                                          }
                                          emptyDescription={
                                              queueCopy[queue].empty[view][1]
                                          }
                                      />
                                  </>
                              )}
                    </>
                )}
            </div>
        </>
    );
}

ApprovalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Approval', href: approvals.index() },
    ],
};
