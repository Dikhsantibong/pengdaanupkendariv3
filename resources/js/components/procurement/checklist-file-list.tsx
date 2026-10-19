import { router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    Download,
    FileCheck2,
    FileWarning,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    UploadedFileRows,
    UploadPreviewDialog,
} from '@/components/procurement/signed-upload-list';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import procurements from '@/routes/procurements';
import type { ChecklistFile } from '@/types';

/**
 * The papers that evidence a Penyerahan or Pembayaran requirement: the step
 * is finished by uploading them, not by a bare tick.
 *
 * Laid out like a document of the Perencanaan and Pelaksanaan steps: a
 * labelled box with its status, the files opening a preview from their
 * name, and the upload and delete buttons side by side.
 */
export function ChecklistFileList({
    procurementId,
    checklistId,
    name,
    files,
    templateUrl = null,
    canManage,
}: {
    procurementId: number;
    checklistId: number;
    /** The requirement the files evidence. */
    name: string;
    files: ChecklistFile[];
    /** The partner's letter to download as Word, filled in, before it is signed. */
    templateUrl?: string | null;
    canManage: boolean;
}) {
    const [previewFile, setPreviewFile] = useState<ChecklistFile | null>(null);
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

    const filed = files.length > 0;
    const deleteClass =
        'text-destructive hover:bg-destructive/10 hover:text-destructive';

    return (
        <div className="mt-2 space-y-1.5 rounded-sm border border-border bg-muted/30 p-2.5">
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="flex items-center gap-1.5 text-xs font-medium">
                    {filed ? (
                        <FileCheck2 className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                    ) : (
                        <FileWarning className="size-3.5 text-amber-600 dark:text-amber-400" />
                    )}
                    Dokumen {name}
                </span>
                <span
                    className={
                        filed
                            ? 'rounded-sm bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400'
                            : 'rounded-sm bg-amber-500/10 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400'
                    }
                >
                    {filed
                        ? `Terunggah · ${files.length} berkas`
                        : 'Wajib diunggah'}
                </span>
            </div>

            <UploadedFileRows files={files} onPreview={setPreviewFile} />

            {templateUrl && (
                <div className="flex flex-wrap items-center gap-1.5">
                    <Button asChild size="sm" variant="outline">
                        <a href={templateUrl}>
                            <Download className="size-3.5" />
                            Unduh Template (Word)
                        </a>
                    </Button>
                    <span className="text-[11px] text-muted-foreground">
                        Sudah terisi data kontrak. Pasang kop perusahaan,
                        lengkapi dan tanda tangani, lalu unggah kembali di sini.
                    </span>
                </div>
            )}

            {canManage && (
                <>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Button
                            size="sm"
                            variant={filed ? 'ghost' : 'outline'}
                            disabled={form.processing}
                            onClick={() => input.current?.click()}
                        >
                            <Upload className="size-3.5" />
                            {form.processing
                                ? 'Mengunggah…'
                                : filed
                                  ? 'Tambah Berkas'
                                  : 'Unggah Dokumen'}
                        </Button>

                        {files.length === 1 && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className={deleteClass}
                                onClick={() => remove(files[0])}
                            >
                                <Trash2 className="size-3.5" />
                                Hapus
                            </Button>
                        )}

                        {files.length > 1 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className={deleteClass}
                                    >
                                        <Trash2 className="size-3.5" />
                                        Hapus
                                        <ChevronDown className="size-3.5" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    className="max-w-80"
                                >
                                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                                        Pilih berkas yang dihapus
                                    </DropdownMenuLabel>
                                    {files.map((file) => (
                                        <DropdownMenuItem
                                            key={file.id}
                                            onSelect={() => remove(file)}
                                        >
                                            <span className="truncate">
                                                {file.file_name}
                                            </span>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>

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

                    {!filed && (
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
                previewUrl={previewFile?.url ?? ''}
                downloadUrl={
                    previewFile
                        ? `${previewFile.url}${previewFile.url.includes('?') ? '&' : '?'}download=1`
                        : ''
                }
                onClose={() => setPreviewFile(null)}
            />
        </div>
    );
}
