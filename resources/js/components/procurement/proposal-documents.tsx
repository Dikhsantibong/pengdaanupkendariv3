import { FileCheck2, FileWarning } from 'lucide-react';
import { ChecklistDocumentActions } from '@/components/procurement/checklist-panel';
import type { ProposalDocument } from '@/types';

/**
 * The two documents a division files with its proposal: the TOR (drafted
 * from the template, then uploaded signed) and the Nota Dinas Usulan.
 */
export function ProposalDocuments({
    procurementId,
    proposer,
    documents,
    canManage,
}: {
    procurementId: number;
    proposer: string;
    documents: ProposalDocument[];
    canManage: boolean;
}) {
    const filed = documents.filter((document) => document.is_signed).length;

    return (
        <section className="rounded-md border border-border bg-card">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                <div className="space-y-1">
                    <p className="text-xs text-muted-foreground">
                        Dokumen usulan yang diunggah{' '}
                        <span className="font-medium text-foreground">
                            {proposer}
                        </span>
                        . Keduanya wajib terunggah sebelum perencanaan diajukan,
                        dan menggantikan tahapan TOR serta Nota Dinas Usulan di
                        checklist.
                    </p>
                </div>
                <span
                    className={
                        filed === documents.length
                            ? 'flex items-center gap-1.5 rounded-sm bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-400'
                            : 'flex items-center gap-1.5 rounded-sm bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-700 dark:text-amber-400'
                    }
                >
                    {filed === documents.length ? (
                        <FileCheck2 className="size-3.5" />
                    ) : (
                        <FileWarning className="size-3.5" />
                    )}
                    {filed}/{documents.length} terunggah
                </span>
            </header>

            <ul className="divide-y divide-border">
                {documents.map((document) => (
                    <li key={document.type_id} className="space-y-2 p-4">
                        <p className="text-sm font-medium">
                            {document.type_name}
                        </p>
                        <ChecklistDocumentActions
                            procurementId={procurementId}
                            document={document}
                            canManage={canManage}
                        />
                    </li>
                ))}
            </ul>
        </section>
    );
}
