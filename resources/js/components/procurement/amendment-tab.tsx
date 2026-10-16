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
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';

type AmendmentChange = {
    article: string;
    original_text: string;
    new_text: string;
};

type AmendmentFile = {
    kind: string;
    label: string;
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
    can: { update: boolean; decide: boolean };
};

const STATE_TONE: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    dikembalikan: 'bg-destructive/10 text-destructive',
    disetujui: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
};

/**
 * The Amandemen tab: every amendment of the contract. The proposer drafts
 * one (four papers, numbers and dates, the articles that change) and sends
 * it to their TL, the division's Asman and then TL Pengadaan.
 */
export function AmendmentTab({
    procurementId,
    amendments,
    canStart,
}: {
    procurementId: number;
    amendments: Amendment[];
    canStart: boolean;
}) {
    const [starting, setStarting] = useState(false);

    return (
        <div className="space-y-4">
            <section className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-4">
                <div className="text-sm">
                    <p className="font-semibold">Amandemen Kontrak</p>
                    <p className="text-xs text-muted-foreground">
                        Diajukan pengusul setelah kontrak dibuat, disetujui TL,
                        Asman bidang, lalu TL Pengadaan. Dokumen amandemen
                        dibuat otomatis saat diajukan.
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
                        />
                    ))
            )}
        </div>
    );
}

