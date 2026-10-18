import { router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    FileText,
    Pencil,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency, formatDate } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { PartnerCandidate } from '@/types';

type CandidateValues = {
    name: string;
    director_name: string;
    address: string;
    quotation_number: string;
    quotation_date: string;
    quotation_value: string;
    quotation_file: File | null;
};

/**
 * The partner candidates (calon mitra) the planning PIC lists on the
 * "Penawaran" step, each with its offer: the total before negotiation and
 * the scanned quotation. In execution the "Pelaksana" step picks the one
 * that does the work.
 */
export function PartnerCandidatesCard({
    procurementId,
    candidates,
    editable,
    direct = false,
}: {
    procurementId: number;
    candidates: PartnerCandidate[];
    editable: boolean;
    /** SPPL: appointed directly, one partner who is the executor at once. */
    direct?: boolean;
}) {
    const [editing, setEditing] = useState<number | 'new' | null>(null);
    const canAdd =
        editable && editing === null && !(direct && candidates.length > 0);

    return (
        <div className="space-y-2 rounded-sm border border-border bg-muted/30 p-2.5">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="flex items-center gap-1.5 text-xs font-medium">
                        <Users className="size-3.5 text-primary" />
                        {direct ? 'Mitra (Tunjuk Langsung)' : 'Calon Mitra'}
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                        {direct
                            ? 'SPPL ditunjuk langsung: cukup satu mitra, dan mitra ini langsung menjadi pelaksana.'
                            : 'Bisa lebih dari satu. Pelaksana dipilih dari daftar ini pada tahapan “Pelaksana” di tab Pelaksanaan.'}{' '}
                        Setiap calon wajib lengkap beserta nilai dan berkas
                        penawaran harganya.
                    </p>
                </div>
                {canAdd && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setEditing('new')}
                    >
                        <Plus className="size-3.5" />
                        {direct ? 'Isi Mitra' : 'Tambah Calon Mitra'}
                    </Button>
                )}
            </div>

            {candidates.length === 0 && editing !== 'new' && (
                <p className="text-xs text-muted-foreground">
                    Belum ada calon mitra.
                </p>
            )}

            <ul className="divide-y divide-border rounded-sm border border-border bg-background">
                {candidates.map((candidate) =>
                    editing === candidate.id ? (
                        <li key={candidate.id} className="px-3 py-2.5">
                            <CandidateForm
                                procurementId={procurementId}
                                candidate={candidate}
                                onDone={() => setEditing(null)}
                            />
                        </li>
                    ) : (
                        <li
                            key={candidate.id}
                            className="flex flex-wrap items-start justify-between gap-3 px-3 py-2.5"
                        >
                            <div className="min-w-0 space-y-0.5 text-sm">
                                <p className="flex flex-wrap items-center gap-1.5 font-medium">
                                    {candidate.name}
                                    {candidate.is_selected && (
                                        <span className="inline-flex items-center gap-1 rounded-sm bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                                            <CheckCircle2 className="size-3" />
                                            Pelaksana terpilih
                                        </span>
                                    )}
                                </p>
                                <p className="tabular text-xs font-medium text-foreground">
                                    Nilai penawaran (sebelum nego):{' '}
                                    {candidate.quotation_value === null
                                        ? '—'
                                        : formatCurrency(
                                              candidate.quotation_value,
                                          )}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {[
                                        candidate.director_name &&
                                            `Direktur: ${candidate.director_name}`,
                                        candidate.quotation_number &&
                                            `Penawaran ${candidate.quotation_number}`,
                                        candidate.quotation_date &&
                                            formatDate(
                                                candidate.quotation_date,
                                            ),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                </p>
                                {candidate.address && (
                                    <p className="text-xs text-muted-foreground">
                                        {candidate.address}
                                    </p>
                                )}
                                {candidate.quotation_file && (
                                    <a
                                        href={candidate.quotation_file.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                    >
                                        <FileText className="size-3.5" />
                                        {candidate.quotation_file.name}
                                    </a>
                                )}
                                {candidate.missing.length > 0 && (
                                    <p className="flex items-start gap-1 text-xs text-amber-700 dark:text-amber-400">
                                        <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                        Belum lengkap:{' '}
                                        {candidate.missing.join(', ')}
                                    </p>
                                )}
                            </div>
                            {editable && editing === null && (
                                <div className="flex gap-1">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setEditing(candidate.id)}
                                    >
                                        <Pencil className="size-3.5" />
                                        Ubah
                                    </Button>
                                    {!candidate.is_selected && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Hapus calon mitra ${candidate.name}?`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        procurements.partnerCandidates.destroy(
                                                            {
                                                                procurement:
                                                                    procurementId,
                                                                candidate:
                                                                    candidate.id,
                                                            },
                                                        ).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 className="size-3.5" />
                                            Hapus
                                        </Button>
                                    )}
                                </div>
                            )}
                        </li>
                    ),
                )}
            </ul>

            {editing === 'new' && (
                <div className="rounded-sm border border-border bg-background px-3 py-2.5">
                    <CandidateForm
                        procurementId={procurementId}
                        candidate={null}
                        onDone={() => setEditing(null)}
                    />
                </div>
            )}
        </div>
    );
}

function CandidateForm({
    procurementId,
    candidate,
    onDone,
}: {
    procurementId: number;
    candidate: PartnerCandidate | null;
    onDone: () => void;
}) {
    const form = useForm<CandidateValues>({
        name: candidate?.name ?? '',
        director_name: candidate?.director_name ?? '',
        address: candidate?.address ?? '',
        quotation_number: candidate?.quotation_number ?? '',
        quotation_date: candidate?.quotation_date ?? '',
        quotation_value:
            candidate?.quotation_value === null ||
            candidate?.quotation_value === undefined
                ? ''
                : String(candidate.quotation_value),
        quotation_file: null,
    });

    const prefix = `candidate-${candidate?.id ?? 'new'}`;

    const submit = () => {
        // Sent as form data so the quotation scan travels along; an update
        // is spoofed as PUT for the same reason.
        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onDone,
        };

        if (candidate === null) {
            form.post(
                procurements.partnerCandidates.store(procurementId).url,
                options,
            );

            return;
        }

        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(
            procurements.partnerCandidates.update({
                procurement: procurementId,
                candidate: candidate.id,
            }).url,
            options,
        );
    };

    const text = (
        name: Exclude<keyof CandidateValues, 'address' | 'quotation_file'>,
        label: string,
        type: 'text' | 'date' | 'number' = 'text',
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`${prefix}-${name}`}>{label}</Label>
            <Input
                id={`${prefix}-${name}`}
                type={type}
                min={type === 'number' ? 0 : undefined}
                className={type === 'text' ? undefined : 'tabular'}
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
                submit();
            }}
        >
            <div className="grid gap-3 md:grid-cols-2">
                {text('name', 'Nama Calon Mitra')}
                {text('director_name', 'Nama Direktur')}
                {text('quotation_number', 'Nomor Surat Penawaran')}
                {text('quotation_date', 'Tanggal Surat Penawaran', 'date')}
                {text(
                    'quotation_value',
                    'Nilai Total Penawaran Sebelum Nego (Rp)',
                    'number',
                )}
                <div className="grid gap-1.5">
                    <Label htmlFor={`${prefix}-quotation_file`}>
                        Berkas Penawaran Harga (PDF/JPG/PNG)
                    </Label>
                    <Input
                        id={`${prefix}-quotation_file`}
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(event) =>
                            form.setData(
                                'quotation_file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    {candidate?.quotation_file && (
                        <p className="text-[11px] text-muted-foreground">
                            Sudah ada: {candidate.quotation_file.name}. Pilih
                            berkas baru untuk menggantinya.
                        </p>
                    )}
                    <InputError message={form.errors.quotation_file} />
                </div>
                <div className="grid gap-1.5 md:col-span-2">
                    <Label htmlFor={`${prefix}-address`}>
                        Alamat Perusahaan
                    </Label>
                    <Textarea
                        id={`${prefix}-address`}
                        rows={2}
                        value={form.data.address}
                        onChange={(event) =>
                            form.setData('address', event.target.value)
                        }
                    />
                    <InputError message={form.errors.address} />
                </div>
            </div>
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    Simpan
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onDone}
                >
                    Batal
                </Button>
            </div>
        </form>
    );
}
