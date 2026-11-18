import { router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    Download,
    ExternalLink,
    FileCheck2,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
    const input = useRef<HTMLInputElement>(null);
    const form = useForm<{ files: File[]; document_type_id?: number }>({
        files: [],
        ...(documentTypeId === undefined
            ? {}
            : { document_type_id: documentTypeId }),
    });

    const routeArgs = { procurement: procurementId, document: documentId ?? 0 };

    const previewUrl = (file: SignedUpload) =>
        procurements.documents.signed.show(
            { ...routeArgs, upload: file.id },
            { query: { preview: '1' } },
        ).url;

    const downloadUrl = (file: SignedUpload) =>
        procurements.documents.signed.show(
            { ...routeArgs, upload: file.id },
            { query: { download: '1' } },
        ).url;

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
            <UploadedFileRows
                files={uploads}
                previewUrl={previewUrl}
                downloadUrl={downloadUrl}
            />

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

/** A URL with `download=1` added: the file downloads instead of opening. */
export function withDownload(url: string): string {
    return `${url}${url.includes('?') ? '&' : '?'}download=1`;
}

/**
 * The uploaded files of a step or document. A file opens in a new tab, where
 * the browser renders the PDF itself, and downloads from its row under a
 * name marking it as the upload it is (not a generated document). Shared by
 * every tab so the files read the same everywhere.
 */
export function UploadedFileRows<File extends UploadedFile>({
    files,
    previewUrl,
    downloadUrl,
}: {
    files: File[];
    /** Where the file opens in the browser. */
    previewUrl: (file: File) => string;
    /** Where it downloads. */
    downloadUrl: (file: File) => string;
}) {
    if (files.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-1">
            {files.map((file) => (
                <li
                    key={file.id}
                    className="flex flex-wrap items-center justify-between gap-2 rounded px-2 py-1.5 transition-colors hover:bg-muted/40"
                >
                    <div className="flex min-w-0 flex-1 items-start gap-1.5">
                        <FileCheck2 className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <div className="min-w-0">
                            <a
                                href={previewUrl(file)}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block truncate text-xs font-medium text-foreground underline-offset-2 hover:text-primary hover:underline"
                                title="Buka berkas unggahan di tab baru"
                            >
                                {file.file_name}
                            </a>
                            <p className="tabular text-[11px] text-muted-foreground">
                                <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                    Berkas unggahan
                                </span>
                                {' · '}
                                {formatSize(file.size)}
                                {file.size !== null ? ' · ' : ''}
                                {formatDateTime(file.uploaded_at)}
                                {file.uploaded_by
                                    ? ` · ${file.uploaded_by}`
                                    : ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-1">
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="h-7 px-2.5 text-xs"
                        >
                            <a
                                href={previewUrl(file)}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink className="size-3.5" />
                                Lihat
                            </a>
                        </Button>
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="h-7 px-2.5 text-xs"
                        >
                            <a
                                href={downloadUrl(file)}
                                title="Unduh berkas unggahan asli"
                            >
                                <Download className="size-3.5" />
                                Unduh
                            </a>
                        </Button>
                    </div>
                </li>
            ))}
        </ul>
    );
}
