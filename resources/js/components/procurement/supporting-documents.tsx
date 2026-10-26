import { router } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    Paperclip,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import proposals from '@/routes/proposals';
import type { SupportingDocument } from '@/types';

/** The file types a supporting document may be, as the server accepts them. */
export const SUPPORTING_ACCEPT = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';

/** At most this many files go up in one go. */
export const SUPPORTING_MAX_FILES = 10;

export function formatFileSize(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    return bytes > 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * The optional supporting documents of a proposal (specifications, photos,
 * quotations, and the like) beside the TOR and the Nota Dinas Usulan: listed
 * for everyone who can see the proposal, added and removed by the proposer
 * until it is approved.
 */
export function SupportingDocuments({
    procurementId,
    documents,
    canManage,
}: {
    procurementId: number;
    documents: SupportingDocument[];
    canManage: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = (files: File[]) => {
        if (files.length === 0) {
            return;
        }

        setError(null);
        router.post(
            proposals.attachments.store(procurementId).url,
            { attachments: files.slice(0, SUPPORTING_MAX_FILES) },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'Dokumen pendukung gagal diunggah.',
                    ),
            },
        );
    };

    const remove = (document: SupportingDocument) => {
        if (!window.confirm(`Hapus dokumen pendukung ${document.file_name}?`)) {
            return;
        }

        router.delete(
            proposals.attachments.destroy({
                procurement: procurementId,
                attachment: document.id,
            }).url,
            { preserveScroll: true },
        );
    };

    if (!canManage && documents.length === 0) {
        return null;
    }

    return (
        <section className="space-y-3 rounded-lg border border-border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="flex items-center gap-1.5 text-sm font-semibold">
                        <Paperclip className="size-4 text-primary" />
                        Dokumen Pendukung
                        <span className="font-normal text-muted-foreground">
                            (opsional)
                        </span>
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Selain TOR dan Nota Dinas Usulan: spesifikasi, foto
                        kondisi, penawaran, dan sebagainya. PDF, JPG, PNG, Word,
                        atau Excel, maks. 20 MB per berkas.
                    </p>
                </div>
                {canManage && (
                    <>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={uploading}
                            onClick={() => input.current?.click()}
                        >
                            <Upload className="size-3.5" />
                            {uploading ? 'Mengunggah...' : 'Tambah Dokumen'}
                        </Button>
                        <input
                            ref={input}
                            type="file"
                            multiple
                            accept={SUPPORTING_ACCEPT}
                            className="sr-only"
                            aria-label="Pilih dokumen pendukung"
                            onChange={(event) => {
                                upload(Array.from(event.target.files ?? []));
                                event.target.value = '';
                            }}
                        />
                    </>
                )}
            </div>

            {documents.length === 0 ? (
                <p className="rounded-md border border-dashed border-border px-3 py-3 text-center text-xs text-muted-foreground">
                    Belum ada dokumen pendukung.
                </p>
            ) : (
                <ul className="divide-y divide-border rounded-md border border-border">
                    {documents.map((document) => (
                        <li
                            key={document.id}
                            className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                        >
                            <span className="flex min-w-0 items-center gap-2">
                                <FileText className="size-4 shrink-0 text-primary" />
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">
                                        {document.file_name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {[
                                            formatFileSize(document.size),
                                            document.uploaded_by,
                                            document.uploaded_at
                                                ? formatDateTime(
                                                      document.uploaded_at,
                                                  )
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                </span>
                            </span>
                            <span className="flex shrink-0 items-center gap-1">
                                <Button asChild size="sm" variant="ghost">
                                    <a
                                        href={document.url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink className="size-3.5" />
                                        Buka
                                    </a>
                                </Button>
                                {canManage && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => remove(document)}
                                        aria-label={`Hapus ${document.file_name}`}
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <InputError message={error ?? undefined} />
        </section>
    );
}
