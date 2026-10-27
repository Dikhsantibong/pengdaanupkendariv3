import { Head, router } from '@inertiajs/react';
import { Search, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ApprovalRequestTable } from '@/components/approval-request-table';
import type { ApprovalRequestRow } from '@/components/approval-request-table';
import { ReviewList } from '@/components/approval-review';
import type { Review } from '@/components/approval-review';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import approvals from '@/routes/approvals';
import type {
    FilterOptions,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
} from '@/types';

type ProcurementQueueKey = 'planning' | 'execution' | 'handover' | 'payment';

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

/** Who decides each queue, and what an empty one says. */
const queueCopy: Record<
    ProcurementQueueKey,
    {
        decider: string;
        empty: Record<QueueView, [string, string]>;
    }
> = {
    planning: {
        decider: 'TL ICC',
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
    handover: {
        decider: 'TL Pengadaan',
        empty: {
            waiting: [
                'Tidak ada antrean persetujuan penyerahan',
                'Berkas penyerahan yang diajukan PIC Pelaksana akan tampil di sini.',
            ],
            decided: [
                'Belum ada keputusan penyerahan',
                'Penyerahan yang disetujui atau dikembalikan akan tampil di sini.',
            ],
        },
    },
    payment: {
        decider: 'TL Keuangan dan Asman',
        empty: {
            waiting: [
                'Tidak ada pembayaran yang menunggu Anda',
                'Pembayaran yang diajukan Keuangan untuk persetujuan TL Keuangan / Asman akan tampil di sini.',
            ],
            decided: [
                'Belum ada pembayaran yang Anda putuskan',
                'Pembayaran yang Anda setujui atau kembalikan akan tampil di sini.',
            ],
        },
    },
};

export default function ApprovalIndex({
    queues,
    queue,
    view,
    procurements: page,
    reviews = {},
    requests = null,
    filters,
}: {
    queues: Queue[];
    queue: QueueKey | null;
    view: QueueView;
    procurements: Paginated<ProcurementRow> | null;
    /** What to read before deciding, keyed by procurement. */
    reviews?: Record<number, Review>;
    /** The amendment or DO queue's page, when that queue is open. */
    requests?: Paginated<ApprovalRequestRow> | null;
    filters: ProcurementFilters;
    options: FilterOptions;
}) {
    const url = approvals.index().url;
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);
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

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(
            () =>
                router.get(
                    url,
                    { ...persist, ...(search ? { search } : {}) },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            350,
        );

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <>
            <Head title="Approval" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Persetujuan"
                    title="Approval"
                    description="Pilih pengajuan, periksa berkasnya, lalu Setujui atau Kembalikan."
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
                                      <div className="relative max-w-sm">
                                          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                          <Input
                                              value={search}
                                              onChange={(event) =>
                                                  setSearch(event.target.value)
                                              }
                                              placeholder="Cari nomor atau nama pekerjaan"
                                              className="h-9 pl-9"
                                              aria-label="Cari pengajuan"
                                          />
                                      </div>

                                      <ReviewList
                                          queue={queue}
                                          page={page}
                                          reviews={reviews}
                                          decided={view === 'decided'}
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
