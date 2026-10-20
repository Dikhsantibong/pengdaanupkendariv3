import { router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    ClipboardPaste,
    CircleAlert,
    Download,
    Eye,
    FilePlus2,
    MoreHorizontal,
    Pencil,
    Paperclip,
    Plus,
    RotateCcw,
    Send,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { DecisionControls } from '@/components/procurement/decision-controls';
import {
    ContractValueSummary,
    ItemPicker,
    orderedLines,
    PriceListPaste,
    toLines,
} from '@/components/procurement/delivery-order-items';
import type { Line } from '@/components/procurement/delivery-order-items';
import {
    forgetRememberedPanel,
    NextActionBanner,
    PanelSection,
    rememberNewestPanel,
    StageDots,
    touchRememberedPanel,
    useRememberedPanel,
} from '@/components/procurement/request-board';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import {
    DetailToggle,
    RequestDocumentActions,
    RequestProgress,
} from './request-progress';

export type ContractItem = {
    id: number;
    group_name: string | null;
    description: string;
    part_number: string | null;
    unit: string;
    unit_price: number;
};

type OrderLine = {
    contract_item_id: number | null;
    group_name: string | null;
    description: string;
    part_number: string | null;
    quantity: number;
    unit: string;
    unit_price: number;
    total: number;
};

export type DeliveryOrder = {
    id: number;
    sequence: number;
    state: string;
    state_label: string;
    request_memo_number: string | null;
    request_memo_date: string | null;
    request_file: { name: string; url: string } | null;
    do_memo_number: string | null;
    do_memo_date: string | null;
    number: string | null;
    order_date: string | null;
    contract_date: string | null;
    vendor_code: string | null;
    work_description: string | null;
    delivery_place: string | null;
    delivery_days: number | null;
    created_by: string | null;
    review_note: string | null;
    revision: number;
    total: number;
    items: OrderLine[];
    steps: Array<{ label: string; by: string | null; at: string }>;
    document: {
        id: number;
        title: string;
        edit_url: string;
        preview_url: string;
        download_url: string;
    } | null;
    missing: string[];
    can: {
        request: boolean;
        decide: boolean;
        forward: boolean;
        issue: boolean;
    };
};

export type DeliveryOrders = {
    available: boolean;
    contract_items: ContractItem[];
    orders: DeliveryOrder[];
};

const STATE_TONE: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    dikembalikan: 'bg-destructive/10 text-destructive',
    terbit: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
};

/** The steps of a DO, and where each state stands among them. */
const DO_STEPS = ['Pengusul', 'TL', 'Asman', 'Rendan', 'Laksdan', 'Terbit'];
const DO_STEP_OF: Record<string, number> = {
    draft: 0,
    dikembalikan: 0,
    menunggu_persetujuan_tl: 1,
    menunggu_persetujuan_asman: 2,
    di_rendan: 3,
    di_laksdan: 4,
    terbit: DO_STEPS.length,
};

/** Whether the user has something to do on this DO now. */
const actionable = (order: DeliveryOrder): boolean =>
    order.can.request ||
    order.can.decide ||
    order.can.forward ||
    order.can.issue;

/** What the user is to do on a DO, in a word and in a sentence. */
function taskOf(
    order: DeliveryOrder,
): { action: string; detail: string } | null {
    if (order.can.request) {
        return order.state === 'dikembalikan'
            ? {
                  action: 'Perbaiki',
                  detail: 'Dikembalikan: perbaiki permohonan sesuai catatan, lalu ajukan ulang.',
              }
            : {
                  action: 'Lengkapi',
                  detail: 'Isi Nota Dinas usulan dan pilih item dari daftar harga kontrak, unggah nota dinasnya, lalu ajukan.',
              };
    }

    if (order.can.decide) {
        return {
            action: 'Tinjau',
            detail: 'Periksa item dan nilai DO, lalu setujui atau kembalikan.',
        };
    }

    if (order.can.forward) {
        return {
            action: 'Proses',
            detail: 'Isi Nota Dinas DO, lalu teruskan ke Laksdan.',
        };
    }

    if (order.can.issue) {
        return {
            action: 'Terbitkan',
            detail: 'Lengkapi nomor, tanggal, dan syarat penyerahan DO, periksa dokumennya, lalu terbitkan.',
        };
    }

    return null;
}

function StateBadge({ order }: { order: DeliveryOrder }) {
    return (
        <span
            className={`rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap ${
                STATE_TONE[order.state] ??
                'bg-amber-500/10 text-amber-800 dark:text-amber-300'
            }`}
        >
            {order.state_label}
            {order.revision > 0 ? ` · revisi ke-${order.revision}` : ''}
        </span>
    );
}

/**
 * The DO tab of a KHS contract as a table: what waits on the user is named
 * in a banner above it, every DO is a row, and the row opens a side panel
 * with only the user's part of it, step by step. The contract's price list
 * comes first while it is empty, since no DO can be asked for without it.
 */
