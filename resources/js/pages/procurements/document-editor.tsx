import { Head, router, useForm, usePoll } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    Eye,
    PenLine,
    RefreshCw,
    RotateCcw,
    Rows3,
    Save,
} from 'lucide-react';
import { useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import { PdfPreview } from '@/components/document-editor/pdf-preview';
import { VisualEditor } from '@/components/document-editor/visual-editor';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { ItemListCard } from '@/components/procurement/item-list-card';
import type {
    ItemPartner,
    ItemRowPayload,
    ProcurementItem,
} from '@/components/procurement/item-list-card';
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

type ViewMode = 'visual' | 'preview' | 'items';

/** The XSRF header Laravel accepts, read from the cookie it sets. */
function xsrfHeader(): Record<string, string> {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match === null
        ? {}
        : { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) };
}

/** How long the Rincian Item waits after typing before redrawing, in ms. */
const ITEM_REDRAW_DELAY = 400;

/** How often the editor pulls the latest procurement data, in ms. */
const LIVE_REFRESH_INTERVAL = 4000;

/**
 * Bring the untouched live fields of a draft up to date.
 *
 * A field generated as <span data-field data-value> is only rewritten while
 * its text still equals what the system put there, so anything the author
 * typed over is left alone. Full HTML documents are refreshed on the server.
 */
function refreshLiveFields(
    body: string,
    values: Record<string, string>,
): string {
    if (!body.includes('data-field=') || /<html[\s>]/i.test(body)) {
        return body;
    }

    const normalise = (text: string) => text.replace(/\s+/g, ' ').trim();
    const holder = document.createElement('template');
    holder.innerHTML = body;
    let changed = false;

    holder.content
        .querySelectorAll<HTMLElement>('span[data-field]')
        .forEach((field) => {
            const key = field.dataset.field ?? '';
            const next = values[key];
            const stored = field.dataset.value ?? '';

            if (
                next === undefined ||
                normalise(field.textContent ?? '') !== normalise(stored) ||
                normalise(next) === normalise(stored)
            ) {
                return;
            }

            field.textContent = next;
            field.dataset.value = normalise(next);
            changed = true;
        });

    return changed ? holder.innerHTML : body;
}

