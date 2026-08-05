import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Copy,
    Download,
    Eye,
    RotateCcw,
    Save,
    SplitSquareHorizontal,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
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

type ViewMode = 'split' | 'source' | 'preview';

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
    const [mode, setMode] = useState<ViewMode>('split');
    const [confirmReload, setConfirmReload] = useState(false);
    const bodyRef = useRef<HTMLTextAreaElement>(null);

    const dirty = form.data.title !== doc.title || form.data.body !== doc.body;

    const stats = useMemo(() => {
        const text = form.data.body.replace(/<[^>]*>/g, ' ');
        const words = text.split(/\s+/).filter(Boolean).length;

        return {
            words,
            characters: form.data.body.length,
            lines: form.data.body.split('\n').length,
        };
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

    // Wrap the selected text, or drop the snippet in at the caret when nothing
    // is selected. Keeps common RKS markup a click away instead of by hand.
    const wrapSelection = (before: string, after = '') => {
        const field = bodyRef.current;

        if (field === null) {
            return;
        }

        const { selectionStart: start, selectionEnd: end, value } = field;
        const selected = value.slice(start, end);
        const next =
            value.slice(0, start) +
            before +
            selected +
            after +
            value.slice(end);

        form.setData('body', next);

        queueMicrotask(() => {
            field.focus();
            const caret = start + before.length + selected.length;
            field.setSelectionRange(caret, caret);
        });
    };

    const insertPlaceholder = (key: string) => wrapSelection(`{{${key}}}`);

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

                <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-card px-3 py-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <ToolbarButton
                            label="Tebal"
                            onClick={() => wrapSelection('<b>', '</b>')}
                        />
                        <ToolbarButton
                            label="Miring"
                            onClick={() => wrapSelection('<i>', '</i>')}
                        />
                        <ToolbarButton
                            label="Paragraf"
                            onClick={() => wrapSelection('<p>', '</p>')}
                        />
                        <ToolbarButton
                            label="Sub Judul"
                            onClick={() => wrapSelection('<h3>', '</h3>')}
                        />
                        <ToolbarButton
                            label="Daftar"
                            onClick={() =>
                                wrapSelection('<ol>\n<li>', '</li>\n</ol>')
                            }
                        />
                        <ToolbarButton
                            label="Isian"
                            onClick={() =>
                                wrapSelection(
                                    '<span class="fill">',
                                    '..........</span>',
                                )
                            }
                        />
                        <ToolbarButton
                            label="Halaman Baru"
                            onClick={() =>
                                wrapSelection(
                                    '<section class="bab">\n',
                                    '\n</section>',
                                )
                            }
                        />
                    </div>

                    <div className="flex items-center gap-1.5">
                        <ModeButton
                            active={mode === 'source'}
                            onClick={() => setMode('source')}
                            icon={<Copy className="size-3.5" />}
                            label="Sumber"
                        />
                        <ModeButton
                            active={mode === 'split'}
                            onClick={() => setMode('split')}
                            icon={
                                <SplitSquareHorizontal className="size-3.5" />
                            }
                            label="Terbagi"
                        />
                        <ModeButton
                            active={mode === 'preview'}
                            onClick={() => setMode('preview')}
                            icon={<Eye className="size-3.5" />}
                            label="Pratinjau"
                        />
                    </div>
                </div>

                <div
                    className={
                        mode === 'split'
                            ? 'grid gap-4 lg:grid-cols-2'
                            : 'grid gap-4'
                    }
                >
                    {mode !== 'preview' && (
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="body">Sumber Dokumen</Label>
                                <span className="tabular text-xs text-muted-foreground">
                                    {stats.words} kata · {stats.characters}{' '}
                                    karakter · {stats.lines} baris
                                </span>
                            </div>
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
                            <InputError message={form.errors.body} />
                        </div>
                    )}

                    {mode !== 'source' && (
                        <div className="flex flex-col gap-2">
                            <Label>Pratinjau Cetak</Label>
                            <div className="min-h-[70vh] overflow-auto rounded-md border border-border bg-white p-6">
                                <div
                                    className="document-preview"
                                    dangerouslySetInnerHTML={{
                                        __html: form.data.body,
                                    }}
                                />
                            </div>
                        </div>
                    )}
                </div>

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

function ToolbarButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <Button type="button" size="sm" variant="ghost" onClick={onClick}>
            {label}
        </Button>
    );
}

function ModeButton({
    active,
    onClick,
    icon,
    label,
}: {
    active: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    label: string;
}) {
    return (
        <Button
            type="button"
            size="sm"
            variant={active ? 'secondary' : 'ghost'}
            onClick={onClick}
        >
            {icon}
            {label}
        </Button>
    );
}

DocumentEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengadaan', href: procurements.index() },
        { title: 'Edit Dokumen', href: '#' },
    ],
};