function AmendmentCard({
    procurementId,
    amendment,
}: {
    procurementId: number;
    amendment: Amendment;
}) {
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
                <AmendmentForm
                    procurementId={procurementId}
                    amendment={amendment}
                />
            ) : (
                <AmendmentSummary amendment={amendment} />
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
                <SubmitBar
                    procurementId={procurementId}
                    amendment={amendment}
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

/** The numbers, dates and changed articles, while the proposer may edit. */
function AmendmentForm({
    procurementId,
    amendment,
}: {
    procurementId: number;
    amendment: Amendment;
}) {
    const form = useForm<{
        number: string;
        amendment_date: string;
        contract_date: string;
        meeting_date: string;
        memo_number: string;
        memo_date: string;
        request_number: string;
        request_date: string;
        changes: AmendmentChange[];
    }>({
        number: amendment.number ?? '',
        amendment_date: amendment.amendment_date ?? '',
        contract_date: amendment.contract_date ?? '',
        meeting_date: amendment.meeting_date ?? '',
        memo_number: amendment.memo_number ?? '',
        memo_date: amendment.memo_date ?? '',
        request_number: amendment.request_number ?? '',
        request_date: amendment.request_date ?? '',
        changes:
            amendment.changes.length > 0
                ? amendment.changes
                : [{ article: '', original_text: '', new_text: '' }],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const setChange = (
        index: number,
        key: keyof AmendmentChange,
        value: string,
    ) =>
        form.setData(
            'changes',
            form.data.changes.map((change, at) =>
                at === index ? { ...change, [key]: value } : change,
            ),
        );

    const field = (
        name:
            | 'number'
            | 'amendment_date'
            | 'contract_date'
            | 'meeting_date'
            | 'memo_number'
            | 'memo_date'
            | 'request_number'
            | 'request_date',
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
            className="space-y-4"
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
            <div className="grid gap-3 md:grid-cols-2">
                {field('number', 'Nomor Amandemen')}
                {field('amendment_date', 'Tanggal Amandemen', 'date')}
                {field('contract_date', 'Tanggal Kontrak', 'date')}
                {field('meeting_date', 'Tanggal Notulen Rapat', 'date')}
                {field('memo_number', 'Nomor Nota Dinas Amandemen')}
                {field('memo_date', 'Tanggal Nota Dinas Amandemen', 'date')}
                {field('request_number', 'Nomor Surat Permohonan Amandemen')}
                {field(
                    'request_date',
                    'Tanggal Surat Permohonan Amandemen',
                    'date',
                )}
            </div>

            <div className="space-y-3">
                <p className="text-sm font-semibold">
                    Pasal 1 · Perubahan Ketentuan
                </p>
                {form.data.changes.map((change, index) => (
                    <div
                        key={index}
                        className="space-y-2 rounded-sm border border-border p-3"
                    >
                        <div className="flex items-end gap-2">
                            <div className="grid flex-1 gap-1.5">
                                <Label
                                    htmlFor={`amd-${amendment.id}-article-${index}`}
                                >
                                    Pasal / ayat yang diubah
                                </Label>
                                <Input
                                    id={`amd-${amendment.id}-article-${index}`}
                                    value={change.article}
                                    placeholder="Contoh: PASAL 8 HARGA PEKERJAAN Ayat 1"
                                    onChange={(event) =>
                                        setChange(
                                            index,
                                            'article',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label="Hapus perubahan"
                                disabled={form.data.changes.length === 1}
                                onClick={() =>
                                    form.setData(
                                        'changes',
                                        form.data.changes.filter(
                                            (_, at) => at !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                        <InputError
                            message={errors[`changes.${index}.article`]}
                        />
                        <div className="grid gap-2 md:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label>Semula berbunyi</Label>
                                <Textarea
                                    rows={5}
                                    value={change.original_text}
                                    onChange={(event) =>
                                        setChange(
                                            index,
                                            'original_text',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`changes.${index}.original_text`]
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Menjadi</Label>
                                <Textarea
                                    rows={5}
                                    value={change.new_text}
                                    onChange={(event) =>
                                        setChange(
                                            index,
                                            'new_text',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`changes.${index}.new_text`]
                                    }
                                />
                            </div>
                        </div>
                    </div>
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
                Simpan Amandemen
            </Button>
        </form>
    );
}

/** What the amendment says, for everyone who cannot edit it. */
function AmendmentSummary({ amendment }: { amendment: Amendment }) {
    const rows: Array<[string, string | null]> = [
        ['Tanggal Amandemen', amendment.amendment_date],
        ['Tanggal Kontrak', amendment.contract_date],
        ['Tanggal Notulen Rapat', amendment.meeting_date],
        ['Tanggal Nota Dinas', amendment.memo_date],
        ['Tanggal Surat Permohonan', amendment.request_date],
    ];

    return (
        <div className="space-y-3 text-sm">
            <dl className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">Nomor Nota Dinas</dt>
                    <dd className="tabular font-medium">
                        {amendment.memo_number ?? '—'}
                    </dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">
                        Nomor Surat Permohonan
                    </dt>
                    <dd className="tabular font-medium">
                        {amendment.request_number ?? '—'}
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
                            <p className="text-xs whitespace-pre-line">
                                {change.original_text}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Menjadi:
                            </p>
                            <p className="text-xs whitespace-pre-line">
                                {change.new_text}
                            </p>
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
            <p className="text-sm font-semibold">Dokumen Pendukung</p>
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
                <p className="font-medium">{entry.label}</p>
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
            {amendment.can.update && (
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

/** What still holds the request back, and the button to send it. */
function SubmitBar({
    procurementId,
    amendment,
}: {
    procurementId: number;
    amendment: Amendment;
}) {
    const [sending, setSending] = useState(false);

    return (
        <div className="space-y-2 border-t border-border pt-3">
            {amendment.missing.length > 0 && (
                <div className="space-y-1 rounded-sm border border-border bg-muted/40 p-2.5 text-xs">
                    <p className="flex items-center gap-1.5 font-medium">
                        <CircleAlert className="size-3.5 text-amber-600" />
                        Lengkapi sebelum diajukan (simpan isian terlebih
                        dahulu):
                    </p>
                    <ul className="list-disc pl-4 text-muted-foreground">
                        {amendment.missing.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}
            <Button
                disabled={sending || amendment.missing.length > 0}
                onClick={() =>
                    router.post(
                        procurements.amendments.submit({
                            procurement: procurementId,
                            amendment: amendment.id,
                        }).url,
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
                {amendment.state === 'dikembalikan'
                    ? 'Ajukan Ulang Amandemen'
                    : 'Ajukan untuk Persetujuan'}
            </Button>
        </div>
    );
}

/** The TL, Asman or TL Pengadaan decision at the amendment's level. */
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