export function DeliveryOrderTab({
    procurementId,
    data,
    canStart,
    canManageItems,
    contractValue = null,
}: {
    procurementId: number;
    data: DeliveryOrders;
    canStart: boolean;
    canManageItems: boolean;
    /** The contract's value (after negotiation), if known. */
    contractValue?: number | null;
}) {
    const scope = `do:${procurementId}`;
    const [starting, setStarting] = useState(false);
    const [openId, open] = useRememberedPanel(
        scope,
        data.orders.map((order) => order.id),
    );
    const newestFirst = [...data.orders].reverse();
    const mine = newestFirst.filter(actionable);
    const first = mine[0] ?? null;
    const firstTask = first === null ? null : taskOf(first);
    const openOrder = data.orders.find((order) => order.id === openId) ?? null;
    const noPriceList = data.contract_items.length === 0;
    // What is left of the contract's value for a DO: all the others taken.
    const remainingFor = (order: DeliveryOrder): number | null =>
        contractValue === null || contractValue <= 0
            ? null
            : contractValue -
              data.orders
                  .filter((other) => other.id !== order.id)
                  .reduce((sum, other) => sum + other.total, 0);

    const priceList = (
        <ContractItemsSection
            procurementId={procurementId}
            items={data.contract_items}
            editable={canManageItems}
        />
    );

    return (
        <div className="space-y-3">
            <section className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-4">
                <div className="text-sm">
                    <p className="font-semibold">Delivery Order (DO)</p>
                    <p className="text-xs text-muted-foreground">
                        Pemesanan bertahap dari daftar harga kontrak KHS:
                        diajukan pengusul, disetujui TL dan Asman, diproses
                        Rendan, lalu diterbitkan Laksdan.
                    </p>
                </div>
                {canStart && (
                    <Button
                        disabled={starting || noPriceList}
                        title={
                            noPriceList
                                ? 'Daftar harga kontrak belum diisi Laksdan'
                                : undefined
                        }
                        onClick={() =>
                            router.post(
                                procurements.deliveryOrders.store(procurementId)
                                    .url,
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => {
                                        setStarting(true);
                                        // The new draft opens when it is back.
                                        rememberNewestPanel(scope);
                                    },
                                    onError: forgetRememberedPanel,
                                    onFinish: () => setStarting(false),
                                },
                            )
                        }
                    >
                        <FilePlus2 className="size-4" />
                        Ajukan DO
                    </Button>
                )}
            </section>

            {!data.available && (
                <p className="rounded-md border border-dashed border-border px-4 py-3 text-sm text-muted-foreground">
                    DO dapat diajukan setelah dokumen kontrak (SPK / Surat
                    Perjanjian) dibuat.
                </p>
            )}

            {noPriceList && canManageItems && (
                <NextActionBanner
                    badge="1"
                    label="Langkah pertama"
                    title="Isi Daftar Harga Satuan Kontrak"
                    detail="Setiap DO memilih item dari daftar ini; DO belum bisa diajukan sebelum daftarnya diisi."
                    action="Isi daftar harga"
                    onAction={() =>
                        document
                            .getElementById('contract-price-list')
                            ?.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start',
                            })
                    }
                />
            )}

            {first !== null && firstTask !== null && (
                <NextActionBanner
                    badge={first.sequence}
                    label={
                        mine.length > 1
                            ? `Perlu tindakan Anda (${mine.length})`
                            : 'Perlu tindakan Anda'
                    }
                    title={`DO ke-${first.sequence} · ${first.state_label}`}
                    detail={
                        firstTask.detail +
                        ((first.can.request ||
                            first.can.forward ||
                            first.can.issue) &&
                        first.missing.length > 0
                            ? ` Yang belum: ${first.missing.slice(0, 3).join(', ')}${first.missing.length > 3 ? ` dan ${first.missing.length - 3} lainnya` : ''}.`
                            : '')
                    }
                    action={firstTask.action}
                    onAction={() => open(first.id)}
                />
            )}

            {noPriceList && <div id="contract-price-list">{priceList}</div>}

            {data.orders.length > 0 && (
                <ContractValueSummary
                    contractValue={contractValue}
                    orders={data.orders}
                />
            )}

            {data.orders.length === 0 ? (
                <p className="rounded-md border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                    Belum ada Delivery Order.
                    {canStart && !noPriceList
                        ? ' Klik Ajukan DO untuk memesan item dari daftar harga kontrak.'
                        : ''}
                </p>
            ) : (
                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <div
                        aria-hidden="true"
                        className="hidden grid-cols-[minmax(0,0.7fr)_minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.5fr)_10rem] gap-3 border-b border-border bg-muted/40 px-4 py-2 text-[0.6875rem] font-semibold tracking-wide text-muted-foreground uppercase md:grid"
                    >
                        <span>DO</span>
                        <span>Nomor &amp; tanggal</span>
                        <span>Item &amp; nilai</span>
                        <span>Status</span>
                        <span className="text-right">Aksi</span>
                    </div>
                    <ul className="divide-y divide-border">
                        {newestFirst.map((order) => (
                            <OrderRow
                                key={order.id}
                                order={order}
                                highlighted={order.id === first?.id}
                                onOpen={() => open(order.id)}
                            />
                        ))}
                    </ul>
                </div>
            )}

            {!noPriceList && priceList}

            <Sheet
                open={openOrder !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        open(null);
                    }
                }}
            >
                <SheetContent
                    className="w-full gap-0 sm:max-w-2xl lg:max-w-3xl"
                    onPointerDownCapture={touchRememberedPanel}
                >
                    {openOrder !== null && (
                        <OrderPanel
                            procurementId={procurementId}
                            order={openOrder}
                            contractItems={data.contract_items}
                            remaining={remainingFor(openOrder)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}

/** One DO in the table: what it orders, where it stands, and one action. */
function OrderRow({
    order,
    highlighted,
    onOpen,
}: {
    order: DeliveryOrder;
    highlighted: boolean;
    onOpen: () => void;
}) {
    const task = taskOf(order);

    return (
        <li
            onClick={(event) => {
                if (
                    !(event.target as HTMLElement).closest(
                        'button, a, [role="menuitem"]',
                    )
                ) {
                    onOpen();
                }
            }}
            className={cn(
                'grid cursor-pointer gap-x-3 gap-y-1.5 px-4 py-3 transition-colors hover:bg-muted/40 md:grid-cols-[minmax(0,0.7fr)_minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.5fr)_10rem] md:items-center',
                highlighted &&
                    'bg-primary/[0.03] shadow-[inset_3px_0_0_var(--primary)]',
            )}
        >
            <button
                type="button"
                onClick={onOpen}
                className="text-left text-sm font-medium hover:underline"
            >
                DO ke-{order.sequence}
            </button>
            <div className="tabular min-w-0 text-xs">
                <p className="truncate">
                    {order.number ?? (
                        <span className="text-muted-foreground">
                            Belum bernomor
                        </span>
                    )}
                </p>
                <p className="text-muted-foreground">
                    {order.order_date
                        ? formatDate(order.order_date)
                        : order.created_by
                          ? `Diajukan ${order.created_by}`
                          : '—'}
                </p>
            </div>
            <div className="tabular text-xs">
                <p>
                    {order.items.length > 0
                        ? `${order.items.length} item`
                        : 'Belum ada item'}
                </p>
                {order.total > 0 && (
                    <p className="text-muted-foreground">
                        {formatCurrency(order.total)}
                    </p>
                )}
            </div>
            <div className="space-y-1">
                <StateBadge order={order} />
                <StageDots
                    steps={DO_STEPS}
                    current={DO_STEP_OF[order.state] ?? 0}
                    returned={order.state === 'dikembalikan'}
                />
            </div>
            <div className="flex items-center gap-1 md:justify-end">
                <Button
                    size="sm"
                    variant={
                        task === null
                            ? 'ghost'
                            : highlighted
                              ? 'default'
                              : 'outline'
                    }
                    onClick={onOpen}
                >
                    {task?.action ?? 'Lihat'}
                    {task === null && <ChevronRight className="size-3.5" />}
                </Button>
                {order.document && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                size="sm"
                                variant="ghost"
                                className="size-8 p-0"
                                aria-label={`Aksi lain untuk DO ke-${order.sequence}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <a
                                    href={order.document.preview_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Eye className="size-4" />
                                    Pratinjau PDF
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <a href={order.document.download_url}>
                                    <Download className="size-4" />
                                    Unduh PDF
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <a href={order.document.edit_url}>
                                    <Pencil className="size-4" />
                                    Edit dokumen
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </li>
    );
}

/** Who did what, and when. */
function StepList({
    steps,
}: {
    steps: Array<{ label: string; by: string | null; at: string }>;
}) {
    if (steps.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-1 text-xs text-muted-foreground">
            {steps.map((step) => (
                <li key={step.label} className="flex items-center gap-1.5">
                    <CheckCircle2 className="size-3.5 text-emerald-600" />
                    {step.label}
                    {step.by ? ` (${step.by})` : ''} · {formatDateTime(step.at)}
                </li>
            ))}
        </ul>
    );
}

/**
 * A DO in the side panel: where it stands, then only the user's part of it,
 * numbered in the order it is done. The proposer picks the lines and files
 * the memo; an approver reads it and decides; Rendan writes the Nota Dinas
 * DO; Laksdan completes and issues it. Anyone else reads it.
 */
function OrderPanel({
    procurementId,
    order,
    contractItems,
    remaining,
}: {
    procurementId: number;
    order: DeliveryOrder;
    contractItems: ContractItem[];
    /** What is left of the contract's value for this DO, if known. */
    remaining: number | null;
}) {
    const args = { procurement: procurementId, order: order.id };
    const [details, setDetails] = useState(false);
    const task = taskOf(order);
    let section = 0;

    // What was asked for, folded away beside the forms that carry it.
    const foldedSummary = () => (
        <PanelSection number={++section} title="Periksa permohonan">
            <DetailToggle
                open={details}
                onToggle={() => setDetails(!details)}
            />
            {details && <Summary order={order} />}
        </PanelSection>
    );

    return (
        <>
            <SheetHeader className="border-b border-border pr-12">
                <p className="section-label">Delivery Order</p>
                <SheetTitle className="flex flex-wrap items-center gap-2 text-base">
                    DO ke-{order.sequence}
                    <StateBadge order={order} />
                </SheetTitle>
                <SheetDescription className="tabular">
                    {order.number ?? 'Nomor belum diisi'}
                    {order.created_by ? ` · diajukan ${order.created_by}` : ''}
                    {order.total > 0
                        ? ` · Total ${formatCurrency(order.total)}`
                        : ''}
                </SheetDescription>
                <RequestProgress
                    steps={DO_STEPS}
                    current={DO_STEP_OF[order.state] ?? 0}
                    returned={order.state === 'dikembalikan'}
                />
            </SheetHeader>

            <div className="flex-1 space-y-5 overflow-y-auto p-4">
                {task !== null && (
                    <p className="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-xs">
                        <b>Tugas Anda:</b> {task.detail}
                    </p>
                )}

                {order.state === 'dikembalikan' && order.review_note && (
                    <p className="flex items-start gap-1.5 rounded-sm border border-destructive/40 bg-destructive/5 p-2.5 text-xs">
                        <RotateCcw className="mt-0.5 size-3.5 shrink-0 text-destructive" />
                        <span>
                            <b>Yang harus diperbaiki:</b> {order.review_note}
                        </span>
                    </p>
                )}

                {order.can.request && (
                    <PanelSection
                        number={++section}
                        title="Permohonan DO"
                        hint="Tiga langkah: pilih item, isi Nota Dinas usulan, lalu periksa dan ajukan."
                    >
                        <RequestWizard
                            procurementId={procurementId}
                            order={order}
                            contractItems={contractItems}
                            remaining={remaining}
                        />
                    </PanelSection>
                )}

                {order.can.decide && (
                    <PanelSection number={++section} title="Periksa permohonan">
                        <Summary order={order} />
                    </PanelSection>
                )}

                {order.can.forward && (
                    <>
                        {foldedSummary()}
                        <PanelSection number={++section} title="Nota Dinas DO">
                            <MemoForm
                                procurementId={procurementId}
                                order={order}
                            />
                        </PanelSection>
                    </>
                )}

                {order.can.issue && (
                    <>
                        {foldedSummary()}
                        <PanelSection
                            number={++section}
                            title="Data DO & item"
                            hint="Nomor terisi otomatis dan bisa dikoreksi; simpan sebelum menerbitkan."
                        >
                            <IssueForm
                                procurementId={procurementId}
                                order={order}
                                contractItems={contractItems}
                                remaining={remaining}
                            />
                        </PanelSection>
                    </>
                )}

                {!actionable(order) && (
                    <PanelSection number={++section} title="Ringkasan">
                        <Summary order={order} />
                    </PanelSection>
                )}

                {order.document && (
                    <PanelSection
                        number={++section}
                        title="Dokumen DO"
                        hint="Periksa dokumen yang digenerate; perbaiki redaksinya lewat Edit bila perlu."
                    >
                        <RequestDocumentActions document={order.document} />
                    </PanelSection>
                )}

                {order.can.forward && (
                    <PanelSection number={++section} title="Teruskan">
                        <ActionBar
                            missing={order.missing}
                            label="Teruskan ke Laksdan"
                            url={procurements.deliveryOrders.forward(args).url}
                        />
                    </PanelSection>
                )}

                {order.can.issue && (
                    <PanelSection number={++section} title="Terbitkan">
                        <ActionBar
                            missing={order.missing}
                            label="Terbitkan DO"
                            url={procurements.deliveryOrders.issue(args).url}
                        />
                    </PanelSection>
                )}

                {order.can.decide && (
                    <PanelSection number={++section} title="Keputusan">
                        <DecisionForm
                            procurementId={procurementId}
                            order={order}
                        />
                    </PanelSection>
                )}

                {order.steps.length > 0 && (
                    <div className="space-y-1">
                        <p className="text-xs font-semibold">Riwayat</p>
                        <StepList steps={order.steps} />
                    </div>
                )}
            </div>
        </>
    );
}

type ContractItemRow = {
    id: number | null;
    group_name: string;
    description: string;
    part_number: string;
    unit: string;
    unit_price: string;
};

/** The KHS contract's price list, kept by Laksdan. */
function ContractItemsSection({
    procurementId,
    items,
    editable,
}: {
    procurementId: number;
    items: ContractItem[];
    editable: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const [shown, setShown] = useState(false);
    const [pasting, setPasting] = useState(false);
    const form = useForm<{ items: ContractItemRow[] }>({
        items: items.map((item) => ({
            id: item.id,
            group_name: item.group_name ?? '',
            description: item.description,
            part_number: item.part_number ?? '',
            unit: item.unit,
            unit_price: String(item.unit_price),
        })),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const set = (index: number, key: keyof ContractItemRow, value: string) =>
        form.setData(
            'items',
            form.data.items.map((row, at) =>
                at === index ? { ...row, [key]: value } : row,
            ),
        );

    return (
        <section className="space-y-3 rounded-md border border-border bg-card p-4">
            <header className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold">
                        Daftar Harga Satuan Kontrak
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {items.length > 0
                            ? `${items.length} item dari lampiran kontrak; setiap DO memilih item dari daftar ini.`
                            : 'Belum diisi.'}
                    </p>
                </div>
                {!editing && (
                    <div className="flex flex-wrap gap-1.5">
                        {items.length > 0 && (
                            <DetailToggle
                                open={shown}
                                onToggle={() => setShown(!shown)}
                            />
                        )}
                        {editable && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setEditing(true)}
                            >
                                Ubah Daftar
                            </Button>
                        )}
                    </div>
                )}
            </header>

            {editing ? (
                <form
                    className="space-y-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(
                            procurements.deliveryOrders.contractItems(
                                procurementId,
                            ).url,
                            {
                                preserveScroll: true,
                                onSuccess: () => setEditing(false),
                            },
                        );
                    }}
                >
                    {pasting ? (
                        <PriceListPaste
                            onCancel={() => setPasting(false)}
                            onAdd={(rows) => {
                                // Rows left blank make way for the pasted ones.
                                const kept = form.data.items.filter(
                                    (row) =>
                                        row.id !== null ||
                                        row.description.trim() !== '',
                                );
                                form.setData('items', [...kept, ...rows]);
                                setPasting(false);
                            }}
                        />
                    ) : null}
                    {form.data.items.map((row, index) => (
                        <div
                            key={index}
                            className="grid items-start gap-2 rounded-sm border border-border p-2 md:grid-cols-[1.2fr_2fr_1fr_0.7fr_1fr_auto]"
                        >
                            <Input
                                placeholder="Kelompok (opsional)"
                                value={row.group_name}
                                onChange={(event) =>
                                    set(index, 'group_name', event.target.value)
                                }
                            />
                            <div>
                                <Input
                                    placeholder="Uraian"
                                    value={row.description}
                                    onChange={(event) =>
                                        set(
                                            index,
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`items.${index}.description`]
                                    }
                                />
                            </div>
                            <Input
                                placeholder="Part number"
                                value={row.part_number}
                                onChange={(event) =>
                                    set(
                                        index,
                                        'part_number',
                                        event.target.value,
                                    )
                                }
                            />
                            <div>
                                <Input
                                    placeholder="Satuan"
                                    value={row.unit}
                                    onChange={(event) =>
                                        set(index, 'unit', event.target.value)
                                    }
                                />
                                <InputError
                                    message={errors[`items.${index}.unit`]}
                                />
                            </div>
                            <div>
                                <Input
                                    type="number"
                                    min={0}
                                    placeholder="Harga satuan"
                                    className="tabular"
                                    value={row.unit_price}
                                    onChange={(event) =>
                                        set(
                                            index,
                                            'unit_price',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`items.${index}.unit_price`]
                                    }
                                />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label="Hapus item"
                                onClick={() =>
                                    form.setData(
                                        'items',
                                        form.data.items.filter(
                                            (_, at) => at !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                form.setData('items', [
                                    ...form.data.items,
                                    {
                                        id: null,
                                        group_name:
                                            form.data.items.at(-1)
                                                ?.group_name ?? '',
                                        description: '',
                                        part_number: '',
                                        unit: '',
                                        unit_price: '',
                                    },
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            Tambah Item
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setPasting(true)}
                        >
                            <ClipboardPaste className="size-4" />
                            Tempel dari Excel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            Simpan Daftar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                form.reset();
                                setEditing(false);
                            }}
                        >
                            Batal
                        </Button>
                    </div>
                </form>
            ) : !shown || items.length === 0 ? null : (
                <ItemTable
                    rows={items.map((item) => ({
                        group_name: item.group_name,
                        description: item.description,
                        part_number: item.part_number,
                        unit: item.unit,
                        unit_price: item.unit_price,
                    }))}
                />
            )}
        </section>
    );
}

/** A read-only list of lines, grouped under their headings. */
function ItemTable({
    rows,
}: {
    rows: Array<{
        group_name: string | null;
        description: string;
        part_number: string | null;
        unit: string;
        unit_price: number;
        quantity?: number;
        total?: number;
    }>;
}) {
    const withQuantity = rows.some((row) => row.quantity !== undefined);

    return (
        <div className="overflow-x-auto rounded-sm border border-border">
            <table className="w-full text-xs">
                <thead className="bg-muted/60 text-left">
                    <tr>
                        <th className="px-2 py-1.5">Kelompok</th>
                        <th className="px-2 py-1.5">Uraian</th>
                        <th className="px-2 py-1.5">Part Number</th>
                        {withQuantity && (
                            <th className="px-2 py-1.5 text-right">Volume</th>
                        )}
                        <th className="px-2 py-1.5">Satuan</th>
                        <th className="px-2 py-1.5 text-right">Harga Satuan</th>
                        {withQuantity && (
                            <th className="px-2 py-1.5 text-right">Jumlah</th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {rows.map((row, index) => (
                        <tr key={index}>
                            <td className="px-2 py-1.5 text-muted-foreground">
                                {row.group_name ?? '—'}
                            </td>
                            <td className="px-2 py-1.5">{row.description}</td>
                            <td className="px-2 py-1.5">
                                {row.part_number ?? '—'}
                            </td>
                            {withQuantity && (
                                <td className="tabular px-2 py-1.5 text-right">
                                    {row.quantity}
                                </td>
                            )}
                            <td className="px-2 py-1.5">{row.unit}</td>
                            <td className="tabular px-2 py-1.5 text-right">
                                {formatCurrency(row.unit_price)}
                            </td>
                            {withQuantity && (
                                <td className="tabular px-2 py-1.5 text-right">
                                    {formatCurrency(row.total ?? 0)}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** The step of the request each DO is on, kept across the reloads saves bring. */
const requestSteps = new Map<number, number>();

const REQUEST_STEPS = ['Pilih item', 'Nota Dinas usulan', 'Periksa & ajukan'];

/**
 * The proposer's request, one step at a time: pick the items and their
 * volumes from the price list, then the Nota Dinas usulan and its scan,
 * then check the whole request and submit it. Every step saves before the
 * next, and the steps can be revisited.
 */
function RequestWizard({
    procurementId,
    order,
    contractItems,
    remaining,
}: {
    procurementId: number;
    order: DeliveryOrder;
    contractItems: ContractItem[];
    remaining: number | null;
}) {
    const itemsDone = order.items.length > 0;
    const memoDone = Boolean(
        order.request_memo_number &&
        order.request_memo_date &&
        order.request_file,
    );
    const done = [itemsDone, memoDone, false];
    const [step, setStepState] = useState<number>(
        () =>
            requestSteps.get(order.id) ?? (!itemsDone ? 0 : !memoDone ? 1 : 2),
    );
    const setStep = (next: number) => {
        requestSteps.set(order.id, next);
        setStepState(next);
    };

    const form = useForm<{
        request_memo_number: string;
        request_memo_date: string;
        items: Line[];
    }>({
        request_memo_number: order.request_memo_number ?? '',
        request_memo_date: order.request_memo_date ?? '',
        items: toLines(order),
    });
    const errors = form.errors as Record<string, string | undefined>;

    /** Save, and come back on the next step once the page has reloaded. */
    const saveAndGo = (next: number) => {
        if (!form.isDirty) {
            setStep(next);

            return;
        }

        form.transform((data) => ({
            ...data,
            items: orderedLines(data.items),
        }));
        form.put(
            procurements.deliveryOrders.request({
                procurement: procurementId,
                order: order.id,
            }).url,
            {
                preserveScroll: true,
                onStart: () => requestSteps.set(order.id, next),
                onError: () => requestSteps.set(order.id, step),
            },
        );
    };

    return (
        <div className="space-y-4">
            <ol className="grid grid-cols-3 gap-1.5">
                {REQUEST_STEPS.map((label, index) => (
                    <li key={label}>
                        <button
                            type="button"
                            onClick={() => setStep(index)}
                            aria-current={index === step ? 'step' : undefined}
                            className={cn(
                                'flex w-full items-center gap-2 rounded-md border px-2 py-1.5 text-left text-xs transition-colors',
                                index === step
                                    ? 'border-primary/40 bg-primary/10 font-semibold text-primary'
                                    : 'border-border text-muted-foreground hover:bg-muted/60',
                            )}
                        >
                            <span
                                className={cn(
                                    'tabular flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                                    done[index]
                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                        : index === step
                                          ? 'bg-primary text-primary-foreground'
                                          : 'bg-muted',
                                )}
                            >
                                {done[index] ? (
                                    <CheckCircle2 className="size-3.5" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span className="truncate">{label}</span>
                        </button>
                    </li>
                ))}
            </ol>

            {step === 0 && (
                <div className="space-y-3">
                    <p className="text-xs text-muted-foreground">
                        Isi volume item yang dipesan. Kosongkan (atau 0) item
                        yang tidak dipesan.
                    </p>
                    <ItemPicker
                        lines={form.data.items}
                        onChange={(lines) => form.setData('items', lines)}
                        contractItems={contractItems}
                        errors={errors}
                        remaining={remaining}
                    />
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            disabled={
                                form.processing ||
                                orderedLines(form.data.items).length === 0
                            }
                            onClick={() => saveAndGo(1)}
                        >
                            {form.isDirty ? 'Simpan & lanjut' : 'Lanjut'}
                            <ChevronRight className="size-3.5" />
                        </Button>
                    </div>
                </div>
            )}

            {step === 1 && (
                <div className="space-y-3">
                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor={`do-${order.id}-request-number`}>
                                Nomor Nota Dinas Usulan
                            </Label>
                            <Input
                                id={`do-${order.id}-request-number`}
                                value={form.data.request_memo_number}
                                onChange={(event) =>
                                    form.setData(
                                        'request_memo_number',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.request_memo_number} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor={`do-${order.id}-request-date`}>
                                Tanggal Nota Dinas Usulan
                            </Label>
                            <Input
                                id={`do-${order.id}-request-date`}
                                type="date"
                                className="tabular"
                                value={form.data.request_memo_date}
                                onChange={(event) =>
                                    form.setData(
                                        'request_memo_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.request_memo_date} />
                        </div>
                    </div>
                    <RequestFile procurementId={procurementId} order={order} />
                    <div className="flex justify-between gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setStep(0)}
                        >
                            Kembali
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={form.processing}
                            onClick={() => saveAndGo(2)}
                        >
                            {form.isDirty ? 'Simpan & lanjut' : 'Lanjut'}
                            <ChevronRight className="size-3.5" />
                        </Button>
                    </div>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-3">
                    <Summary order={order} />
                    {form.isDirty && (
                        <p className="rounded-sm border border-amber-500/30 bg-amber-500/5 px-2.5 py-2 text-xs text-amber-800 dark:text-amber-300">
                            Ada perubahan yang belum disimpan di langkah
                            sebelumnya.
                        </p>
                    )}
                    <ActionBar
                        missing={order.missing}
                        label={
                            order.state === 'dikembalikan'
                                ? 'Ajukan Ulang DO'
                                : 'Ajukan untuk Persetujuan'
                        }
                        url={
                            procurements.deliveryOrders.submit({
                                procurement: procurementId,
                                order: order.id,
                            }).url
                        }
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setStep(1)}
                    >
                        Kembali
                    </Button>
                </div>
            )}
        </div>
    );
}

/** The scan of the Nota Dinas usulan. */
function RequestFile({
    procurementId,
    order,
}: {
    procurementId: number;
    order: DeliveryOrder;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | undefined>();

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-sm border border-border px-3 py-2 text-sm">
            <div className="min-w-0">
                <p className="font-medium">Dokumen Nota Dinas Usulan</p>
                {order.request_file ? (
                    <a
                        href={order.request_file.url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        <Paperclip className="size-3" />
                        {order.request_file.name}
                    </a>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Belum diunggah
                    </p>
                )}
                <InputError message={error} />
            </div>
            <input
                ref={input}
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                className="sr-only"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = '';

                    if (!file) {
                        return;
                    }

                    router.post(
                        procurements.deliveryOrders.uploadRequest({
                            procurement: procurementId,
                            order: order.id,
                        }).url,
                        { file },
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            onStart: () => {
                                setUploading(true);
                                setError(undefined);
                            },
                            onError: (errors) => setError(errors.file),
                            onFinish: () => setUploading(false),
                        },
                    );
                }}
            />
            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={uploading}
                onClick={() => input.current?.click()}
            >
                <Upload className="size-3.5" />
                {order.request_file ? 'Ganti' : 'Unggah'}
            </Button>
        </div>
    );
}

/** Rendan's Nota Dinas DO. */
function MemoForm({
    procurementId,
    order,
}: {
    procurementId: number;
    order: DeliveryOrder;
}) {
    const form = useForm({
        do_memo_number: order.do_memo_number ?? '',
        do_memo_date: order.do_memo_date ?? '',
    });

    return (
        <form
            className="space-y-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    procurements.deliveryOrders.memo({
                        procurement: procurementId,
                        order: order.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <div className="grid gap-3 md:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`do-${order.id}-memo-number`}>
                        Nomor Nota Dinas DO
                    </Label>
                    <Input
                        id={`do-${order.id}-memo-number`}
                        value={form.data.do_memo_number}
                        onChange={(event) =>
                            form.setData('do_memo_number', event.target.value)
                        }
                    />
                    <InputError message={form.errors.do_memo_number} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`do-${order.id}-memo-date`}>
                        Tanggal Nota Dinas DO
                    </Label>
                    <Input
                        id={`do-${order.id}-memo-date`}
                        type="date"
                        className="tabular"
                        value={form.data.do_memo_date}
                        onChange={(event) =>
                            form.setData('do_memo_date', event.target.value)
                        }
                    />
                    <InputError message={form.errors.do_memo_date} />
                </div>
            </div>
            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Nota Dinas DO
            </Button>
        </form>
    );
}

/** Laksdan's DO: number, date, delivery terms and the lines. */
function IssueForm({
    procurementId,
    order,
    contractItems,
    remaining,
}: {
    procurementId: number;
    order: DeliveryOrder;
    contractItems: ContractItem[];
    remaining: number | null;
}) {
    const form = useForm<{
        number: string;
        order_date: string;
        vendor_code: string;
        work_description: string;
        delivery_place: string;
        delivery_days: string;
        items: Line[];
    }>({
        number: order.number ?? '',
        order_date: order.order_date ?? '',
        vendor_code: order.vendor_code ?? '',
        work_description: order.work_description ?? '',
        delivery_place: order.delivery_place ?? '',
        delivery_days:
            order.delivery_days === null ? '' : String(order.delivery_days),
        items: toLines(order),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const field = (
        name:
            | 'number'
            | 'order_date'
            | 'vendor_code'
            | 'delivery_place'
            | 'delivery_days',
        label: string,
        type: 'text' | 'date' | 'number' = 'text',
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`do-${order.id}-${name}`}>{label}</Label>
            <Input
                id={`do-${order.id}-${name}`}
                type={type}
                className={type === 'text' ? undefined : 'tabular'}
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
            />
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    items: orderedLines(data.items),
                }));
                form.put(
                    procurements.deliveryOrders.issueData({
                        procurement: procurementId,
                        order: order.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <div className="grid gap-3 md:grid-cols-2">
                {field('number', 'Nomor DO (otomatis, bisa dikoreksi)')}
                {field('order_date', 'Tanggal DO', 'date')}
                {field('vendor_code', 'Kode Rekanan')}
                {field('delivery_days', 'Waktu Penyerahan (hari)', 'number')}
                <div className="grid gap-1.5 md:col-span-2">
                    <Label htmlFor={`do-${order.id}-place`}>
                        Tempat Penyerahan Pekerjaan
                    </Label>
                    <Input
                        id={`do-${order.id}-place`}
                        value={form.data.delivery_place}
                        onChange={(event) =>
                            form.setData('delivery_place', event.target.value)
                        }
                    />
                    <InputError message={errors.delivery_place} />
                </div>
                <div className="grid gap-1.5 md:col-span-2">
                    <Label htmlFor={`do-${order.id}-work`}>
                        Uraian Pekerjaan
                    </Label>
                    <Textarea
                        id={`do-${order.id}-work`}
                        rows={2}
                        value={form.data.work_description}
                        onChange={(event) =>
                            form.setData('work_description', event.target.value)
                        }
                    />
                    <InputError message={errors.work_description} />
                </div>
            </div>

            <div className="space-y-1.5">
                <p className="text-sm font-semibold">Item DO</p>
                <ItemPicker
                    lines={form.data.items}
                    onChange={(lines) => form.setData('items', lines)}
                    contractItems={contractItems}
                    errors={errors}
                    remaining={remaining}
                />
            </div>

            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Data DO
            </Button>
        </form>
    );
}

/** What the DO says, for everyone who is not editing that step. */
function Summary({ order }: { order: DeliveryOrder }) {
    const rows: Array<[string, string | null]> = [
        ['Nota Dinas Usulan', order.request_memo_number],
        [
            'Tanggal Nota Dinas Usulan',
            order.request_memo_date
                ? formatDate(order.request_memo_date)
                : null,
        ],
        ['Nota Dinas DO', order.do_memo_number],
        [
            'Tanggal Nota Dinas DO',
            order.do_memo_date ? formatDate(order.do_memo_date) : null,
        ],
        ['Tanggal DO', order.order_date ? formatDate(order.order_date) : null],
        ['Tempat Penyerahan', order.delivery_place],
    ];

    return (
        <div className="space-y-3 text-sm">
            <dl className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-3">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="tabular text-right font-medium">
                            {value ?? '—'}
                        </dd>
                    </div>
                ))}
            </dl>
            {order.request_file && (
                <a
                    href={order.request_file.url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                >
                    <Paperclip className="size-3" />
                    {order.request_file.name}
                </a>
            )}
            {order.items.length > 0 && <ItemTable rows={order.items} />}
        </div>
    );
}

/** What still holds the step back, and the button that moves it on. */
function ActionBar({
    missing,
    label,
    url,
}: {
    missing: string[];
    label: string;
    url: string;
}) {
    const [sending, setSending] = useState(false);

    return (
        <div className="space-y-2 border-t border-border pt-3">
            {missing.length > 0 && (
                <div className="space-y-1 rounded-sm border border-border bg-muted/40 p-2.5 text-xs">
                    <p className="flex items-center gap-1.5 font-medium">
                        <CircleAlert className="size-3.5 text-amber-600" />
                        Lengkapi dulu (simpan isian terlebih dahulu):
                    </p>
                    <ul className="list-disc pl-4 text-muted-foreground">
                        {missing.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}
            <Button
                disabled={sending || missing.length > 0}
                onClick={() =>
                    router.post(
                        url,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setSending(true),
                            onFinish: () => setSending(false),
                        },
                    )
                }
            >
                <Send className="size-4" />
                {label}
            </Button>
        </div>
    );
}

/** The TL or Asman decision on the request. */
function DecisionForm({
    procurementId,
    order,
}: {
    procurementId: number;
    order: DeliveryOrder;
}) {
    const form = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    const decide = (approved: boolean) => {
        form.transform((data) => ({ ...data, approved }));
        form.post(
            procurements.deliveryOrders.decide({
                procurement: procurementId,
                order: order.id,
            }).url,
            { preserveScroll: true },
        );
    };

    return (
        <DecisionControls
            id={`do-${order.id}-note`}
            note={form.data.note}
            onNoteChange={(note) => form.setData('note', note)}
            error={form.errors.note}
            processing={form.processing}
            onDecide={decide}
        />
    );
}
