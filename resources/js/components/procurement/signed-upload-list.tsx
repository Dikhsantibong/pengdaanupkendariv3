import { router, useForm } from '@inertiajs/react';
import {
    Download,
    ExternalLink,
    Eye,
    FileCheck2,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { SignedUpload } from '@/types';

/**
 * Render a byte count the way a person reads it.
 */
function formatSize(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * The signed scans of one generated document: upload several, list, remove.
 *
 * A signed berita acara rarely arrives as one file, so the control accepts a
 * whole batch at once and every file stays listed on its own.
 */
export function SignedUploadList({
    procurementId,
    documentId,
    documentTypeId,
    uploads,
    canManage,
    label = 'Unggah Hasil TTD',
}: {
    procurementId: number;
    /** Null for an upload-only step that has no files yet. */
    documentId: number | null;
    /** Needed for that first upload, which creates the archive entry. */
    documentTypeId?: number;
    uploads: SignedUpload[];
    canManage: boolean;
    label?: string;
}) {
    const [previewFile, setPreviewFile] = useState<SignedUpload | null>(null);
    const input = useRef<HTMLInputElement>(null);
    const form = useForm<{ files: File[]; document_type_id?: number }>({
        files: [],
        ...(documentTypeId === undefined
            ? {}
            : { document_type_id: documentTypeId }),
    });

    const routeArgs = { procurement: procurementId, document: documentId ?? 0 };

    const previewUrl = previewFile
        ? procurements.documents.signed.show(
              { ...routeArgs, upload: previewFile.id },
              { query: { preview: '1' } },
          ).url
        : '';

    const downloadUrl = previewFile
        ? procurements.documents.signed.show(
              { ...routeArgs, upload: previewFile.id },
              { query: { download: '1' } },
          ).url
        : '';

    const isImage = Boolean(
        previewFile?.file_name.match(/\.(jpg|jpeg|png)$/i),
    );

    const upload = (files: FileList) => {
        form.setData('files', Array.from(files));
        form.submit(
            'post',
            documentId === null
                ? procurements.documents.upload(procurementId).url
                : procurements.documents.signed.store(routeArgs).url,
            {
                preserveScroll: true,
                forceFormData: true,
                onFinish: () => {
                    form.reset();

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    // Laravel reports per-file problems as files.0, files.1 and so on.
    const errors = Object.entries(form.errors)
        .filter(([key]) => key.startsWith('files'))
        .map(([, message]) => message)
        .filter(Boolean);

    return (
        <div className="space-y-1.5">
            {uploads.length > 0 && (
                <ul className="space-y-1">
                    {uploads.map((file) => (
                        <li
                            key={file.id}
                            className="flex items-center justify-between gap-2 rounded px-2 py-1.5 hover:bg-muted/40 transition-colors"
                        >
                            <div className="flex min-w-0 items-start gap-1.5 flex-1">
                                <FileCheck2 className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                <div className="min-w-0">
                                    <button
                                        type="button"
                                        onClick={() => setPreviewFile(file)}
                                        className="block truncate text-left text-xs font-medium text-foreground underline-offset-2 hover:underline hover:text-primary cursor-pointer"
                                        title="Klik untuk melihat pratinjau dokumen"
                                    >
                                        {file.file_name}
                                    </button>
                                    <p className="tabular text-[11px] text-muted-foreground">
                                        {formatSize(file.size)}
                                        {file.size !== null ? ' · ' : ''}
                                        {formatDateTime(file.uploaded_at)}
                                        {file.uploaded_by
                                            ? ` · ${file.uploaded_by}`
                                            : ''}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-1 shrink-0">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="h-6.5 px-2 text-[11px] gap-1 font-medium text-foreground hover:bg-muted"
                                    onClick={() => setPreviewFile(file)}
                                    title="Pratinjau Berkas"
                                >
                                    <Eye className="size-3.5 text-primary" />
                                    <span>Preview</span>
                                </Button>

                                {canManage && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-6.5 shrink-0 px-1.5 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        title="Hapus Berkas"
                                        onClick={() =>
                                            router.delete(
                                                procurements.documents.signed.destroy(
                                                    {
                                                        ...routeArgs,
                                                        upload: file.id,
                                                    },
                                                ).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 className="size-3.5" />
                                        <span className="sr-only">Hapus</span>
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {canManage && (
                <>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Button
                            size="sm"
                            variant={uploads.length > 0 ? 'ghost' : 'outline'}
                            disabled={form.processing}
                            onClick={() => input.current?.click()}
                        >
                            <Upload className="size-3.5" />
                            {form.processing
                                ? 'Mengunggah…'
                                : uploads.length > 0
                                  ? 'Tambah Berkas'
                                  : label}
                        </Button>
                        {uploads.length > 1 && (
                            <Button
                                size="sm"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() =>
                                    router.delete(
                                        procurements.documents.signed.destroyAll(
                                            routeArgs,
                                        ).url,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Hapus Semua
                            </Button>
                        )}
                    </div>

                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png"
                        className="hidden"
                        onChange={(event) => {
                            const files = event.target.files;

                            if (files && files.length > 0) {
                                upload(files);
                            }
                        }}
                    />

                    {uploads.length === 0 && (
                        <p className="text-[11px] text-muted-foreground">
                            Bisa pilih beberapa berkas sekaligus. PDF/JPG/PNG,
                            maks 20 MB per berkas.
                        </p>
                    )}
                </>
            )}

            {errors.map((message) => (
                <InputError key={message} message={message} />
            ))}

            <Dialog
                open={previewFile !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        setPreviewFile(null);
                    }
                }}
            >
                <DialogContent className="flex h-[92vh] max-w-5xl flex-col p-4 sm:p-6">
                    <DialogHeader className="shrink-0">
                        <DialogTitle className="truncate text-base font-semibold">
                            Pratinjau: {previewFile?.file_name}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            {previewFile ? formatSize(previewFile.size) : ''}
                            {previewFile?.size !== null ? ' · ' : ''}
                            Diunggah{' '}
                            {previewFile
                                ? formatDateTime(previewFile.uploaded_at)
                                : ''}
                            {previewFile?.uploaded_by
                                ? ` · ${previewFile.uploaded_by}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-2 flex-1 min-h-0 w-full overflow-hidden rounded-md border border-border bg-muted/20">
                        {previewFile !== null &&
                            (isImage ? (
                                <div className="flex h-full w-full items-center justify-center overflow-auto p-4 bg-muted/10">
                                    <img
                                        src={previewUrl}
                                        alt={previewFile.file_name}
                                        className="max-h-full max-w-full rounded object-contain shadow-sm"
                                    />
                                </div>
                            ) : (
                                <iframe
                                    src={`${previewUrl}#view=FitH`}
                                    title={previewFile.file_name}
                                    className="h-full w-full border-0 bg-white"
                                />
                            ))}
                    </div>

                    <DialogFooter className="shrink-0 flex flex-row items-center justify-between gap-2 sm:justify-between">
                        <div className="flex items-center gap-2">
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={previewUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink className="size-3.5" />
                                    Tab Baru
                                </a>
                            </Button>
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={downloadUrl}
                                    download={previewFile?.file_name}
                                >
                                    <Download className="size-3.5" />
                                    Unduh
                                </a>
                            </Button>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => setPreviewFile(null)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
