import { Link, router } from '@inertiajs/react';
import {
    Download,
    FileCheck2,
    FilePlus2,
    FileWarning,
    Lock,
    Pencil,
} from 'lucide-react';
import { SignedUploadList } from '@/components/procurement/signed-upload-list';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Progress } from '@/components/ui/progress';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { ChecklistDocument, ChecklistRow } from '@/types';

export function ChecklistPanel({
    procurementId,
    title,
    description,
    rows,
    editable,
    canManageDocuments,
    lockedReason,
}: {
    procurementId: number;
    title: string;
    description?: string;
    rows: ChecklistRow[];
    editable: boolean;
    /** Generating, editing and filing documents follows the document policy. */
    canManageDocuments: boolean;
    lockedReason?: string;
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
                    <h2 className="text-sm font-semibold text-foreground">
                        {title}
                    </h2>
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
                {rows.map((row) => (
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

                            {row.documents.length > 0 && (
                                <div className="mt-2 space-y-1.5">
                                    {row.documents.map((document) => (
                                        <ChecklistDocumentActions
                                            key={document.type_id}
                                            procurementId={procurementId}
                                            document={document}
                                            canManage={canManageDocuments}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </li>
                ))}
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
function ChecklistDocumentActions({
    procurementId,
    document,
    canManage,
}: {
    procurementId: number;
    document: ChecklistDocument;
    canManage: boolean;
}) {
    const generate = () =>
        router.post(
            procurements.documents.store(procurementId).url,
            { document_type_id: document.type_id },
            { preserveScroll: true },
        );

    return (
        <div className="space-y-1.5 rounded-sm border border-border bg-muted/30 p-2.5">
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
                        ? `Bertanda tangan · ${document.uploads.length} berkas`
                        : 'Wajib diunggah'}
                </span>
            </div>

            {document.id === null ? (
                <div className="flex flex-wrap items-center gap-2">
                    <p className="text-xs text-muted-foreground">
                        Belum digenerate.
                    </p>
                    {canManage && document.has_template && (
                        <Button size="sm" variant="outline" onClick={generate}>
                            <FilePlus2 className="size-3.5" />
                            Generate
                        </Button>
                    )}
                    {canManage && !document.has_template && (
                        <span className="text-xs text-muted-foreground">
                            Template belum tersedia — hubungi Administrator
                            untuk memasang template dokumen ini.
                        </span>
                    )}
                </div>
            ) : (
                <>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Button asChild size="sm" variant="ghost">
                            <a
                                href={
                                    procurements.documents.show({
                                        procurement: procurementId,
                                        document: document.id,
                                    }).url
                                }
                            >
                                <Download className="size-3.5" />
                                PDF
                            </a>
                        </Button>
                        {canManage && (
                            <Button asChild size="sm" variant="ghost">
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
                    </div>

                    <SignedUploadList
                        procurementId={procurementId}
                        documentId={document.id}
                        uploads={document.uploads}
                        canManage={canManage}
                    />
                </>
            )}
        </div>
    );
}
