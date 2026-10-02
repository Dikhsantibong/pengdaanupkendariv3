import { Head, router, useForm } from '@inertiajs/react';
import { Archive, Eye, FileCode2, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { Option } from '@/types';

type TemplateRow = {
    id: number;
    document_type_id: number;
    document_type: string;
    procurement_method_id: number | null;
    procurement_method: string | null;
    name: string;
    version: number;
    body: string;
    placeholders: string[];
    is_active: boolean;
    usage_count: number;
    updated_at: string | null;
};

type TemplateFormValues = {
    document_type_id: number | null;
    procurement_method_id: number | null;
    name: string;
    body: string;
    is_active: boolean;
};

/** Sentinel for "berlaku untuk semua metode pengadaan". */
const ALL_METHODS = 'all';

const SAMPLE_VALUES: Record<string, string> = {
    nomor_pengadaan: '001/PENG/612/UPKD/2026',
    nama_pengadaan: 'JASA PEMBUATAN WEB DIGITALISASI PLN NP UP KENDARI',
    direksi_pekerjaan: 'Manager UPDK Kendari',
    unit_tujuan: 'PLN NP UP Kendari',
    metode_pengadaan: 'Pengadaan Langsung',
    sumber_anggaran: 'AO',
    sumber_anggaran_keterangan: 'Anggaran Operasi',
    jenis_kontrak: 'Lumsum',
    nomor_nota_dinas_manager: 'ND-012/UPKD/2026',
    nomor_pr_ro: 'PR-2026-0042',
    nomor_prk: 'KD262O0306',
    nilai_hpe: 'Rp 85.000.000,00',
    nilai_hpe_angka: '85.000.000',
    nilai_hpe_terbilang: 'Delapan puluh lima juta rupiah',
    status_progres: 'Penyusunan TOR',
    pic_perencana: 'Dikhsan Tibong',
    pic_pelaksana: 'Andi Pratama',
    target_penyelesaian: '30 April 2026',
    tanggal_dokumen: '04 Maret 2026',
    tahun: '2026',
    checklist_perencanaan: '<ul><li>[✓] TOR</li><li>[✓] RAB</li><li>[✓] HPE</li></ul>',
    checklist_pelaksanaan: '<ul><li>[-] Penawaran</li></ul>',
};

function renderPreviewHtml(body: string): string {
    const rendered = body.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (match, key) => {
        return SAMPLE_VALUES[key.toLowerCase()] ?? match;
    });

    if (/<html[\s>]/i.test(rendered)) {
        return rendered;
    }

    return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    @page { size: A4; margin: 15mm; }
    body { font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.45; color: #111; margin: 0; padding: 15mm; }
    table { width: 100%; border-collapse: collapse; margin: 8pt 0; font-size: 10.5pt; }
    td, th { border: 1px solid #444; padding: 4pt 6pt; vertical-align: top; }
</style>
</head>
<body>${rendered}</body>
</html>`;
}

export default function DocumentTemplates({
    templates,
    documentTypes,
    procurementMethods,
    placeholderCatalog,
}: {
    templates: TemplateRow[];
    documentTypes: Option[];
    procurementMethods: Option[];
    placeholderCatalog: { key: string; label: string }[];
}) {
    const [editing, setEditing] = useState<TemplateRow | null>(null);
    const [open, setOpen] = useState(false);
    const [previewHtml, setPreviewHtml] = useState<string | null>(null);
    const [previewTitle, setPreviewTitle] = useState<string>('');

    const openPreview = (name: string, body: string) => {
        setPreviewTitle(name);
        setPreviewHtml(renderPreviewHtml(body));
    };

    const defaults: TemplateFormValues = {
        document_type_id: documentTypes[0]?.value ?? null,
        procurement_method_id: null,
        name: '',
        body: '<h1>Judul Dokumen</h1>\n<p>{{nama_pengadaan}}</p>',
        is_active: true,
    };

    const form = useForm<TemplateFormValues>({ ...defaults });

    const openCreate = () => {
        setEditing(null);
        form.setDefaults({ ...defaults });
        form.reset();
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (template: TemplateRow) => {
        const values: TemplateFormValues = {
            document_type_id: template.document_type_id,
            procurement_method_id: template.procurement_method_id,
            name: template.name,
            body: template.body,
            is_active: template.is_active,
        };

        setEditing(template);
        form.setDefaults(values);
        form.setData(values);
        form.clearErrors();
        setOpen(true);
    };

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing === null) {
            form.post(masterData.documentTemplates.store().url, options);

            return;
        }

        form.put(masterData.documentTemplates.update(editing.id).url, options);
    };

    return (
        <>
            <Head title="Template Dokumen" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Data Master"
                    title="Template Dokumen"
                    description="Template standar sementara. Saat template resmi UP Kendari tersedia, cukup unggah versi baru di sini tanpa mengubah logika aplikasi."
                    actions={
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            Tambah Template
                        </Button>
                    }
                />

                {templates.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={FileCode2}
                            title="Belum ada template"
                            description="Tambahkan template pertama agar dokumen dapat digenerate dari data pengadaan."
                            action={
                                <Button size="sm" onClick={openCreate}>
                                    Tambah Template
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Jenis Dokumen</TableHead>
                                    <TableHead>Berlaku Untuk</TableHead>
                                    <TableHead>Template</TableHead>
                                    <TableHead>Versi</TableHead>
                                    <TableHead>Placeholder</TableHead>
                                    <TableHead>Dipakai</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {templates.map((template) => (
                                    <TableRow key={template.id}>
                                        <TableCell>
                                            {template.document_type}
                                        </TableCell>
                                        <TableCell>
                                            {template.procurement_method ?? (
                                                <span className="text-muted-foreground">
                                                    Semua metode
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {template.name}
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                Diperbarui{' '}
                                                {formatDateTime(
                                                    template.updated_at,
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="tabular">
                                            v{template.version}
                                        </TableCell>
                                        <TableCell className="tabular">
                                            {template.placeholders.length}
                                        </TableCell>
                                        <TableCell className="tabular text-muted-foreground">
                                            {template.usage_count}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={
                                                    template.is_active
                                                        ? 'inline-flex items-center rounded-sm bg-status-selesai-surface px-2 py-0.5 text-xs font-medium text-status-selesai'
                                                        : 'inline-flex items-center rounded-sm bg-status-pending-surface px-2 py-0.5 text-xs font-medium text-status-pending'
                                                }
                                            >
                                                {template.is_active
                                                    ? 'Aktif'
                                                    : 'Arsip'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    openPreview(
                                                        `${template.name} v${template.version}`,
                                                        template.body,
                                                    )
                                                }
                                            >
                                                <Eye className="size-3.5" />
                                                Pratinjau
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    openEdit(template)
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Ubah
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            `Arsipkan template ${template.name} v${template.version}? Dokumen yang sudah digenerate tidak berubah.`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            masterData.documentTemplates.destroy(
                                                                template.id,
                                                            ).url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Archive className="size-3.5" />
                                                Arsipkan
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <section className="rounded-md border border-border bg-card p-4">
                    <p className="section-label">Placeholder Tersedia</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Tulis placeholder dalam kurung kurawal ganda, misalnya{' '}
                        <code className="rounded-sm bg-muted px-1 py-0.5">
                            {'{{nama_pengadaan}}'}
                        </code>
                        . Nilainya diisi otomatis dari data pengadaan saat
                        dokumen digenerate.
                    </p>

                    <ul className="mt-3 grid gap-x-6 gap-y-1.5 sm:grid-cols-2 lg:grid-cols-3">
                        {placeholderCatalog.map((placeholder) => (
                            <li
                                key={placeholder.key}
                                className="flex items-baseline gap-2 text-xs"
                            >
                                <code className="shrink-0 rounded-sm bg-muted px-1.5 py-0.5 font-medium text-foreground">
                                    {`{{${placeholder.key}}}`}
                                </code>
                                <span className="text-muted-foreground">
                                    {placeholder.label}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editing === null
                                ? 'Tambah Template Dokumen'
                                : `Ubah ${editing.name}`}
                        </DialogTitle>
                        <DialogDescription>
                            Mengaktifkan template ini akan menonaktifkan
                            template lain pada jenis dokumen yang sama.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        id="template-form"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                        className="space-y-4"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="document_type_id">
                                    Jenis Dokumen
                                </Label>
                                <Select
                                    value={
                                        form.data.document_type_id === null
                                            ? undefined
                                            : String(form.data.document_type_id)
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'document_type_id',
                                            Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="document_type_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih jenis dokumen" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {documentTypes.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={String(type.value)}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.document_type_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="procurement_method_id">
                                    Berlaku Untuk Metode
                                </Label>
                                <Select
                                    value={
                                        form.data.procurement_method_id === null
                                            ? ALL_METHODS
                                            : String(
                                                  form.data
                                                      .procurement_method_id,
                                              )
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'procurement_method_id',
                                            value === ALL_METHODS
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="procurement_method_id"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL_METHODS}>
                                            Semua metode
                                        </SelectItem>
                                        {procurementMethods.map((method) => (
                                            <SelectItem
                                                key={method.value}
                                                value={String(method.value)}
                                            >
                                                {method.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Template khusus metode dipakai lebih dulu;
                                    "Semua metode" jadi cadangan.
                                </p>
                                <InputError
                                    message={form.errors.procurement_method_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="template-name">
                                    Nama Template
                                </Label>
                                <Input
                                    id="template-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder="Contoh: Template Resmi RKS 2026"
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="template-body">
                                    Isi Template (HTML)
                                </Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 px-2 text-xs"
                                    onClick={() =>
                                        openPreview(
                                            form.data.name || 'Pratinjau Template',
                                            form.data.body,
                                        )
                                    }
                                >
                                    <Eye className="size-3.5 mr-1" />
                                    Pratinjau Tampilan
                                </Button>
                            </div>
                            <Textarea
                                id="template-body"
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                rows={14}
                                spellCheck={false}
                                className="font-mono text-xs"
                            />
                            <InputError message={form.errors.body} />
                        </div>

                        <div className="flex items-center justify-between gap-4 rounded-md border border-border px-3 py-2.5">
                            <div className="space-y-0.5">
                                <Label htmlFor="template-active">
                                    Jadikan Template Aktif
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Template aktif dipakai saat dokumen
                                    digenerate.
                                </p>
                            </div>
                            <Switch
                                id="template-active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                        </div>
                    </form>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="template-form"
                            disabled={form.processing}
                        >
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={previewHtml !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        setPreviewHtml(null);
                    }
                }}
            >
                <DialogContent className="flex h-[92vh] max-w-5xl flex-col p-4 sm:p-6">
                    <DialogHeader className="shrink-0">
                        <DialogTitle>Pratinjau: {previewTitle}</DialogTitle>
                        <DialogDescription>
                            Tampilan dokumen dengan simulasi data pengadaan.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-2 flex-1 min-h-0 w-full overflow-hidden rounded-md border border-border bg-white shadow-inner">
                        {previewHtml !== null && (
                            <iframe
                                title="Pratinjau Template Dokumen"
                                srcDoc={previewHtml}
                                className="h-full w-full border-0"
                            />
                        )}
                    </div>

                    <DialogFooter className="shrink-0">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPreviewHtml(null)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

DocumentTemplates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Template Dokumen',
            href: masterData.documentTemplates.index(),
        },
    ],
};
