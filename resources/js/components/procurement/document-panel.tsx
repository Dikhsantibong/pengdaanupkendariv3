import { Link, useForm } from '@inertiajs/react';
import { Download, Eye, FileText, Pencil } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { SignedUploadList } from '@/components/procurement/signed-upload-list';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { DocumentTypeOption, ProcurementDocumentRow } from '@/types';

export function DocumentPanel({
    procurementId,
    documents,
    documentTypes,
    canGenerate,
}: {
    procurementId: number;
    documents: ProcurementDocumentRow[];
    documentTypes: DocumentTypeOption[];
    canGenerate: boolean;
}) {
    const form = useForm<{ document_type_id: number | null }>({
        document_type_id: documentTypes[0]?.value ?? null,
    });

    const selected = documentTypes.find(
        (type) => type.value === form.data.document_type_id,
    );

    return (
        <section className="rounded-md border border-border bg-card">
            <header className="flex flex-col gap-3 border-b border-border p-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="space-y-1">
                    <h2 className="text-sm font-semibold text-foreground">
                        Dokumen Pengadaan
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Dokumen digenerate dari template aktif dan tersimpan
                        otomatis pada arsip pengadaan ini.
                    </p>
                </div>

                {canGenerate && documentTypes.length > 0 && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(
                                procurements.documents.store(procurementId).url,
                                { preserveScroll: true },
                            );
                        }}
                        className="flex items-end gap-2"
                    >
                        <Select
                            value={
                                form.data.document_type_id === null
                                    ? undefined
                                    : String(form.data.document_type_id)
                            }
                            onValueChange={(value) =>
                                form.setData('document_type_id', Number(value))
                            }
                        >
                            <SelectTrigger className="w-60">
                                <SelectValue placeholder="Pilih jenis dokumen" />
                            </SelectTrigger>
                            <SelectContent>
                                {documentTypes.map((type) => (
                                    <SelectItem
                                        key={type.value}
                                        value={String(type.value)}
                                        disabled={!type.hasTemplate}
                                    >
                                        {type.label}
                                        {type.hasTemplate
                                            ? ''
                                            : ' (template belum ada)'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button
                            type="submit"
                            size="sm"
                            disabled={
                                form.processing ||
                                selected?.hasTemplate !== true
                            }
                        >
                            Generate
                        </Button>
                    </form>
                )}
            </header>

            {documents.length === 0 ? (
                <EmptyState
                    icon={FileText}
                    title="Belum ada dokumen digenerate"
                    description="Pilih jenis dokumen di atas untuk menghasilkan dokumen dari data pengadaan ini."
                />
            ) : (
                <Table>
                    <TableHeader className="bg-muted/60">
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Dokumen</TableHead>
                            <TableHead>Jenis</TableHead>
                            <TableHead>Versi Template</TableHead>
                            <TableHead>Digenerate</TableHead>
                            <TableHead>Hasil Tanda Tangan</TableHead>
                            <TableHead className="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {documents.map((document) => (
                            <TableRow key={document.id}>
                                <TableCell className="font-medium">
                                    {document.title}
                                </TableCell>
                                <TableCell>{document.type}</TableCell>
                                <TableCell className="tabular">
                                    v{document.template_version}
                                    {document.revision > 0 && (
                                        <span className="ml-1.5 rounded-sm bg-amber-500/10 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400">
                                            rev {document.revision}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="tabular text-xs text-muted-foreground">
                                    {formatDateTime(document.generated_at)}
                                    {document.generated_by
                                        ? ` · ${document.generated_by}`
                                        : ''}
                                    {document.edited_at && (
                                        <div>
                                            Diedit{' '}
                                            {formatDateTime(document.edited_at)}
                                            {document.edited_by
                                                ? ` · ${document.edited_by}`
                                                : ''}
                                        </div>
                                    )}
                                </TableCell>
                                <TableCell className="min-w-56">
                                    <SignedUploadList
                                        procurementId={procurementId}
                                        documentId={document.id}
                                        uploads={document.uploads}
                                        canManage={canGenerate}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    <div className="flex justify-end gap-2">
                                        {canGenerate && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <Link
                                                    href={
                                                        procurements.documents.edit(
                                                            {
                                                                procurement:
                                                                    procurementId,
                                                                document:
                                                                    document.id,
                                                            },
                                                        ).url
                                                    }
                                                >
                                                    <Pencil className="size-3.5" />
                                                    Edit
                                                </Link>
                                            </Button>
                                        )}
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <a
                                                href={`${
                                                    procurements.documents.show(
                                                        {
                                                            procurement:
                                                                procurementId,
                                                            document:
                                                                document.id,
                                                        },
                                                    ).url
                                                }?format=html`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Eye className="size-3.5" />
                                                Pratinjau
                                            </a>
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={
                                                    procurements.documents.show(
                                                        {
                                                            procurement:
                                                                procurementId,
                                                            document:
                                                                document.id,
                                                        },
                                                    ).url
                                                }
                                            >
                                                <Download className="size-3.5" />
                                                Unduh PDF
                                            </a>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}
