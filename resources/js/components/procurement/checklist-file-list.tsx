import { router, useForm } from '@inertiajs/react';
import { FileCheck2, Trash2, Upload } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { ChecklistFile } from '@/types';

/**
 * The papers that evidence a Penyerahan or Pembayaran requirement: the step
 * is finished by uploading them, not by a bare tick.
 */
export function ChecklistFileList({
    procurementId,
    checklistId,
    files,
    canManage,
}: {
    procurementId: number;
    checklistId: number;
    files: ChecklistFile[];
    canManage: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const form = useForm<{ files: File[] }>({ files: [] });

    const upload = (picked: FileList) => {
        form.setData('files', Array.from(picked));
        form.submit(
            'post',
            procurements.checklists.files.store({
                procurement: procurementId,
                checklist: checklistId,
            }).url,
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

    const remove = (file: ChecklistFile) => {
        if (window.confirm(`Hapus berkas ${file.file_name}?`)) {
            router.delete(
                procurements.checklists.files.destroy({
                    procurement: procurementId,
                    checklist: checklistId,
                    file: file.id,
                }).url,
                { preserveScroll: true },
            );
        }
    };

    // Laravel reports per-file problems as files.0, files.1 and so on.
    const errors = Object.entries(form.errors)
        .filter(([key]) => key.startsWith('files'))
        .map(([, message]) => message)
        .filter(Boolean);

    return (
        <div className="mt-2 space-y-1.5 rounded-sm border border-border p-2">
            <p className="text-xs font-medium text-muted-foreground">
                {files.length > 0
                    ? `Berkas terunggah · ${files.length}`
                    : 'Wajib unggah berkas sebelum dicentang'}
            </p>

            {files.length > 0 && (
                <ul className="space-y-1">
                    {files.map((file) => (
                        <li
                            key={file.id}
                            className="flex items-center justify-between gap-2 rounded px-1.5 py-1 hover:bg-muted/40"
                        >
                            <div className="flex min-w-0 items-start gap-1.5">
                                <FileCheck2 className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                <div className="min-w-0">
                                    <a
                                        href={file.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="block truncate text-xs font-medium text-foreground hover:text-primary hover:underline"
                                    >
                                        {file.file_name}
                                    </a>
                                    <p className="tabular text-[11px] text-muted-foreground">
                                        {formatDateTime(file.uploaded_at)}
                                        {file.uploaded_by
                                            ? ` · ${file.uploaded_by}`
                                            : ''}
                                    </p>
                                </div>
                            </div>
                            {canManage && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="h-6.5 shrink-0 px-1.5 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    title="Hapus berkas"
                                    onClick={() => remove(file)}
                                >
                                    <Trash2 className="size-3.5" />
                                    <span className="sr-only">Hapus</span>
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {canManage && (
                <>
                    <Button
                        size="sm"
                        variant={files.length > 0 ? 'ghost' : 'outline'}
                        disabled={form.processing}
                        onClick={() => input.current?.click()}
                    >
                        <Upload className="size-3.5" />
                        {form.processing
                            ? 'Mengunggah…'
                            : files.length > 0
                              ? 'Tambah Berkas'
                              : 'Unggah Berkas'}
                    </Button>
                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png"
                        className="hidden"
                        onChange={(event) => {
                            const picked = event.target.files;

                            if (picked && picked.length > 0) {
                                upload(picked);
                            }
                        }}
                    />
                    {files.length === 0 && (
                        <p className="text-[11px] text-muted-foreground">
                            PDF/JPG/PNG, maks 20 MB per berkas, bisa beberapa
                            sekaligus.
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
