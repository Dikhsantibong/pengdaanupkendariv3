import { router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    FilePlus2,
    FileText,
    Paperclip,
    Plus,
    RotateCcw,
    Send,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';

type AmendmentChange = {
    article: string;
    original_text: string;
    new_text: string;
};

type AmendmentFile = {
    kind: string;
    label: string;
    required: boolean;
    file: {
        name: string;
        size: number | null;
        uploaded_by: string | null;
        uploaded_at: string | null;
        url: string;
    } | null;
};

export type Amendment = {
    id: number;
    sequence: number;
    roman: string;
    number: string | null;
    state: string;
    state_label: string;
    amendment_date: string | null;
    contract_date: string | null;
    meeting_date: string | null;
    memo_number: string | null;
    memo_date: string | null;
    request_number: string | null;
    request_date: string | null;
    created_by: string | null;
    submitted_at: string | null;
    review_note: string | null;
    revision: number;
    decisions: Array<{ level: string; by: string | null; at: string }>;
    changes: AmendmentChange[];
    files: AmendmentFile[];
    document: {
        id: number;
        title: string;
        edit_url: string;
        preview_url: string;
    } | null;
    missing: string[];
    can: { update: boolean; decide: boolean; issue: boolean };
};

/** An article of the contract, which a change is picked from. */
export type ContractArticle = { article: string; text: string };

const STATE_TONE: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    dikembalikan: 'bg-destructive/10 text-destructive',
    disetujui: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
};

/**
 * The Amandemen tab: every amendment of the contract. The proposer requests
 * one (Nota Dinas usulan, Notulen, optionally the partner's request letter),
 * their TL and the division's Asman agree, and Laksdan numbers and dates it,
 * picks the changed articles from the contract and issues it.
 */
export function AmendmentTab({
    procurementId,
    amendments,
    canStart,
    articles = [],
}: {
    procurementId: number;
    amendments: Amendment[];
    canStart: boolean;
    /** The contract's articles, for Laksdan to pick the changed ones. */
    articles?: ContractArticle[];
}) {
    const [starting, setStarting] = useState(false);

    return (
        <div className="space-y-4">
            <section className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-4">
                <div className="text-sm">
                    <p className="font-semibold">Amandemen Kontrak</p>
                    <p className="text-xs text-muted-foreground">
                        Pengusul mengajukan (nota dinas usulan, notulen, surat
                        permohonan mitra bila ada) → disetujui TL dan Asman →
                        Laksdan mengisi nomor (otomatis berurutan), tanggal, dan
                        perubahan pasal dari kontrak, lalu menerbitkan
                        amandemen.
                    </p>
                </div>
                {canStart && (
                    <Button
                        disabled={starting}
                        onClick={() =>
                            router.post(
                                procurements.amendments.store(procurementId)
                                    .url,
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setStarting(true),
                                    onFinish: () => setStarting(false),
                                },
                            )
                        }
                    >
                        <FilePlus2 className="size-4" />
                        Ajukan Amandemen
                    </Button>
                )}
            </section>

            {amendments.length === 0 ? (
                <p className="rounded-md border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                    Belum ada amandemen.
                    {!canStart &&
                        ' Amandemen dapat diajukan pengusul setelah dokumen kontrak dibuat.'}
                </p>
            ) : (
                [...amendments]
                    .reverse()
                    .map((amendment) => (
                        <AmendmentCard
                            key={amendment.id}
                            procurementId={procurementId}
                            amendment={amendment}
                            articles={articles}
                        />
                    ))
            )}
        </div>
    );
}

