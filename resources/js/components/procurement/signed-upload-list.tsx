import { router, useForm } from '@inertiajs/react';
import { FileCheck2, Trash2, Upload } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
    uploads,
    canManage,
    label = 'Unggah Hasil TTD',
}: {
    procurementId: number;
    documentId: number;
    uploads: SignedUpload[];
    canManage: boolean;
    label?: string;
}) {
    const input = useRef<HTMLInputElement>(null);
    const form = useForm<{ files: File[] }>({ files: [] });

    const routeArgs = { procurement: procurementId, document: documentId };

    const upload = (files: FileList) => {
        form.setData('files', Array.from(files));
        form.submit(
            'post',
            procurements.documents.signed.store(routeArgs).url,
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
                            className="flex items-start justify-between gap-2"
                        >
                            <div className="flex min-w-0 items-start gap-1.5">
                                <FileCheck2 className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                <div className="min-w-0">
                                    <a
                                        href={
                                            procurements.documents.signed.show({
                                                ...routeArgs,
                                                upload: file.id,
                                            }).url
                                        }
                                        className="block truncate text-xs font-medium underline-offset-2 hover:underline"
                                    >
                                        {file.file_name}
                                    </a>
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

                            {canManage && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="h-6 shrink-0 px-1.5 text-destructive hover:text-destructive"
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
                                </Button>
                            )}
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
        </div>
    );
}
