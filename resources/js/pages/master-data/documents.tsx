import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    Eye,
    FileCode2,
    FileText,
    ListChecks,
    Pencil,
    Plus,
    Power,
    Save,
    Search,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { PdfPreview } from '@/components/document-editor/pdf-preview';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { TemplateEditor } from '@/components/template-editor';
import type { TemplateEditorHandle } from '@/components/template-editor';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDateTime } from '@/lib/format';
import { fillSampleValues } from '@/lib/template-document';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption, ProcurementStage } from '@/types';

type TemplateSummary = {
    id: number;
    name: string;
    version: number;
    procurement_method: string | null;
    contract_number_format: string | null;
    work_category: string | null;
    /** default: shipped wording; modified: shipped then edited; custom: written here. */
    origin: 'default' | 'modified' | 'custom';
    is_active: boolean;
    usage_count: number;
    updated_at: string | null;
};

type DocumentTypeRecord = {
    id: number;
    code: string;
    name: string;
    stage: ProcurementStage;
    stage_label: string;
    upload_only: boolean;
    contract_number_format_id: string;
    contract_number_format: string | null;
    /** The family of documents it is a variant of (rks, spk, ...). */
    family: string;
    procurement_method: string | null;
    work_category: string | null;
    selection_method_ids: number[];
    selection_methods: string[];
    description: string | null;
    sort_order: number;
    is_active: boolean;
    template_count: number;
    active_template: { id: number; name: string; version: number } | null;
    usage_count: number;
    templates: TemplateSummary[];
    steps: {
        id: number;
        name: string;
        stage_label: string;
        is_active: boolean;
    }[];
};

type Workbench = {
    template: {
        id: number;
        document_type_id: number;
        procurement_method_id: number | null;
        contract_number_format_id: number | null;
        work_category: string | null;
        name: string;
        version: number;
        body: string;
        is_active: boolean;
    };
    placeholderCatalog: { key: string; label: string }[];
    documentStylesheet: string;
};

const ALL = 'semua';

/**
 * Data Master · Dokumen & Template: every document type with its templates
 * and the steps that ask for it, on one screen. A template opens in a visual
 * editor beside its PDF as it will print.
 */