function AmendmentCard({
    procurementId,
    amendment,
    articles,
}: {
    procurementId: number;
    amendment: Amendment;
    articles: ContractArticle[];
}) {
    const args = { procurement: procurementId, amendment: amendment.id };

    return (
        <section className="space-y-4 rounded-md border border-border bg-card p-4">
            <header className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold">
                        Amandemen {amendment.roman}
                    </p>
                    <p className="tabular text-xs text-muted-foreground">
                        {amendment.number ?? 'Nomor belum diisi'}
                        {amendment.created_by
                            ? ` · oleh ${amendment.created_by}`
                            : ''}
                    </p>
                </div>
                <span
                    className={`rounded-sm px-2 py-0.5 text-xs font-medium ${
                        STATE_TONE[amendment.state] ??
                        'bg-amber-500/10 text-amber-800 dark:text-amber-300'
                    }`}
                >
                    {amendment.state_label}
                    {amendment.revision > 0
                        ? ` · revisi ke-${amendment.revision}`
                        : ''}
                </span>
            </header>

            {amendment.state === 'dikembalikan' && amendment.review_note && (
                <p className="flex items-start gap-1.5 rounded-sm border border-destructive/40 bg-destructive/5 p-2.5 text-xs">
                    <RotateCcw className="mt-0.5 size-3.5 shrink-0 text-destructive" />
                    <span>
                        <b>Yang harus diperbaiki:</b> {amendment.review_note}
                    </span>
                </p>
            )}

            {amendment.can.update ? (
                <RequestForm
                    procurementId={procurementId}
                    amendment={amendment}
                />
            ) : (
                <AmendmentSummary amendment={amendment} />
            )}

            {amendment.can.issue && (
                <IssueForm
                    procurementId={procurementId}
                    amendment={amendment}
                    articles={articles}
                />
            )}

            <FileList procurementId={procurementId} amendment={amendment} />

            {amendment.document && (
                <div className="flex flex-wrap items-center gap-2 rounded-sm border border-border bg-muted/40 p-2.5 text-xs">
                    <FileText className="size-4 text-primary" />
                    <span className="font-medium">
                        {amendment.document.title}
                    </span>
                    <a
                        href={amendment.document.preview_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-primary hover:underline"
                    >
                        Pratinjau
                    </a>
                    <a
                        href={amendment.document.edit_url}
                        className="text-primary hover:underline"
                    >
                        Buka di editor
                    </a>
                </div>
            )}

            {amendment.decisions.length > 0 && (
                <ul className="space-y-1 text-xs text-muted-foreground">
                    {amendment.decisions.map((decision) => (
                        <li
                            key={decision.level}
                            className="flex items-center gap-1.5"
                        >
                            <CheckCircle2 className="size-3.5 text-emerald-600" />
                            {decision.level}
                            {decision.by ? ` (${decision.by})` : ''} ·{' '}
                            {formatDateTime(decision.at)}
                        </li>
                    ))}
                </ul>
            )}

            {amendment.can.update && (
                <ActionBar
                    missing={amendment.missing}
                    label={
                        amendment.state === 'dikembalikan'
                            ? 'Ajukan Ulang Amandemen'
                            : 'Ajukan untuk Persetujuan'
                    }
                    url={procurements.amendments.submit(args).url}
                />
            )}

            {amendment.can.issue && (
                <ActionBar
                    missing={amendment.missing}
                    label="Terbitkan Amandemen"
                    url={procurements.amendments.issue(args).url}
                />
            )}

            {amendment.can.decide && (
                <DecisionForm
                    procurementId={procurementId}
                    amendment={amendment}
                />
            )}
        </section>
    );
}

/** The proposer's request: the Nota Dinas usulan and the Notulen date. */
function RequestForm({
    procurementId,
    amendment,
}: {
    procurementId: number;
    amendment: Amendment;
}) {
    const form = useForm({
        memo_number: amendment.memo_number ?? '',
        memo_date: amendment.memo_date ?? '',
        meeting_date: amendment.meeting_date ?? '',
    });

    const field = (
        name: 'memo_number' | 'memo_date' | 'meeting_date',
        label: string,
        type: 'text' | 'date' = 'text',
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`amd-${amendment.id}-${name}`}>{label}</Label>
            <Input
                id={`amd-${amendment.id}-${name}`}
                type={type}
                className="tabular"
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
            />
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <form
            className="space-y-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    procurements.amendments.update({
                        procurement: procurementId,
                        amendment: amendment.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <div className="grid gap-3 md:grid-cols-3">
                {field('memo_number', 'Nomor Nota Dinas Usulan')}
                {field('memo_date', 'Tanggal Nota Dinas Usulan', 'date')}
                {field('meeting_date', 'Tanggal Notulen Rapat', 'date')}
            </div>
            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Permohonan
            </Button>
        </form>
    );
}

/**
 * Laksdan's part: the number (drawn automatically, correctable), the dates,
 * and the articles that change, each picked from the contract with what it
 * said there, and what it says now.
 */
