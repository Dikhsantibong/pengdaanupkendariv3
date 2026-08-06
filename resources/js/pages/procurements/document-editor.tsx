import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Code2,
    Download,
    Eye,
    PenLine,
    RotateCcw,
    Save,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { VisualEditor } from '@/components/document-editor/visual-editor';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';

type EditorDocument = {
    id: number;
    title: string;
    body: string;
    type: string;
    template_version: number;
    revision: number;
    generated_at: string;
    generated_by: string | null;
    edited_at: string | null;
    edited_by: string | null;
};

type PlaceholderRow = {
    key: string;
    label: string;
    value: string;
};

type ViewMode = 'visual' | 'source' | 'preview';

export default function DocumentEditor({
    procurement,
    document: doc,
    placeholders,
}: {
    procurement: { id: number; number: string; name: string };
    document: EditorDocument;
    placeholders: PlaceholderRow[];
}) {
    const form = useForm({ title: doc.title, body: doc.body });
    const [mode, setMode] = useState<ViewMode>('visual');
    const [confirmReload, setConfirmReload] = useState(false);
    const bodyRef = useRef<HTMLTextAreaElement>(null);

    const dirty = form.data.title !== doc.title || form.data.body !== doc.body;

    const stats = useMemo(() => {
        const text = form.data.body.replace(/<[^>]*>/g, ' ');
        const words = text.split(/\s+/).filter(Boolean).length;

        return { words, characters: form.data.body.length };
    }, [form.data.body]);

    const showUrl = procurements.documents.show({
        procurement: procurement.id,
        document: doc.id,
    }).url;

    const save = () => {
        form.put(
            procurements.documents.update({
                procurement: procurement.id,
                document: doc.id,
            }).url,
            { preserveScroll: true },
        );
    };

    /**
     * Drop a placeholder code in at the caret, in whichever mode is open.
     *
     * In the visual editor the caret lives in the document itself; in source
     * mode it lives in the textarea. Both are handled so the data catalogue
     * below stays useful either way.
     */
    const insertPlaceholder = (key: string) => {
        const code = `{{${key}}}`;

        if (mode === 'source') {
            const field = bodyRef.current;

            if (field === null) {
                return;
            }

            const { selectionStart: start, selectionEnd: end, value } = field;
            const next = value.slice(0, start) + code + value.slice(end);

            form.setData('body', next);

            queueMicrotask(() => {
                field.focus();
                field.setSelectionRange(
                    start + code.length,
                    start + code.length,
                );
            });

            return;
        }

        setMode('visual');

        queueMicrotask(() => {
            const area = window.document.querySelector<HTMLElement>(
                '[aria-label="Isi dokumen"]',
            );

            if (area === null) {
                return;
            }

            area.focus();
            window.document.execCommand('insertText', false, code);
            form.setData('body', area.innerHTML);
        });
    };

    return (
        <>
            <Head title={`Edit ${doc.title}`} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow={`${procurement.number} · ${doc.type}`}
                    title="Edit Dokumen"
                    description="Perbaiki redaksi maupun data yang terpanggil pada dokumen. Perubahan tersimpan sebagai revisi dan langsung dipakai saat dokumen diunduh."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.visit(
                                        procurements.show(procurement.id).url,
                                    )
                                }
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setConfirmReload(true)}
                            >
                                <RotateCcw className="size-4" />
                                Muat Ulang dari Template
                            </Button>
                            <Button asChild variant="outline">
                                <a href={showUrl}>
                                    <Download className="size-4" />
                                    Unduh PDF
                                </a>
                            </Button>
                            <Button
                                onClick={save}
                                disabled={!dirty || form.processing}
                            >
                                <Save className="size-4" />
                                {form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan Perubahan'}
                            </Button>
                        </div>
                    }
                />

                <dl className="grid gap-3 rounded-md border border-border bg-card p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Pengadaan
                        </dt>
                        <dd className="font-medium">{procurement.name}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Versi Template
                        </dt>
                        <dd className="tabular font-medium">
                            v{doc.template_version}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Digenerate
                        </dt>
                        <dd className="tabular">
                            {formatDateTime(doc.generated_at)}
                            {doc.generated_by ? ` · ${doc.generated_by}` : ''}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Revisi Terakhir
                        </dt>
                        <dd className="tabular">
                            {doc.revision === 0 ? (
                                <span className="text-muted-foreground">
                                    Belum pernah diedit
                                </span>
                            ) : (
                                <>
                                    Revisi {doc.revision} ·{' '}
                                    {formatDateTime(doc.edited_at)}
                                    {doc.edited_by ? ` · ${doc.edited_by}` : ''}
                                </>
                            )}
                        </dd>
                    </div>
                </dl>

                <div className="grid gap-2">
                    <Label htmlFor="title">Judul Dokumen</Label>
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        autoComplete="off"
                    />
                    <InputError message={form.errors.title} />
                </div>

                <Tabs
                    defaultValue="visual"
                    value={mode}
                    onValueChange={(next) => setMode(next as ViewMode)}
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <TabsList>
                            <TabsTrigger value="visual">
                                <PenLine className="size-3.5" />
                                Editor Visual
                            </TabsTrigger>
                            <TabsTrigger value="preview">
                                <Eye className="size-3.5" />
                                Pratinjau Cetak
                            </TabsTrigger>
                            <TabsTrigger value="source">
                                <Code2 className="size-3.5" />
                                Sumber HTML
                            </TabsTrigger>
                        </TabsList>

                        <span className="tabular text-xs text-muted-foreground">
                            {stats.words} kata · {stats.characters} karakter
                        </span>
                    </div>

                    <TabsContent value="visual" className="mt-3">
                        <VisualEditor
                            value={form.data.body}
                            onChange={(html) => form.setData('body', html)}
                        />
                        <p className="mt-2 text-xs text-muted-foreground">
                            Ketik langsung pada dokumen seperti mengetik di
                            Word. Klik di dalam tabel untuk menambah atau
                            menghapus baris dan kolom.
                        </p>
                        <InputError message={form.errors.body} />
                    </TabsContent>

                    <TabsContent value="preview" className="mt-3">
                        <div className="min-h-[70vh] overflow-auto rounded-md border border-border bg-white p-6">
                            <div
                                className="document-preview"
                                dangerouslySetInnerHTML={{
                                    __html: form.data.body,
                                }}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="source" className="mt-3">
                        <Textarea
                            id="body"
                            ref={bodyRef}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            spellCheck={false}
                            className="min-h-[70vh] resize-y font-mono text-xs leading-relaxed"
                        />
                        <p className="mt-2 text-xs text-muted-foreground">
                            Mode lanjutan untuk penyuntingan HTML langsung.
                            Gunakan bila perlu mengatur hal yang tidak tersedia
                            di editor visual.
                        </p>
                        <InputError message={form.errors.body} />
                    </TabsContent>
                </Tabs>
                <section className="flex flex-col gap-3 rounded-md border border-border bg-card p-4">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Data yang Terpanggil
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Nilai berikut diambil dari data pengadaan saat
                            dokumen digenerate. Bila ada yang keliru, perbaiki
                            datanya lalu tekan Muat Ulang dari Template. Klik
                            kode untuk menyisipkannya ke dokumen.
                        </p>
                    </div>

                    <div className="grid gap-1.5 sm:grid-cols-2 xl:grid-cols-3">
                        {placeholders.map((placeholder) => (
                            <button
                                key={placeholder.key}
                                type="button"
                                onClick={() =>
                                    insertPlaceholder(placeholder.key)
                                }
                                className="flex flex-col gap-0.5 rounded-md border border-border px-3 py-2 text-left transition-colors hover:border-primary hover:bg-accent"
                            >
                                <span className="font-mono text-[11px] text-muted-foreground">
                                    {`{{${placeholder.key}}}`}
                                </span>
                                <span className="text-sm font-medium">
                                    {placeholder.value}
                                </span>
                                <span className="text-[11px] text-muted-foreground">
                                    {placeholder.label}
                                </span>
                            </button>
                        ))}
                    </div>
                </section>
            </div>

            <Dialog open={confirmReload} onOpenChange={setConfirmReload}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Muat ulang dari template?</DialogTitle>
                        <DialogDescription>
                            Dokumen akan disusun ulang dari template dengan data
                            pengadaan terbaru. Seluruh perbaikan manual pada
                            dokumen ini akan hilang dan nomor revisi kembali ke
                            nol.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmReload(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            onClick={() => {
                                setConfirmReload(false);
                                router.post(
                                    procurements.documents.regenerate({
                                        procurement: procurement.id,
                                        document: doc.id,
                                    }).url,
                                    {},
                                    { preserveScroll: false },
                                );
                            }}
                        >
                            Muat Ulang
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

DocumentEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengadaan', href: procurements.index() },
        { title: 'Edit Dokumen', href: '#' },
    ],
};
