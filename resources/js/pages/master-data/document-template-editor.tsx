import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Braces, Eye, Save, Search } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { TemplateEditor } from '@/components/template-editor';
import type { TemplateEditorHandle } from '@/components/template-editor';
import { TemplatePreviewDialog } from '@/components/template-preview-dialog';
import { Button } from '@/components/ui/button';
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
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { Option } from '@/types';

type EditableTemplate = {
    id: number;
    document_type_id: number;
    procurement_method_id: number | null;
    name: string;
    version: number;
    body: string;
    is_active: boolean;
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

const STARTER_BODY =
    '<h1>Judul Dokumen</h1><p>Nomor: {{nomor_pengadaan}}</p><p>Pekerjaan {{nama_pengadaan}} …</p>';

export default function DocumentTemplateEditor({
    template,
    initialDocumentTypeId,
    documentTypes,
    procurementMethods,
    placeholderCatalog,
    documentStylesheet,
}: {
    template: EditableTemplate | null;
    initialDocumentTypeId: number | null;
    documentTypes: Option[];
    procurementMethods: Option[];
    placeholderCatalog: { key: string; label: string }[];
    documentStylesheet: string;
}) {
    const editorRef = useRef<TemplateEditorHandle>(null);
    const [search, setSearch] = useState('');
    const [previewBody, setPreviewBody] = useState<string | null>(null);

    const form = useForm<TemplateFormValues>({
        document_type_id:
            template?.document_type_id ??
            initialDocumentTypeId ??
            documentTypes[0]?.value ??
            null,
        procurement_method_id: template?.procurement_method_id ?? null,
        name: template?.name ?? '',
        body: template?.body ?? STARTER_BODY,
        is_active: template?.is_active ?? true,
    });

    const filteredPlaceholders = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (term === '') {
            return placeholderCatalog;
        }

        return placeholderCatalog.filter(
            (item) =>
                item.label.toLowerCase().includes(term) ||
                item.key.includes(term),
        );
    }, [placeholderCatalog, search]);

    const currentBody = (): string =>
        editorRef.current?.getBody() ?? form.data.body;

    const submit = () => {
        form.transform((data) => ({ ...data, body: currentBody() }));

        if (template === null) {
            form.post(masterData.documentTemplates.store().url, {
                preserveScroll: true,
            });

            return;
        }

        form.put(masterData.documentTemplates.update(template.id).url, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <>
            <Head
                title={
                    template === null
                        ? 'Template Baru'
                        : `Ubah ${template.name}`
                }
            />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
                className="flex h-[calc(100svh-4rem)] min-h-[640px] flex-col gap-3 p-3 md:p-4"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button variant="ghost" size="icon" asChild>
                            <Link
                                href={masterData.documentTemplates.index().url}
                                aria-label="Kembali ke daftar template"
                            >
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div className="min-w-0">
                            <p className="section-label">
                                Data Master · Template Dokumen
                            </p>
                            <h1 className="truncate text-lg font-semibold tracking-tight">
                                {template === null
                                    ? 'Template Baru'
                                    : `${template.name} · v${template.version}`}
                            </h1>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPreviewBody(currentBody())}
                        >
                            <Eye className="size-4" />
                            Pratinjau
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save className="size-4" />
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 rounded-md border border-border bg-card p-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-start">
                    <div className="grid gap-1.5">
                        <Label htmlFor="template-name">Nama Template</Label>
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

                    <div className="grid gap-1.5">
                        <Label htmlFor="document_type_id">Jenis Dokumen</Label>
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
                        <InputError message={form.errors.document_type_id} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="procurement_method_id">
                            Berlaku Untuk Metode
                        </Label>
                        <Select
                            value={
                                form.data.procurement_method_id === null
                                    ? ALL_METHODS
                                    : String(form.data.procurement_method_id)
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
                        <InputError
                            message={form.errors.procurement_method_id}
                        />
                    </div>

                    <div className="flex h-full items-center gap-3 rounded-md border border-border px-3 py-2 sm:col-span-2 xl:col-span-1 xl:mt-[1.375rem] xl:h-9 xl:py-0">
                        <Switch
                            id="template-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                        <Label
                            htmlFor="template-active"
                            className="whitespace-nowrap"
                        >
                            Template aktif
                        </Label>
                    </div>
                </div>

                <InputError message={form.errors.body} />

                <div className="flex min-h-0 flex-1 gap-3">
                    <TemplateEditor
                        key={template?.id ?? 'new'}
                        ref={editorRef}
                        initialBody={form.data.body}
                        documentStylesheet={documentStylesheet}
                        placeholders={placeholderCatalog}
                        className="min-w-0 flex-1"
                    />

                    <aside className="hidden w-72 shrink-0 flex-col overflow-hidden rounded-md border border-border bg-card lg:flex">
                        <div className="border-b border-border p-3">
                            <p className="flex items-center gap-1.5 text-sm font-semibold">
                                <Braces className="size-4 text-primary" />
                                Data Otomatis
                            </p>
                            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                Letakkan kursor di dokumen, lalu klik data di
                                bawah. Nilainya terisi otomatis dari pengadaan
                                saat dokumen digenerate.
                            </p>
                            <div className="relative mt-2.5">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari data…"
                                    className="h-8 pl-8 text-sm"
                                />
                            </div>
                        </div>

                        <ul className="min-h-0 flex-1 space-y-0.5 overflow-y-auto p-1.5">
                            {filteredPlaceholders.map((item) => (
                                <li key={item.key}>
                                    <button
                                        type="button"
                                        onMouseDown={(event) =>
                                            event.preventDefault()
                                        }
                                        onClick={() =>
                                            editorRef.current?.insertPlaceholder(
                                                item.key,
                                            )
                                        }
                                        className="group flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-primary/8"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-foreground">
                                                {item.label}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-xs font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100">
                                            Sisipkan
                                        </span>
                                    </button>
                                </li>
                            ))}

                            {filteredPlaceholders.length === 0 && (
                                <li className="px-2 py-6 text-center text-xs text-muted-foreground">
                                    Data tidak ditemukan.
                                </li>
                            )}
                        </ul>
                    </aside>
                </div>
            </form>

            <TemplatePreviewDialog
                title={form.data.name || 'Template Baru'}
                body={previewBody}
                documentStylesheet={documentStylesheet}
                onClose={() => setPreviewBody(null)}
            />
        </>
    );
}

DocumentTemplateEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Template Dokumen',
            href: masterData.documentTemplates.index(),
        },
        {
            title: 'Editor',
            href: masterData.documentTemplates.index(),
        },
    ],
};