function IssueForm({
    procurementId,
    amendment,
    articles,
}: {
    procurementId: number;
    amendment: Amendment;
    articles: ContractArticle[];
}) {
    const form = useForm<{
        number: string;
        amendment_date: string;
        contract_date: string;
        changes: AmendmentChange[];
    }>({
        number: amendment.number ?? '',
        amendment_date: amendment.amendment_date ?? '',
        contract_date: amendment.contract_date ?? '',
        changes:
            amendment.changes.length > 0
                ? amendment.changes
                : [{ article: '', original_text: '', new_text: '' }],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const setChange = (index: number, patch: Partial<AmendmentChange>) =>
        form.setData(
            'changes',
            form.data.changes.map((change, at) =>
                at === index ? { ...change, ...patch } : change,
            ),
        );

    const field = (
        name: 'number' | 'amendment_date' | 'contract_date',
        label: string,
        type: 'text' | 'date' = 'text',
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`amd-${amendment.id}-${name}`}>{label}</Label>
            <Input
                id={`amd-${amendment.id}-${name}`}
                type={type}
                className="tabular"
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
            />
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <form
            className="space-y-4 rounded-sm border border-border p-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    procurements.amendments.issueData({
                        procurement: procurementId,
                        amendment: amendment.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <p className="text-sm font-semibold">
                Penerbitan Amandemen (Laksdan)
            </p>
            <div className="grid gap-3 md:grid-cols-3">
                {field('number', 'Nomor Amandemen (otomatis, bisa dikoreksi)')}
                {field('amendment_date', 'Tanggal Amandemen', 'date')}
                {field('contract_date', 'Tanggal Kontrak', 'date')}
            </div>

            <div className="space-y-3">
                <p className="text-sm font-semibold">
                    Pasal 1 · Perubahan Ketentuan
                </p>
                {articles.length === 0 && (
                    <p className="text-xs text-muted-foreground">
                        Pasal kontrak tidak terbaca dari dokumen kontrak; ketik
                        pasal dan bunyi semula secara manual.
                    </p>
                )}
                {form.data.changes.map((change, index) => (
                    <ChangeEditor
                        key={index}
                        amendmentId={amendment.id}
                        index={index}
                        change={change}
                        articles={articles}
                        errors={errors}
                        canRemove={form.data.changes.length > 1}
                        onChange={(patch) => setChange(index, patch)}
                        onRemove={() =>
                            form.setData(
                                'changes',
                                form.data.changes.filter(
                                    (_, at) => at !== index,
                                ),
                            )
                        }
                    />
                ))}
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        form.setData('changes', [
                            ...form.data.changes,
                            { article: '', original_text: '', new_text: '' },
                        ])
                    }
                >
                    <Plus className="size-4" />
                    Tambah Perubahan
                </Button>
            </div>

            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Data Amandemen
            </Button>
        </form>
    );
}

/**
 * One changed article, kept simple: pick the article (ayat) from the
 * contract, its wording comes along and is shown as the document will print
 * it; "Menjadi" starts from that wording, so only what changes is edited.
 */