export default function Documents({
    records,
    stages,
    contractNumberFormats,
    selectionMethods,
    workbench,
}: {
    records: DocumentTypeRecord[];
    stages: EnumOption[];
    contractNumberFormats: EnumOption[];
    selectionMethods: EnumOption[];
    workbench?: Workbench | null;
}) {
    const [search, setSearch] = useState('');
    const [stage, setStage] = useState<string>(ALL);
    const [selectedId, setSelectedId] = useState<number | null>(() => {
        const asked = Number(
            new URLSearchParams(
                typeof window === 'undefined' ? '' : window.location.search,
            ).get('jenis'),
        );

        return records.some((record) => record.id === asked) ? asked : null;
    });
    const [typeForm, setTypeForm] = useState<DocumentTypeRecord | 'new' | null>(
        null,
    );
    // The template shown for each document, when another than its active one.
    const [chosenTemplates, setChosenTemplates] = useState<
        Record<number, number>
    >({});

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();

        return records.filter(
            (record) =>
                (stage === ALL || record.stage === stage) &&
                (term === '' ||
                    record.name.toLowerCase().includes(term) ||
                    record.code.includes(term) ||
                    record.templates.some((template) =>
                        template.name.toLowerCase().includes(term),
                    ) ||
                    record.steps.some((step) =>
                        step.name.toLowerCase().includes(term),
                    )),
        );
    }, [records, search, stage]);

    const grouped = stages
        .map((option) => ({
            ...option,
            records: visible.filter((record) => record.stage === option.value),
        }))
        .filter((group) => group.records.length > 0);

    // None picked yet: the first document of the list, as it reads.
    const selected =
        records.find((record) => record.id === selectedId) ??
        grouped[0]?.records[0] ??
        null;

    // The document's template on show: the one picked, else its active one.
    const previewTemplateId =
        selected === null || selected.upload_only
            ? null
            : (chosenTemplates[selected.id] ??
              selected.active_template?.id ??
              selected.templates[0]?.id ??
              null);

    // Picking a document loads its template at once, for the PDF on the right.
    useEffect(() => {
        if (
            previewTemplateId === null ||
            workbench?.template.id === previewTemplateId
        ) {
            return;
        }

        router.reload({
            only: ['workbench'],
            data: { template: previewTemplateId },
        });
        // Only a different template is loaded; the props it brings do not.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [previewTemplateId]);

    const defaultWorkspaceTab =
        selected === null
            ? 'pdf'
            : selected.upload_only
              ? 'steps'
              : selected.templates.length === 0
                ? 'templates'
                : 'pdf';
    const [tabByDoc, setTabByDoc] = useState<Record<number, string>>({});
    const workspaceTab =
        selected !== null
            ? (tabByDoc[selected.id] ?? defaultWorkspaceTab)
            : 'pdf';
    const setWorkspaceTab = (next: string) => {
        if (selected === null) {
            return;
        }

        setTabByDoc((current) => ({ ...current, [selected.id]: next }));
    };

    const showTemplate = (templateId: number) => {
        if (selected === null) {
            return;
        }

        setChosenTemplates((chosen) => ({
            ...chosen,
            [selected.id]: templateId,
        }));
        setWorkspaceTab('pdf');
        document
            .getElementById('template-preview')
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <>
            <Head title="Dokumen & Template" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Data Master"
                    title="Dokumen & Template"
                    description="Jenis dokumen, template yang dipakai untuk menggenerate-nya, dan tahapan yang memintanya, dalam satu layar. Satu jenis dokumen memiliki tepat satu template. Pilih dokumen di kiri: pratinjau PDF template-nya langsung tampil di kanan, dengan tab Editor Visual untuk mengubahnya."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setTypeForm('new')}
                            >
                                <Plus className="size-4" />
                                Jenis Dokumen
                            </Button>
                            <Button asChild>
                                <Link
                                    href={
                                        masterData.documentTemplates.create({
                                            query:
                                                selected === null ||
                                                selected.templates.length > 0
                                                    ? {}
                                                    : {
                                                          document_type_id:
                                                              selected.id,
                                                      },
                                        }).url
                                    }
                                >
                                    <Plus className="size-4" />
                                    Template
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                    {/* The documents, by stage. */}
                    <aside className="flex min-h-0 flex-col gap-3 rounded-md border border-border bg-card p-3 lg:sticky lg:top-4 lg:max-h-[calc(100svh-7rem)]">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari dokumen, template, tahapan…"
                                className="pl-9"
                                aria-label="Cari dokumen"
                            />
                        </div>
                        <div className="flex flex-wrap gap-1">
                            {[{ value: ALL, label: 'Semua' }, ...stages].map(
                                (option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => setStage(option.value)}
                                        className={cn(
                                            'rounded-sm px-2 py-1 text-xs font-medium',
                                            stage === option.value
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {option.label}
                                    </button>
                                ),
                            )}
                        </div>

                        <div className="-mx-1 min-h-0 flex-1 overflow-x-hidden overflow-y-auto px-1">
                            {grouped.length === 0 ? (
                                <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                                    Tidak ada dokumen yang cocok.
                                </p>
                            ) : (
                                grouped.map((group) => (
                                    <div key={group.value} className="mb-3">
                                        <p className="section-label px-2 pb-1">
                                            {group.label} (
                                            {group.records.length})
                                        </p>
                                        <ul className="grid grid-cols-[minmax(0,1fr)] gap-0.5">
                                            {group.records.map((record) => (
                                                <li
                                                    key={record.id}
                                                    className="min-w-0"
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setSelectedId(
                                                                record.id,
                                                            )
                                                        }
                                                        className={cn(
                                                            'flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-left text-sm',
                                                            selected?.id ===
                                                                record.id
                                                                ? 'bg-primary/10 text-foreground'
                                                                : 'hover:bg-muted',
                                                        )}
                                                    >
                                                        {record.upload_only ? (
                                                            <Upload className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                                        ) : (
                                                            <FileText className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                                        )}
                                                        <span className="min-w-0 flex-1">
                                                            <span
                                                                className={cn(
                                                                    'block truncate font-medium',
                                                                    !record.is_active &&
                                                                        'text-muted-foreground line-through',
                                                                )}
                                                            >
                                                                {record.name}
                                                            </span>
                                                            <span className="block truncate text-[11px] text-muted-foreground">
                                                                {record.upload_only
                                                                    ? 'Hanya diunggah'
                                                                    : record.active_template ===
                                                                        null
                                                                      ? 'Belum ada template'
                                                                      : `${record.templates.length} template`}
                                                                {record.steps
                                                                    .length >
                                                                    0 &&
                                                                    ` · ${record.steps.length} tahapan`}
                                                            </span>
                                                        </span>
                                                        {!record.upload_only &&
                                                            record.active_template ===
                                                                null && (
                                                                <span
                                                                    className="mt-1.5 size-2 shrink-0 rounded-full bg-status-batal"
                                                                    aria-label="Belum ada template"
                                                                />
                                                            )}
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))
                            )}
                        </div>
                    </aside>

                    {/* The document picked: its settings, templates and steps. */}
                    {selected === null ? (
                        <div className="rounded-md border border-border bg-card">
                            <EmptyState
                                icon={FileCode2}
                                title="Belum ada jenis dokumen"
                                description="Tambahkan jenis dokumen pertama."
                            />
                        </div>
                    ) : (
                        <DocumentDetail
                            record={selected}
                            shownTemplateId={previewTemplateId}
                            workspaceTab={workspaceTab}
                            onTabChange={setWorkspaceTab}
                            onEdit={() => setTypeForm(selected)}
                            onOpenTemplate={showTemplate}
                            workbench={
                                previewTemplateId !== null &&
                                workbench &&
                                workbench.template.id === previewTemplateId
                                    ? workbench
                                    : null
                            }
                            loadingWorkbench={
                                previewTemplateId !== null &&
                                (!workbench ||
                                    workbench.template.id !== previewTemplateId)
                            }
                        />
                    )}
                </div>
            </div>

            <DocumentTypeDialog
                // A fresh form for each document opened.
                key={
                    typeForm === null
                        ? 'closed'
                        : typeForm === 'new'
                          ? 'new'
                          : typeForm.id
                }
                record={typeForm}
                stages={stages}
                contractNumberFormats={contractNumberFormats}
                selectionMethods={selectionMethods}
                onClose={() => setTypeForm(null)}
            />
        </>
    );
}

function DocumentDetail({
    record,
    shownTemplateId,
    workspaceTab,
    onTabChange,
    onEdit,
    onOpenTemplate,
    workbench,
    loadingWorkbench,
}: {
    record: DocumentTypeRecord;
    shownTemplateId: number | null;
    workspaceTab: string;
    onTabChange: (tab: string) => void;
    onEdit: () => void;
    onOpenTemplate: (templateId: number) => void;
    workbench: Workbench | null;
    loadingWorkbench: boolean;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-4">
            <section className="rounded-md border border-border bg-card p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="section-label">
                            Jenis Dokumen · {record.stage_label}
                        </p>
                        <h2 className="mt-1 text-lg font-semibold tracking-tight">
                            {record.name}
                        </h2>
                        {record.description && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {record.description}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" onClick={onEdit}>
                            <Pencil className="size-3.5" />
                            Ubah Jenis
                        </Button>
                        {record.is_active && (
                            <Button
                                size="sm"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `Nonaktifkan "${record.name}"? Dokumen yang sudah dibuat tetap utuh.`,
                                        )
                                    ) {
                                        router.delete(
                                            masterData.documentTypes.destroy(
                                                record.id,
                                            ).url,
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                <Power className="size-3.5" />
                                Nonaktifkan
                            </Button>
                        )}
                    </div>
                </div>

                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <Fact label="Kode">
                        <span className="tabular">{record.code}</span>
                    </Fact>
                    <Fact label="Cara Dibuat">
                        {record.upload_only
                            ? 'Hanya diunggah (tanpa generate)'
                            : 'Digenerate dari template'}
                    </Fact>
                    <Fact label="Format Kontrak">
                        {record.contract_number_format ?? 'Semua format'}
                    </Fact>
                    {(record.procurement_method || record.work_category) && (
                        <Fact label="Khusus untuk">
                            {[
                                record.procurement_method &&
                                    `Metode ${record.procurement_method}`,
                                record.work_category,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </Fact>
                    )}
                    <Fact label="Metode Pengadaan">
                        {record.selection_methods.length === 0
                            ? 'Semua metode'
                            : record.selection_methods.join(', ')}
                    </Fact>
                    <Fact label="Status">
                        <span
                            className={
                                record.is_active
                                    ? 'inline-flex rounded-sm bg-status-selesai-surface px-2 py-0.5 text-xs font-medium text-status-selesai'
                                    : 'inline-flex rounded-sm bg-status-pending-surface px-2 py-0.5 text-xs font-medium text-status-pending'
                            }
                        >
                            {record.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                    </Fact>
                    <Fact label="Sudah Dibuat">
                        <span className="tabular">
                            {record.usage_count} dokumen
                        </span>
                    </Fact>
                    <Fact label="Urutan Tampil">
                        <span className="tabular">{record.sort_order}</span>
                    </Fact>
                </dl>
            </section>

            <DocumentWorkspace
                key={`${record.id}-${workbench?.template.id ?? 'none'}`}
                record={record}
                shownTemplateId={shownTemplateId}
                tab={workspaceTab}
                onTabChange={onTabChange}
                onOpenTemplate={onOpenTemplate}
                workbench={workbench}
                loadingWorkbench={loadingWorkbench}
            />
        </div>
    );
}

/**
 * Clean single-card workspace holding PDF Preview, Visual Editor,
 * Template List, and Checklist Steps in one tab bar—nothing sits below the PDF.
 */
function DocumentWorkspace({
    record,
    shownTemplateId,
    tab,
    onTabChange,
    onOpenTemplate,
    workbench,
    loadingWorkbench,
}: {
    record: DocumentTypeRecord;
    shownTemplateId: number | null;
    tab: string;
    onTabChange: (tab: string) => void;
    onOpenTemplate: (templateId: number) => void;
    workbench: Workbench | null;
    loadingWorkbench: boolean;
}) {
    const editorRef = useRef<TemplateEditorHandle>(null);
    const [editorOpened, setEditorOpened] = useState(tab === 'editor');
    const [body, setBody] = useState(workbench?.template.body ?? '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const hasTemplatePreview = !record.upload_only && record.templates.length > 0;
    const activeTab =
        !hasTemplatePreview && (tab === 'pdf' || tab === 'editor')
            ? record.upload_only
                ? 'steps'
                : 'templates'
            : tab;

    const currentBody = () => editorRef.current?.getBody() ?? body;

    const save = () => {
        if (workbench === null) {
            return;
        }

        const { template } = workbench;
        const latest = currentBody();
        setBody(latest);
        setSaving(true);
        setError(null);

        router.put(
            masterData.documentTemplates.update(template.id).url,
            {
                document_type_id: template.document_type_id,
                procurement_method_id: template.procurement_method_id,
                contract_number_format_id: template.contract_number_format_id,
                work_category: template.work_category,
                name: template.name,
                is_active: template.is_active,
                body: latest,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) =>
                    setError(Object.values(errors)[0] ?? 'Gagal menyimpan.'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <section
            id="template-preview"
            className="flex scroll-mt-4 flex-col gap-3 rounded-md border border-border bg-card p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="section-label">
                        {workbench
                            ? 'Pratinjau & Pengelolaan Template'
                            : record.upload_only
                              ? 'Dokumen Diunggah & Tahapan'
                              : 'Template & Tahapan'}
                    </p>
                    <h3 className="mt-0.5 truncate font-semibold">
                        {workbench ? (
                            <>
                                {workbench.template.name}{' '}
                                <span className="text-xs font-normal text-muted-foreground">
                                    v{workbench.template.version}
                                </span>
                            </>
                        ) : (
                            record.name
                        )}
                    </h3>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {workbench && activeTab !== 'templates' && (
                        <Button size="sm" variant="ghost" asChild>
                            <Link
                                href={
                                    masterData.documentTemplates.edit(
                                        workbench.template.id,
                                    ).url
                                }
                            >
                                <Pencil className="size-3.5" />
                                Editor Lengkap
                            </Link>
                        </Button>
                    )}
                    {!record.upload_only && record.templates.length === 0 && (
                        <Button size="sm" variant="outline" asChild>
                            <Link
                                href={
                                    masterData.documentTemplates.create({
                                        query: { document_type_id: record.id },
                                    }).url
                                }
                            >
                                <Plus className="size-3.5" />
                                Tambah Template
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <Tabs
                defaultValue={activeTab}
                value={activeTab}
                onValueChange={(next) => {
                    if (next === 'editor') {
                        setEditorOpened(true);
                    }

                    if (next === 'pdf' && workbench !== null) {
                        setBody(currentBody());
                    }

                    onTabChange(next);
                }}
                className="flex flex-col gap-3"
            >
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <TabsList className="flex-wrap">
                        {hasTemplatePreview && (
                            <>
                                <TabsTrigger value="pdf">
                                    Pratinjau PDF
                                </TabsTrigger>
                                <TabsTrigger value="editor">
                                    Editor Visual
                                </TabsTrigger>
                            </>
                        )}
                        <TabsTrigger value="templates">
                            Template ({record.templates.length})
                        </TabsTrigger>
                        <TabsTrigger value="steps">
                            Diminta pada Tahapan ({record.steps.length})
                        </TabsTrigger>
                    </TabsList>

                    {activeTab === 'editor' && workbench !== null && (
                        <div className="flex items-center gap-2">
                            {error && <InputError message={error} />}
                            <Button
                                type="button"
                                size="sm"
                                onClick={save}
                                disabled={saving}
                            >
                                <Save className="size-3.5" />
                                {saving ? 'Menyimpan…' : 'Simpan Template'}
                            </Button>
                        </div>
                    )}

                    {activeTab === 'steps' && (
                        <Button size="sm" variant="ghost" asChild>
                            <Link href={masterData.checklistItems.index().url}>
                                <ListChecks className="size-3.5" />
                                Atur di Item Checklist
                            </Link>
                        </Button>
                    )}
                </div>

                {hasTemplatePreview && (
                    <TabsContent value="pdf" className="mt-0">
                        {workbench ? (
                            <>
                                <PdfPreview
                                    endpoint={
                                        masterData.documentTemplates.pdfPreview()
                                            .url
                                    }
                                    title={workbench.template.name}
                                    body={fillSampleValues(body)}
                                    documentTypeId={
                                        workbench.template.document_type_id
                                    }
                                />
                                <p className="mt-2 text-[11px] text-muted-foreground">
                                    Pratinjau memakai contoh data pengadaan.
                                    Template yang disimpan dipakai dokumen
                                    berikutnya; dokumen yang sudah dibuat tidak
                                    berubah.
                                </p>
                            </>
                        ) : loadingWorkbench ? (
                            <div className="flex h-40 items-center justify-center rounded-md border border-border text-sm text-muted-foreground">
                                Memuat pratinjau template…
                            </div>
                        ) : null}
                    </TabsContent>
                )}

                {hasTemplatePreview && workbench && editorOpened && (
                    <div
                        role="tabpanel"
                        className={cn(
                            'h-[80vh] min-h-0 flex-col',
                            activeTab === 'editor' ? 'flex' : 'hidden',
                        )}
                    >
                        <TemplateEditor
                            ref={editorRef}
                            initialBody={workbench.template.body}
                            documentStylesheet={workbench.documentStylesheet}
                            placeholders={workbench.placeholderCatalog}
                            className="min-h-0 flex-1"
                        />
                    </div>
                )}

                <TabsContent value="templates" className="mt-0">
                    {record.upload_only ? (
                        <p className="rounded-md border border-dashed border-border px-4 py-4 text-sm text-muted-foreground">
                            Dokumen ini disiapkan di luar sistem lalu diunggah
                            pada tahapannya, jadi tidak memakai template.
                        </p>
                    ) : record.templates.length === 0 ? (
                        <p className="rounded-md border border-dashed border-border px-4 py-4 text-sm text-status-batal">
                            Belum ada template: dokumen ini belum bisa
                            digenerate.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-md border border-border">
                            {record.templates.map((template) => (
                                <li
                                    key={template.id}
                                    className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {template.name}{' '}
                                            <span className="text-xs font-normal text-muted-foreground">
                                                v{template.version}
                                            </span>
                                        </p>
                                        <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                                            <Badge
                                                tone={
                                                    template.is_active
                                                        ? 'success'
                                                        : 'muted'
                                                }
                                            >
                                                {template.is_active
                                                    ? 'Aktif'
                                                    : 'Arsip'}
                                            </Badge>
                                            <Badge
                                                tone={
                                                    template.origin === 'custom'
                                                        ? 'success'
                                                        : template.origin ===
                                                            'modified'
                                                          ? 'warning'
                                                          : 'muted'
                                                }
                                            >
                                                {template.origin === 'custom'
                                                    ? 'Kustom'
                                                    : template.origin ===
                                                        'modified'
                                                      ? 'Bawaan · diubah'
                                                      : 'Bawaan'}
                                            </Badge>
                                            <Badge tone="muted">
                                                {template.procurement_method ??
                                                    'Semua metode'}
                                            </Badge>
                                            {template.contract_number_format && (
                                                <Badge tone="muted">
                                                    {
                                                        template.contract_number_format
                                                    }
                                                </Badge>
                                            )}
                                            {template.work_category && (
                                                <Badge tone="primary">
                                                    {template.work_category}
                                                </Badge>
                                            )}
                                            <span className="text-muted-foreground">
                                                {template.usage_count} dokumen ·
                                                diperbarui{' '}
                                                {formatDateTime(
                                                    template.updated_at,
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 flex-wrap gap-1">
                                        <Button
                                            size="sm"
                                            variant={
                                                shownTemplateId === template.id
                                                    ? 'secondary'
                                                    : 'default'
                                            }
                                            disabled={
                                                shownTemplateId === template.id &&
                                                activeTab === 'pdf'
                                            }
                                            onClick={() =>
                                                onOpenTemplate(template.id)
                                            }
                                        >
                                            <Eye className="size-3.5" />
                                            {shownTemplateId === template.id
                                                ? 'Ditampilkan'
                                                : 'Tampilkan'}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    masterData.documentTemplates.edit(
                                                        template.id,
                                                    ).url
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Editor Lengkap
                                            </Link>
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
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </TabsContent>

                <TabsContent value="steps" className="mt-0">
                    {record.steps.length === 0 ? (
                        <p className="rounded-md border border-dashed border-border px-4 py-4 text-sm text-muted-foreground">
                            Belum dipakai tahapan mana pun. Hubungkan dokumen ini
                            ke tahapan di Data Master → Item Checklist.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-md border border-border">
                            {record.steps.map((step) => (
                                <li
                                    key={step.id}
                                    className="flex items-center justify-between gap-3 px-4 py-2 text-sm"
                                >
                                    <span
                                        className={cn(
                                            !step.is_active &&
                                                'text-muted-foreground line-through',
                                        )}
                                    >
                                        {step.name}
                                    </span>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {step.stage_label}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </TabsContent>
            </Tabs>
        </section>
    );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}

function Badge({
    tone,
    children,
}: {
    tone: 'success' | 'warning' | 'muted' | 'primary';
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-sm px-1.5 py-0.5 font-medium',
                tone === 'success' &&
                    'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                tone === 'warning' &&
                    'bg-amber-500/10 text-amber-700 dark:text-amber-400',
                tone === 'muted' && 'bg-muted text-muted-foreground',
                tone === 'primary' && 'bg-primary/10 text-primary',
            )}
        >
            {children}
        </span>
    );
}

type DocumentTypeValues = {
    name: string;
    code: string;
    stage: string;
    description: string;
    upload_only: boolean;
    contract_number_format_id: string;
    selection_method_ids: number[];
    sort_order: number;
    is_active: boolean;
};

function DocumentTypeDialog({
    record,
    stages,
    contractNumberFormats,
    selectionMethods,
    onClose,
}: {
    record: DocumentTypeRecord | 'new' | null;
    stages: EnumOption[];
    contractNumberFormats: EnumOption[];
    selectionMethods: EnumOption[];
    onClose: () => void;
}) {
    const existing = record !== null && record !== 'new' ? record : null;
    const form = useForm<DocumentTypeValues>({
        name: existing?.name ?? '',
        code: existing?.code ?? '',
        stage: existing?.stage ?? 'perencanaan',
        description: existing?.description ?? '',
        upload_only: existing?.upload_only ?? false,
        contract_number_format_id:
            existing?.contract_number_format_id ?? 'none',
        selection_method_ids: existing?.selection_method_ids ?? [],
        sort_order: existing?.sort_order ?? 0,
        is_active: existing?.is_active ?? true,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };

        if (existing === null) {
            form.post(masterData.documentTypes.store().url, options);
        } else {
            form.put(masterData.documentTypes.update(existing.id).url, options);
        }
    };

    return (
        <Dialog
            open={record !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {existing === null
                            ? 'Tambah Jenis Dokumen'
                            : `Ubah ${existing.name}`}
                    </DialogTitle>
                    <DialogDescription>
                        Perubahan berlaku langsung tanpa perlu deploy ulang
                        aplikasi.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="document-type-form"
                    className="grid grid-cols-1 items-start gap-4 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <Field
                        id="doc-name"
                        label="Nama Dokumen"
                        error={form.errors.name}
                    >
                        <Input
                            id="doc-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Contoh: RKS (Rencana Kerja dan Syarat)"
                        />
                    </Field>
                    <Field
                        id="doc-code"
                        label="Kode"
                        error={form.errors.code}
                        hint="Dipakai sebagai bagian nama berkas hasil generate."
                    >
                        <Input
                            id="doc-code"
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                            placeholder="Contoh: rks"
                            className="tabular"
                        />
                    </Field>
                    <Field
                        id="doc-stage"
                        label="Tahap"
                        error={form.errors.stage}
                    >
                        <Select
                            value={form.data.stage}
                            onValueChange={(value) =>
                                form.setData('stage', value)
                            }
                        >
                            <SelectTrigger id="doc-stage" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {stages.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        id="doc-format"
                        label="Hanya untuk Format Kontrak"
                        error={form.errors.contract_number_format_id}
                    >
                        <Select
                            value={form.data.contract_number_format_id}
                            onValueChange={(value) =>
                                form.setData('contract_number_format_id', value)
                            }
                        >
                            <SelectTrigger id="doc-format" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {contractNumberFormats.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <div className="md:col-span-2">
                        <Field
                            id="doc-description"
                            label="Keterangan"
                            error={form.errors.description}
                        >
                            <Input
                                id="doc-description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Opsional"
                            />
                        </Field>
                    </div>
                    <div className="grid gap-2 md:col-span-2">
                        <Label>Hanya untuk Metode Pengadaan</Label>
                        <div className="grid gap-1.5 rounded-md border border-border p-3 sm:grid-cols-2">
                            {selectionMethods.map((option) => {
                                const id = Number(option.value);
                                const checked =
                                    form.data.selection_method_ids.includes(id);

                                return (
                                    <label
                                        key={option.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={checked}
                                            onCheckedChange={(value) =>
                                                form.setData(
                                                    'selection_method_ids',
                                                    value === true
                                                        ? [
                                                              ...form.data
                                                                  .selection_method_ids,
                                                              id,
                                                          ]
                                                        : form.data.selection_method_ids.filter(
                                                              (current) =>
                                                                  current !==
                                                                  id,
                                                          ),
                                                )
                                            }
                                        />
                                        {option.label}
                                    </label>
                                );
                            })}
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                            Kosongkan bila diminta pada semua metode.
                        </p>
                        <InputError
                            message={form.errors.selection_method_ids}
                        />
                    </div>
                    <Field
                        id="doc-sort"
                        label="Urutan Tampil"
                        error={form.errors.sort_order}
                    >
                        <Input
                            id="doc-sort"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData(
                                    'sort_order',
                                    Number(event.target.value),
                                )
                            }
                            className="tabular"
                        />
                    </Field>
                    <div className="grid gap-3 self-end">
                        <label className="flex items-center justify-between gap-3 text-sm">
                            Hanya diunggah (tanpa generate)
                            <Switch
                                checked={form.data.upload_only}
                                onCheckedChange={(value) =>
                                    form.setData('upload_only', value)
                                }
                            />
                        </label>
                        <label className="flex items-center justify-between gap-3 text-sm">
                            Aktif
                            <Switch
                                checked={form.data.is_active}
                                onCheckedChange={(value) =>
                                    form.setData('is_active', value)
                                }
                            />
                        </label>
                    </div>
                </form>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="document-type-form"
                        disabled={form.processing}
                    >
                        Simpan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && (
                <p className="text-[11px] text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

Documents.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Dokumen & Template', href: masterData.documentTypes.index() },
    ],
};