export default function DocumentEditor({
    procurement,
    document: doc,
    placeholders = [],
    liveVersion,
    items = null,
    itemPartners = [],
    itemLayout = null,
    canManageItems = false,
}: {
    procurement: { id: number; number: string; name: string };
    document: EditorDocument;
    placeholders?: PlaceholderRow[];
    /** Fingerprint of the stored body, polled instead of the body itself. */
    liveVersion?: string;
    /** The Rincian Item, for a document with an item table; null otherwise. */
    items?: ProcurementItem[] | null;
    /** The calon mitra compared in the Evaluasi Harga, a column each. */
    itemPartners?: ItemPartner[];
    /** The document type, whose item table decides the grid's columns. */
    itemLayout?: string | null;
    canManageItems?: boolean;
}) {
    const form = useForm({ title: doc.title, body: doc.body });
    const [mode, setMode] = useState<ViewMode>(
        items !== null ? 'items' : 'visual',
    );
    const [confirmReload, setConfirmReload] = useState(false);

    // The Rincian Item as changed in this editor; null while untouched.
    const [itemRows, setItemRows] = useState<ItemRowPayload[] | null>(null);

    const dirty =
        form.data.title !== doc.title ||
        form.data.body !== doc.body ||
        itemRows !== null;

    // Each change of the Rincian Item redraws the draft's item table on the
    // server's renderer, so the Editor Visual shows the printed table as
    // it is typed.
    const draftBody = useRef(form.data.body);

    useEffect(() => {
        draftBody.current = form.data.body;
    }, [form.data.body]);

    useEffect(() => {
        if (itemRows === null) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(
                procurements.documents.itemsPreview({
                    procurement: procurement.id,
                    document: doc.id,
                }).url,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...xsrfHeader(),
                    },
                    body: JSON.stringify({
                        rows: itemRows,
                        body: draftBody.current,
                    }),
                    signal: controller.signal,
                },
            )
                .then((response) =>
                    response.ok
                        ? (response.json() as Promise<{ body: string }>)
                        : null,
                )
                .then((data) => {
                    if (data && data.body !== draftBody.current) {
                        form.setData('body', data.body);
                    }
                })
                .catch(() => {
                    // Aborted by the next change, or offline: the rows are
                    // still kept on save, which draws the table again.
                });
        }, ITEM_REDRAW_DELAY);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [itemRows]);

    // Keep the document in step with the procurement while it is open: data
    // changed on another screen (or by a colleague) shows up here without
    // leaving the page.
    // Only a fingerprint travels on each poll; the (possibly 40-page) body
    // is fetched again only when that fingerprint moves.
    usePoll(LIVE_REFRESH_INTERVAL, { only: ['liveVersion'] });

    const knownVersion = useRef(liveVersion);

    useEffect(() => {
        if (liveVersion === undefined || liveVersion === knownVersion.current) {
            return;
        }

        knownVersion.current = liveVersion;
        router.reload({ only: ['document', 'placeholders'] });
    }, [liveVersion]);

    const [liveNotice, setLiveNotice] = useState(false);
    const previousBody = useRef(doc.body);

    useEffect(() => {
        const before = previousBody.current;
        previousBody.current = doc.body;

        if (doc.body === before) {
            return;
        }

        const values = Object.fromEntries(
            placeholders.map((row) => [row.key, row.value]),
        );

        // Untouched draft: take the refreshed document as it is. A draft
        // with unsaved edits keeps them and only its live fields move.
        const next =
            form.data.body === before
                ? doc.body
                : refreshLiveFields(form.data.body, values);

        if (next !== form.data.body) {
            form.setData('body', next);
            setLiveNotice(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [doc.body]);

    useEffect(() => {
        if (!liveNotice) {
            return;
        }

        const timer = window.setTimeout(() => setLiveNotice(false), 4000);

        return () => window.clearTimeout(timer);
    }, [liveNotice]);

    // Counting words over a long document on every keystroke is wasted
    // work; the counter may trail the typing by a frame.
    const deferredBody = useDeferredValue(form.data.body);

    const stats = useMemo(() => {
        const text = deferredBody.replace(/<[^>]*>/g, ' ');
        const words = text.split(/\s+/).filter(Boolean).length;

        return { words, characters: deferredBody.length };
    }, [deferredBody]);

    const showUrl = procurements.documents.show({
        procurement: procurement.id,
        document: doc.id,
    }).url;

    const previewUrl = procurements.documents.preview({
        procurement: procurement.id,
        document: doc.id,
    }).url;

    const pagesUrl = procurements.documents.pages({
        procurement: procurement.id,
        document: doc.id,
    }).url;

    const save = () => {
        form.transform((data) =>
            itemRows === null ? data : { ...data, item_rows: itemRows },
        );
        form.put(
            procurements.documents.update({
                procurement: procurement.id,
                document: doc.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => setItemRows(null),
            },
        );
    };

    return (
        <>
            <Head title={`Edit ${doc.title}`} />

            <div className="flex flex-col gap-3 p-4 md:p-6">
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

                {/* One compact line, so the page keeps its room for the editor. */}
                <dl className="flex flex-wrap gap-x-6 gap-y-1 rounded-md border border-border bg-card px-3 py-2 text-xs">
                    <div className="flex min-w-0 items-baseline gap-1.5">
                        <dt className="text-muted-foreground">Pengadaan</dt>
                        <dd className="truncate font-medium">
                            {procurement.name}
                        </dd>
                    </div>
                    <div className="flex items-baseline gap-1.5">
                        <dt className="text-muted-foreground">
                            Versi Template
                        </dt>
                        <dd className="tabular font-medium">
                            v{doc.template_version}
                        </dd>
                    </div>
                    <div className="flex items-baseline gap-1.5">
                        <dt className="text-muted-foreground">Digenerate</dt>
                        <dd className="tabular">
                            {formatDateTime(doc.generated_at)}
                            {doc.generated_by ? ` · ${doc.generated_by}` : ''}
                        </dd>
                    </div>
                    <div className="flex items-baseline gap-1.5">
                        <dt className="text-muted-foreground">
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

                <div className="grid gap-1.5 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-3">
                    <Label htmlFor="title" className="whitespace-nowrap">
                        Judul Dokumen
                    </Label>
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        autoComplete="off"
                        className="h-9"
                    />
                    <InputError
                        message={form.errors.title}
                        className="sm:col-start-2"
                    />
                </div>

                <Tabs
                    defaultValue="visual"
                    value={mode}
                    onValueChange={(next) => setMode(next as ViewMode)}
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <TabsList>
                            {items !== null && (
                                <TabsTrigger value="items">
                                    <Rows3 className="size-3.5" />
                                    Rincian Item
                                    {items.length > 0 && ` (${items.length})`}
                                </TabsTrigger>
                            )}
                            <TabsTrigger value="visual">
                                <PenLine className="size-3.5" />
                                Editor Visual
                            </TabsTrigger>
                            <TabsTrigger value="preview">
                                <Eye className="size-3.5" />
                                Pratinjau Cetak
                            </TabsTrigger>
                        </TabsList>

                        <span className="flex items-center gap-3 text-xs text-muted-foreground">
                            <span
                                className={
                                    liveNotice
                                        ? 'flex items-center gap-1 font-medium text-primary'
                                        : 'flex items-center gap-1'
                                }
                                title="Data pengadaan diperbarui otomatis saat berubah"
                            >
                                <RefreshCw
                                    className={
                                        liveNotice
                                            ? 'size-3 animate-spin'
                                            : 'size-3'
                                    }
                                />
                                {liveNotice
                                    ? 'Data terbaru dimuat'
                                    : 'Sinkron otomatis'}
                            </span>
                            <span className="tabular">
                                {stats.words} kata · {stats.characters} karakter
                            </span>
                        </span>
                    </div>

                    <TabsContent value="visual" className="mt-2">
                        <VisualEditor
                            value={form.data.body}
                            onChange={(html) => form.setData('body', html)}
                            pagesEndpoint={pagesUrl}
                            title={form.data.title}
                            // The item table is filled in the Rincian Item.
                            tableTools={items === null}
                        />
                        {items === null && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                Ketik langsung pada dokumen seperti mengetik di
                                Word. Klik di dalam tabel untuk menambah atau
                                menghapus baris dan kolom.
                            </p>
                        )}
                        <InputError message={form.errors.body} />
                    </TabsContent>

                    {items !== null && (
                        <TabsContent value="items" className="mt-3">
                            <ItemListCard
                                items={items}
                                layout={itemLayout}
                                partners={itemPartners}
                                editable={canManageItems}
                                errors={
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                }
                                onRowsChange={setItemRows}
                            />
                        </TabsContent>
                    )}

                    <TabsContent value="preview" className="mt-3">
                        <PdfPreview
                            endpoint={previewUrl}
                            title={form.data.title}
                            body={form.data.body}
                        />
                    </TabsContent>
                </Tabs>
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
