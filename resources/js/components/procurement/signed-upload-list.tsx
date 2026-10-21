import { router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    Download,
    ExternalLink,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
 * Remove the signed scans of one document. Sits in the document's button
 * group (beside Unduh and Edit): one file is removed at once, several open a
 * menu to pick one file or all of them.
 */
export function SignedUploadDeleteButton({
    procurementId,
    documentId,
    uploads,
}: {
    procurementId: number;
    documentId: number | null;
    uploads: SignedUpload[];
}) {
    if (documentId === null || uploads.length === 0) {
        return null;
    }

    const routeArgs = { procurement: procurementId, document: documentId };

    const remove = (file: SignedUpload) => {
        if (window.confirm(`Hapus berkas ${file.file_name}?`)) {
            router.delete(
                procurements.documents.signed.destroy({
                    ...routeArgs,
                    upload: file.id,
                }).url,
                { preserveScroll: true },
            );
        }
    };

    const buttonClass =
        'text-destructive hover:bg-destructive/10 hover:text-destructive';

    if (uploads.length === 1) {
        return (
            <Button
                type="button"
                size="sm"
                variant="outline"
                className={buttonClass}
                onClick={() => remove(uploads[0])}
            >
                <Trash2 className="size-3.5" />
                Hapus
            </Button>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className={buttonClass}
                >
                    <Trash2 className="size-3.5" />
                    Hapus
                    <ChevronDown className="size-3.5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="max-w-80">
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Pilih berkas yang dihapus
                </DropdownMenuLabel>
                {uploads.map((file) => (
                    <DropdownMenuItem
                        key={file.id}
                        onSelect={() => remove(file)}
                    >
                        <span className="truncate">{file.file_name}</span>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    onSelect={() => {
                        if (
                            window.confirm(
                                `Hapus semua ${uploads.length} berkas?`,
                            )
                        ) {
                            router.delete(
                                procurements.documents.signed.destroyAll(
                                    routeArgs,
                                ).url,
                                { preserveScroll: true },
                            );
                        }
                    }}
                >
                    Hapus semua berkas
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * The signed scans of one generated document: upload several, list, remove.
 *
 * A signed berita acara rarely arrives as one file, so the control accepts a
 * whole batch at once and every file stays listed on its own. A file opens
 * its preview from its name; removing sits with the upload button unless the
 * document has its own button group (`deleteInline`).
 */
export function SignedUploadList({
    procurementId,
    documentId,
    documentTypeId,
    uploads,
    canManage,
    label = 'Unggah Dokumen Tervalidasi',
    deleteInline = true,
}: {
    procurementId: number;
    /** Null for an upload-only step that has no files yet. */
    documentId: number | null;
    /** Needed for that first upload, which creates the archive entry. */
    documentTypeId?: number;
    uploads: SignedUpload[];
    canManage: boolean;
    label?: string;
    /** False when the parent puts the delete button in its own button group. */
    deleteInline?: boolean;
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
            <UploadedFileRows files={uploads} onPreview={setPreviewFile} />

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
                        {deleteInline && (
                            <SignedUploadDeleteButton
                                procurementId={procurementId}
                                documentId={documentId}
                                uploads={uploads}
                            />
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

            <UploadPreviewDialog
                file={previewFile}
                previewUrl={previewUrl}
                downloadUrl={downloadUrl}
                onClose={() => setPreviewFile(null)}
            />
        </div>
    );
}

/** What a listed upload shows: its name, size and who filed it when. */
type UploadedFile = {
    id: number;
    file_name: string;
    size: number | null;
    uploaded_by: string | null;
    uploaded_at: string | null;
};

/**
 * The uploaded files of a step or document, each opening its preview from
 * its name. Shared by every tab so the files read the same everywhere.
 */
export function UploadedFileRows<File extends UploadedFile>({
    files,
    onPreview,
}: {
    files: File[];
    onPreview: (file: File) => void;
}) {
    if (files.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-1">
            {files.map((file) => (
                <li
                    key={file.id}
                    className="flex items-center justify-between gap-2 rounded px-2 py-1.5 transition-colors hover:bg-muted/40"
                >
                    <div className="flex min-w-0 flex-1 items-start gap-1.5">
                        <FileCheck2 className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <div className="min-w-0">
                            <button
                                type="button"
                                onClick={() => onPreview(file)}
                                className="block cursor-pointer truncate text-left text-xs font-medium text-foreground underline-offset-2 hover:text-primary hover:underline"
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
                </li>
            ))}
        </ul>
    );
}

/**
 * The preview of one uploaded file, with a new-tab link and a download.
 */
export function UploadPreviewDialog({
    file,
    previewUrl,
    downloadUrl,
    onClose,
}: {
    file: UploadedFile | null;
    previewUrl: string;
    downloadUrl: string;
    onClose: () => void;
}) {
    const isImage = Boolean(file?.file_name.match(/\.(jpg|jpeg|png)$/i));

    return (
        <Dialog
            open={file !== null}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    onClose();
                }
            }}
        >
            <DialogContent className="flex h-[92vh] max-w-5xl flex-col p-4 sm:p-6">
                <DialogHeader className="shrink-0">
                    <DialogTitle className="truncate text-base font-semibold">
                        Pratinjau: {file?.file_name}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        {file ? formatSize(file.size) : ''}
                        {file?.size !== null ? ' · ' : ''}
                        Diunggah {file ? formatDateTime(file.uploaded_at) : ''}
                        {file?.uploaded_by ? ` · ${file.uploaded_by}` : ''}
                    </DialogDescription>
                </DialogHeader>

                <div className="my-2 min-h-0 w-full flex-1 overflow-hidden rounded-md border border-border bg-muted/20">
                    {file !== null &&
                        (isImage ? (
                            <div className="flex h-full w-full items-center justify-center overflow-auto bg-muted/10 p-4">
                                <img
                                    src={previewUrl}
                                    alt={file.file_name}
                                    className="max-h-full max-w-full rounded object-contain shadow-sm"
                                />
                            </div>
                        ) : (
                            <iframe
                                src={`${previewUrl}#view=FitH`}
                                title={file.file_name}
                                className="h-full w-full border-0 bg-white"
                            />
                        ))}
                </div>

                <DialogFooter className="flex shrink-0 flex-row items-center justify-between gap-2 sm:justify-between">
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
                            <a href={downloadUrl} download={file?.file_name}>
                                <Download className="size-3.5" />
                                Unduh
                            </a>
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={onClose}
                    >
                        Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
