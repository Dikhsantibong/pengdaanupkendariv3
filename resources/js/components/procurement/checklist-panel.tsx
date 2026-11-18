import { Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Circle,
    Download,
    Eye,
    FileCheck2,
    FilePlus2,
    FileWarning,
    Lock,
    Pencil,
    Save,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import {
    ChecklistFileList,
    LetterTemplateLink,
} from '@/components/procurement/checklist-file-list';
import { ChecklistInputForm } from '@/components/procurement/checklist-input-form';
import {
    SignedUploadDeleteButton,
    SignedUploadList,
} from '@/components/procurement/signed-upload-list';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import type {
    BeritaAcaraInputEntry,
    ChecklistDocument,
    ChecklistRow,
    PartnerCandidate,
} from '@/types';

export function ChecklistPanel({
    procurementId,
    title,
    description,
    rows,
    editable,
    canManageDocuments,
    lockedReason,
    partnerCandidates = [],
    candidatesEditable = false,
    directAppointment = false,
}: {
    procurementId: number;
    /** Left out where the surrounding tab already names the stage. */
    title?: string;
    description?: string;
    rows: ChecklistRow[];
    editable: boolean;
    /** Generating, editing and filing documents follows the document policy. */
    canManageDocuments: boolean;
    lockedReason?: string;
    /** The calon mitra the "Pelaksana" step picks the executor from. */
    partnerCandidates?: PartnerCandidate[];
    /** Whether the "Penawaran" step may list and correct the calon mitra. */
    candidatesEditable?: boolean;
    /** SPPL: one partner, appointed directly. */
    directAppointment?: boolean;
}) {
    const completed = rows.filter((row) => row.is_completed).length;
    const percentage =
        rows.length > 0 ? Math.round((completed / rows.length) * 100) : 0;

    const toggle = (row: ChecklistRow, next: boolean) => {
        router.put(
            procurements.checklists.update({
                procurement: procurementId,
                checklist: row.id,
            }).url,
            { is_completed: next, notes: row.notes ?? '' },
            { preserveScroll: true },
        );
    };

    return (
        <section className="rounded-md border border-border bg-card">
            <header className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    {title && (
                        <h2 className="text-sm font-semibold text-foreground">
                            {title}
                        </h2>
                    )}
                    {description && (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>

                <div className="w-full space-y-1.5 sm:w-48">
                    <div className="tabular flex items-center justify-between text-xs">
                        <span className="font-semibold">{percentage}%</span>
                        <span className="text-muted-foreground">
                            {completed}/{rows.length} selesai
                        </span>
                    </div>
                    <Progress value={percentage} />
                </div>
            </header>

            {!editable && lockedReason && (
                <p className="flex items-center gap-2 border-b border-border bg-muted/40 px-4 py-2 text-xs text-muted-foreground">
                    <Lock className="size-3.5" />
                    {lockedReason}
                </p>
            )}

            <ul className="divide-y divide-border">
                {rows.map((row) => {
                    const inlineBa =
                        row.input?.kind === 'berita_acara' &&
                        (row.input.values.berita_acaras?.length ?? 0) > 0;
                    const baByTypeId = new Map(
                        (row.input?.values.berita_acaras ?? []).map((entry) => [
                            entry.document_type_id,
                            entry,
                        ]),
                    );

                    return (
                        <li
                            key={row.id}
                            className="flex items-start gap-3 px-4 py-3"
                        >
                            <Checkbox
                                id={`checklist-${row.id}`}
                                checked={row.is_completed}
                                disabled={!editable}
                                onCheckedChange={(checked) =>
                                    toggle(row, checked === true)
                                }
                                className="mt-0.5"
                            />

                            <div className="min-w-0 flex-1">
                                <label
                                    htmlFor={`checklist-${row.id}`}
                                    className="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground"
                                >
                                    {row.name}
                                    {row.is_optional && (
                                        <span className="rounded-sm bg-muted px-1.5 py-0.5 text-[0.6875rem] font-normal text-muted-foreground">
                                            Opsional
                                        </span>
                                    )}
                                </label>

                                {row.description && (
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {row.description}
                                    </p>
                                )}

                                {row.is_completed && row.completed_by && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Ditandai oleh {row.completed_by} ·{' '}
                                        {formatDateTime(row.completed_at)}
                                    </p>
                                )}

                                {row.input !== null && !inlineBa && (
                                    <div className="mt-2">
                                        <ChecklistInputForm
                                            procurementId={procurementId}
                                            checklistId={row.id}
                                            input={row.input}
                                            canManage={editable}
                                            partnerCandidates={
                                                partnerCandidates
                                            }
                                            candidatesEditable={
                                                candidatesEditable
                                            }
                                            directAppointment={
                                                directAppointment
                                            }
                                        />
                                    </div>
                                )}

                                {row.needs_upload && (
                                    <ChecklistFileList
                                        procurementId={procurementId}
                                        checklistId={row.id}
                                        name={row.name}
                                        files={row.files}
                                        templateUrl={row.template_url}
                                        canManage={editable}
                                    />
                                )}

                                {/* A letter filed as an upload-only document (the BAST). */}
                                {!row.needs_upload && row.template_url && (
                                    <div className="mt-2">
                                        <LetterTemplateLink
                                            url={row.template_url}
                                        />
                                    </div>
                                )}

                                {row.documents.some(
                                    (d) => !d.is_alternative,
                                ) && (
                                    <div className="mt-2 space-y-1.5">
                                        {row.documents
                                            .filter((d) => !d.is_alternative)
                                            .map((document) => {
                                                const baEntry = baByTypeId.get(
                                                    document.type_id,
                                                );

                                                return (
                                                    <ChecklistDocumentActions
                                                        key={document.type_id}
                                                        procurementId={
                                                            procurementId
                                                        }
                                                        document={document}
                                                        canManage={
                                                            canManageDocuments
                                                        }
                                                        beritaAcara={
                                                            inlineBa &&
                                                            baEntry !==
                                                                undefined
                                                                ? {
                                                                      checklistId:
                                                                          row.id,
                                                                      entry: baEntry,
                                                                      canEdit:
                                                                          editable,
                                                                  }
                                                                : undefined
                                                        }
                                                    />
                                                );
                                            })}
                                    </div>
                                )}

                                {row.documents.some(
                                    (d) => d.is_alternative,
                                ) && (
                                    <div className="mt-2 space-y-1.5 rounded-sm border border-dashed border-border p-2">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Pilih salah satu — cukup satu
                                            dokumen berikut yang diunggah:
                                        </p>
                                        {row.documents
                                            .filter((d) => d.is_alternative)
                                            .map((document) => (
                                                <ChecklistDocumentActions
                                                    key={document.type_id}
                                                    procurementId={
                                                        procurementId
                                                    }
                                                    document={document}
                                                    canManage={
                                                        canManageDocuments
                                                    }
                                                />
                                            ))}
                                    </div>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/**
 * Draft, correct and file the document a checklist step produces.
 *
 * The step cannot be ticked until the signed copy is here, so the whole round
 * trip lives on the step itself rather than on a separate screen.
 */
export function ChecklistDocumentActions({
    procurementId,
    document,
    canManage,
    beritaAcara,
}: {
    procurementId: number;
    document: ChecklistDocument;
    canManage: boolean;
    /** Present on SMART SCM & BA steps so each document holds its BA no. & date inline. */
    beritaAcara?: {
        checklistId: number;
        entry: BeritaAcaraInputEntry;
        canEdit: boolean;
    };
}) {
    const generate = () =>
        router.post(
            procurements.documents.store(procurementId).url,
            { document_type_id: document.type_id },
            { preserveScroll: true },
        );

    return (
        <div className="@container min-w-0 rounded-sm border border-border bg-muted/30 p-2.5">
            <div
                className={cn(
                    'grid min-w-0 gap-3',
                    beritaAcara !== undefined &&
                        '@xl:grid-cols-[minmax(0,1fr)_minmax(18rem,1.05fr)] @xl:items-start',
                )}
            >
                <div className="min-w-0 space-y-1.5">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="flex items-center gap-1.5 text-xs font-medium">
                            {document.is_signed ? (
                                <FileCheck2 className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            ) : (
                                <FileWarning className="size-3.5 text-amber-600 dark:text-amber-400" />
                            )}
                            Dokumen {document.type_name}
                        </span>
                        <span
                            className={
                                document.is_signed
                                    ? 'rounded-sm bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400'
                                    : 'rounded-sm bg-amber-500/10 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400'
                            }
                        >
                            {document.is_signed
                                ? `${document.upload_only ? 'Terunggah' : 'Bertanda tangan'} · ${document.uploads.length} berkas`
                                : 'Wajib diunggah'}
                        </span>
                    </div>

                    {document.upload_only ? (
                        // Filed as-is: no template, nothing to generate or edit.
                        <SignedUploadList
                            procurementId={procurementId}
                            documentId={document.id}
                            documentTypeId={document.type_id}
                            uploads={document.uploads}
                            canManage={canManage}
                            label="Unggah Dokumen Tervalidasi"
                        />
                    ) : document.id === null ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-xs text-muted-foreground">
                                Belum digenerate.
                            </p>
                            {canManage && document.has_template && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={generate}
                                >
                                    <FilePlus2 className="size-3.5" />
                                    Generate
                                </Button>
                            )}
                            {canManage && !document.has_template && (
                                <span className="text-xs text-muted-foreground">
                                    Template belum tersedia — hubungi
                                    Administrator untuk memasang template
                                    dokumen ini.
                                </span>
                            )}
                        </div>
                    ) : (
                        <>
                            <div className="flex flex-wrap items-center gap-1.5">
                                {document.uploads.length === 0 && (
                                    <Button asChild size="sm" variant="outline">
                                        <a
                                            href={
                                                procurements.documents.show(
                                                    {
                                                        procurement:
                                                            procurementId,
                                                        document: document.id,
                                                    },
                                                    { query: { preview: '1' } },
                                                ).url
                                            }
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            title="Pratinjau PDF"
                                        >
                                            <Eye className="size-3.5" />
                                            Preview PDF
                                        </a>
                                    </Button>
                                )}
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={
                                            procurements.documents.show({
                                                procurement: procurementId,
                                                document: document.id,
                                            }).url
                                        }
                                    >
                                        <Download className="size-3.5" />
                                        Unduh PDF
                                    </a>
                                </Button>
                                {canManage && (
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={
                                                procurements.documents.edit({
                                                    procurement: procurementId,
                                                    document: document.id,
                                                }).url
                                            }
                                        >
                                            <Pencil className="size-3.5" />
                                            Edit
                                        </Link>
                                    </Button>
                                )}
                                {canManage && (
                                    <SignedUploadDeleteButton
                                        procurementId={procurementId}
                                        documentId={document.id}
                                        uploads={document.uploads}
                                    />
                                )}
                            </div>

                            <SignedUploadList
                                procurementId={procurementId}
                                documentId={document.id}
                                uploads={document.uploads}
                                canManage={canManage}
                                deleteInline={false}
                            />
                        </>
                    )}
                </div>

                {beritaAcara !== undefined && (
                    <DocumentBeritaAcaraForm
                        key={`${beritaAcara.checklistId}-${beritaAcara.entry.document_type_id}`}
                        procurementId={procurementId}
                        checklistId={beritaAcara.checklistId}
                        entry={beritaAcara.entry}
                        canEdit={beritaAcara.canEdit}
                    />
                )}
            </div>
        </div>
    );
}

/**
 * Side-by-side Nomor & Tanggal Surat/BA form embedded beside its document.
 */
function DocumentBeritaAcaraForm({
    procurementId,
    checklistId,
    entry,
    canEdit,
}: {
    procurementId: number;
    checklistId: number;
    entry: BeritaAcaraInputEntry;
    canEdit: boolean;
}) {
    const savedNumber = entry.ba_number ?? '';
    const savedDate = entry.ba_date ?? '';

    const form = useForm({
        berita_acaras: [
            {
                document_type_id: entry.document_type_id,
                ba_number: savedNumber,
                ba_date: savedDate,
            },
        ],
    });

    const current = form.data.berita_acaras[0] ?? {
        document_type_id: entry.document_type_id,
        ba_number: savedNumber,
        ba_date: savedDate,
    };

    const dirty =
        current.ba_number !== savedNumber || current.ba_date !== savedDate;
    const savedComplete = savedNumber.trim() !== '' && savedDate !== '';
    const errors = form.errors as Record<string, string | undefined>;
    const numberError = errors['berita_acaras.0.ba_number'];
    const dateError = errors['berita_acaras.0.ba_date'];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(
            procurements.checklists.input({
                procurement: procurementId,
                checklist: checklistId,
            }).url,
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const numberId = `doc-ba-number-${checklistId}-${entry.document_type_id}`;
    const dateId = `doc-ba-date-${checklistId}-${entry.document_type_id}`;

    return (
        <form
            onSubmit={submit}
            className="min-w-0 space-y-2 border-t border-border/70 pt-2.5 @xl:border-t-0 @xl:border-l @xl:pt-0 @xl:pl-3"
        >
            <div className="flex flex-wrap items-center justify-between gap-2 text-[11px]">
                <span className="flex items-center gap-1.5 font-medium text-foreground">
                    {savedComplete ? (
                        <CheckCircle2 className="size-3.5 text-emerald-600" />
                    ) : (
                        <Circle className="size-3.5 text-muted-foreground/60" />
                    )}
                    No. Surat / BA &amp; Tanggal
                </span>
                {dirty ? (
                    <span className="font-medium text-amber-600 dark:text-amber-400">
                        Belum disimpan
                    </span>
                ) : savedComplete ? (
                    <span className="font-medium text-emerald-600 dark:text-emerald-400">
                        Tersimpan
                    </span>
                ) : null}
            </div>

            <div className="@container min-w-0">
                <fieldset
                    disabled={!canEdit || form.processing}
                    className="grid w-full min-w-0 grid-cols-1 gap-2 @[15rem]:grid-cols-[minmax(10.5rem,1fr)_auto] @[15rem]:items-end"
                >
                    <div className="col-span-full grid min-w-0 gap-1">
                        <Label htmlFor={numberId} className="text-[11px]">
                            Nomor Surat / BA
                        </Label>
                        <Input
                            id={numberId}
                            className="h-8 w-full min-w-0 bg-background px-2.5 text-xs"
                            value={current.ba_number}
                            onChange={(event) =>
                                form.setData('berita_acaras', [
                                    {
                                        ...current,
                                        ba_number: event.target.value,
                                    },
                                ])
                            }
                            placeholder="Contoh: 0012/BAPP/612/UPKD/2026"
                            autoComplete="off"
                        />
                    </div>
                    <div className="grid min-w-0 gap-1">
                        <Label htmlFor={dateId} className="text-[11px]">
                            Tanggal BA
                        </Label>
                        <Input
                            id={dateId}
                            type="date"
                            className="tabular h-8 w-full min-w-0 bg-background px-2.5 text-xs"
                            value={current.ba_date}
                            onChange={(event) =>
                                form.setData('berita_acaras', [
                                    {
                                        ...current,
                                        ba_date: event.target.value,
                                    },
                                ])
                            }
                        />
                    </div>
                    {canEdit && (
                        <Button
                            type="submit"
                            size="sm"
                            variant={dirty ? 'default' : 'outline'}
                            disabled={form.processing || !dirty}
                            className="h-8 shrink-0 justify-self-start px-3 @[15rem]:justify-self-end"
                        >
                            <Save className="size-3.5" />
                            Simpan
                        </Button>
                    )}
                </fieldset>
            </div>

            {(numberError || dateError) && (
                <div className="space-y-0.5">
                    <InputError message={numberError} />
                    <InputError message={dateError} />
                </div>
            )}
        </form>
    );
}