function ChangeEditor({
    amendmentId,
    index,
    change,
    articles,
    errors,
    canRemove,
    onChange,
    onRemove,
}: {
    amendmentId: number;
    index: number;
    change: AmendmentChange;
    articles: ContractArticle[];
    errors: Record<string, string | undefined>;
    canRemove: boolean;
    onChange: (patch: Partial<AmendmentChange>) => void;
    onRemove: () => void;
}) {
    // Typed by hand when the contract offers nothing to pick from.
    const [editOriginal, setEditOriginal] = useState(articles.length === 0);
    const picked = articles.findIndex(
        (article) => article.article === change.article,
    );

    return (
        <div className="space-y-3 rounded-sm border border-border p-3">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-semibold">
                    Perubahan {index + 1}
                    {change.article ? (
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            · {change.article}
                        </span>
                    ) : null}
                </p>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="text-destructive hover:text-destructive"
                    disabled={!canRemove}
                    onClick={onRemove}
                >
                    <Trash2 className="size-3.5" />
                    Hapus
                </Button>
            </div>

            {articles.length > 0 ? (
                <div className="grid gap-1.5">
                    <Label>1. Pilih pasal / ayat yang diubah</Label>
                    <Select
                        value={picked >= 0 ? String(picked) : ''}
                        onValueChange={(value) => {
                            const article = articles[Number(value)];

                            if (article) {
                                // "Menjadi" starts from the old wording.
                                onChange({
                                    article: article.article,
                                    original_text: article.text,
                                    new_text: article.text,
                                });
                            }
                        }}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Pilih dari pasal kontrak, mis. PASAL 8 HARGA PEKERJAAN Ayat 1" />
                        </SelectTrigger>
                        <SelectContent className="max-h-80">
                            {articles.map((article, at) => (
                                <SelectItem
                                    key={`${article.article}-${at}`}
                                    value={String(at)}
                                >
                                    {article.article}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            ) : (
                <div className="grid gap-1.5">
                    <Label htmlFor={`amd-${amendmentId}-article-${index}`}>
                        1. Pasal / ayat yang diubah
                    </Label>
                    <Input
                        id={`amd-${amendmentId}-article-${index}`}
                        value={change.article}
                        placeholder="Contoh: PASAL 8 HARGA PEKERJAAN Ayat 1"
                        onChange={(event) =>
                            onChange({ article: event.target.value })
                        }
                    />
                </div>
            )}
            <InputError message={errors[`changes.${index}.article`]} />

            {change.article !== '' || articles.length === 0 ? (
                <div className="grid gap-3 lg:grid-cols-2">
                    <div className="grid content-start gap-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label>Semula berbunyi</Label>
                            {articles.length > 0 && (
                                <button
                                    type="button"
                                    className="text-xs text-primary hover:underline"
                                    onClick={() =>
                                        setEditOriginal(!editOriginal)
                                    }
                                >
                                    {editOriginal
                                        ? 'Selesai'
                                        : 'Koreksi teks semula'}
                                </button>
                            )}
                        </div>
                        {editOriginal ? (
                            <Textarea
                                rows={8}
                                value={change.original_text}
                                onChange={(event) =>
                                    onChange({
                                        original_text: event.target.value,
                                    })
                                }
                            />
                        ) : (
                            <div className="max-h-72 overflow-y-auto rounded-md border border-border bg-muted/30 p-3">
                                <ArticleText text={change.original_text} />
                            </div>
                        )}
                        <InputError
                            message={errors[`changes.${index}.original_text`]}
                        />
                    </div>

                    <div className="grid content-start gap-1.5">
                        <Label htmlFor={`amd-${amendmentId}-new-${index}`}>
                            2. Menjadi — ubah bagian yang berubah saja
                        </Label>
                        <Textarea
                            id={`amd-${amendmentId}-new-${index}`}
                            rows={8}
                            value={change.new_text}
                            onChange={(event) =>
                                onChange({ new_text: event.target.value })
                            }
                        />
                        <p className="text-[11px] text-muted-foreground">
                            Baris diawali “1.” menjadi nomor ayat; baris diawali
                            spasi lalu “a.” menjadi sub-butir menjorok, seperti
                            di kontrak.
                        </p>
                        <InputError
                            message={errors[`changes.${index}.new_text`]}
                        />
                        {change.new_text.trim() !== '' && (
                            <div className="rounded-md border border-dashed border-border p-3">
                                <p className="mb-1 text-[11px] font-medium text-muted-foreground">
                                    Tampilan di dokumen
                                </p>
                                <ArticleText text={change.new_text} />
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                <p className="text-xs text-muted-foreground">
                    Pilih pasal dulu; bunyi semula terisi dari kontrak dan
                    “Menjadi” disiapkan dari teks yang sama.
                </p>
            )}
        </div>
    );
}

/** The lines of an article's wording, each with its marker and indent. */
function layoutArticle(text: string) {
    const rows: Array<{
        indented: boolean;
        marker: string | null;
        body: string;
        under: boolean;
    }> = [];
    let numbered = false;

    for (const raw of text.split(/\r?\n/)) {
        if (raw.trim() === '') {
            continue;
        }

        const indented = /^\s+/.test(raw);
        const line = raw.trim();
        const match = line.match(
            /^((?:\d+|[a-z]|[ivxlc]+)\.|\(\d+\)|-)\s+(.*)$/,
        );
        const marker = match ? match[1] : null;

        if (!indented && marker) {
            numbered = true;
        }

        rows.push({
            indented,
            marker,
            body: match ? match[2] : line,
            under: numbered,
        });
    }

    return rows;
}

/**
 * An article's wording laid out as the amendment prints it: the ayat number
 * hanging beside its text, list items indented under it. Mirrors the
 * server's layout of the document.
 */
function ArticleText({ text }: { text: string }) {
    const rows = layoutArticle(text);

    if (rows.length === 0) {
        return <p className="text-xs text-muted-foreground">—</p>;
    }

    return (
        <div className="space-y-0.5 text-xs leading-relaxed text-foreground">
            {rows.map((row, at) => (
                <div
                    key={at}
                    className={cn(
                        'flex gap-1.5 text-justify',
                        (row.indented || (!row.marker && row.under)) && 'pl-5',
                    )}
                >
                    {row.marker && (
                        <span className="w-5 shrink-0">{row.marker}</span>
                    )}
                    <span className="min-w-0 flex-1">{row.body}</span>
                </div>
            ))}
        </div>
    );
}

/** What the amendment says, for everyone who cannot edit it. */
function AmendmentSummary({ amendment }: { amendment: Amendment }) {
    const rows: Array<[string, string | null]> = [
        ['Tanggal Nota Dinas Usulan', amendment.memo_date],
        ['Tanggal Notulen Rapat', amendment.meeting_date],
        ['Tanggal Amandemen', amendment.amendment_date],
        ['Tanggal Kontrak', amendment.contract_date],
    ];

    return (
        <div className="space-y-3 text-sm">
            <dl className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">
                        Nomor Nota Dinas Usulan
                    </dt>
                    <dd className="tabular font-medium">
                        {amendment.memo_number ?? '—'}
                    </dd>
                </div>
                {rows.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-3">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="tabular font-medium">
                            {value ? formatDate(value) : '—'}
                        </dd>
                    </div>
                ))}
            </dl>
            {amendment.changes.length > 0 && (
                <ol className="list-decimal space-y-2 pl-5">
                    {amendment.changes.map((change, index) => (
                        <li key={index}>
                            <p className="font-medium">{change.article}</p>
                            <p className="text-xs text-muted-foreground">
                                Semula:
                            </p>
                            <ArticleText text={change.original_text} />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Menjadi:
                            </p>
                            <ArticleText text={change.new_text} />
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

/** The four papers, uploaded by the proposer while they may edit. */
function FileList({
    procurementId,
    amendment,
}: {
    procurementId: number;
    amendment: Amendment;
}) {
    return (
        <div className="space-y-2">
            <p className="text-sm font-semibold">Dokumen Permohonan</p>
            <ul className="divide-y divide-border rounded-sm border border-border">
                {amendment.files.map((entry) => (
                    <FileRow
                        key={entry.kind}
                        procurementId={procurementId}
                        amendment={amendment}
                        entry={entry}
                    />
                ))}
            </ul>
        </div>
    );
}

function FileRow({
    procurementId,
    amendment,
    entry,
}: {
    procurementId: number;
    amendment: Amendment;
    entry: AmendmentFile;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | undefined>();

    return (
        <li className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
            <div className="min-w-0">
                <p className="font-medium">
                    {entry.label}
                    {entry.required && (
                        <span className="text-destructive"> *</span>
                    )}
                </p>
                {entry.file ? (
                    <a
                        href={entry.file.url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        <Paperclip className="size-3" />
                        {entry.file.name}
                    </a>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Belum diunggah
                    </p>
                )}
                <InputError message={error} />
            </div>
            {/* The attendance list is no longer asked for; one filed stays. */}
            {amendment.can.update && entry.kind !== 'daftar_hadir' && (
                <>
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
                                procurements.amendments.upload({
                                    procurement: procurementId,
                                    amendment: amendment.id,
                                }).url,
                                { kind: entry.kind, file },
                                {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onStart: () => {
                                        setUploading(true);
                                        setError(undefined);
                                    },
                                    onError: (errors) =>
                                        setError(errors.file ?? errors.kind),
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
                        {entry.file ? 'Ganti' : 'Unggah'}
                    </Button>
                </>
            )}
        </li>
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
    amendment,
}: {
    procurementId: number;
    amendment: Amendment;
}) {
    const form = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    const decide = (approved: boolean) => {
        form.transform((data) => ({ ...data, approved }));
        form.post(
            procurements.amendments.decide({
                procurement: procurementId,
                amendment: amendment.id,
            }).url,
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-2 border-t border-border pt-3">
            <Label htmlFor={`amd-${amendment.id}-note`}>
                Catatan Persetujuan
            </Label>
            <Textarea
                id={`amd-${amendment.id}-note`}
                rows={2}
                value={form.data.note}
                onChange={(event) => form.setData('note', event.target.value)}
                placeholder="Wajib diisi bila dikembalikan"
            />
            <InputError message={form.errors.note} />
            <div className="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    disabled={form.processing}
                    onClick={() => decide(true)}
                >
                    Setujui
                </Button>
                <Button
                    size="sm"
                    variant="destructive"
                    disabled={form.processing}
                    onClick={() => decide(false)}
                >
                    Kembalikan
                </Button>
            </div>
        </div>
    );
}
