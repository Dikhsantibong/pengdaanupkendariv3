import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    ExternalLink,
    FileText,
    FolderOpen,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import documents from '@/routes/documents';
import procurements from '@/routes/procurements';

type ArchivedDocument = {
    id: number;
    title: string;
    type: string;
    template_version: number;
    generated_by: string | null;
    generated_at: string;
};

/**
 * One work's folder of the archive on a page of its own: the documents
 * listed beside, the chosen one shown large as a PDF.
 */
export default function ArchiveFolder({
    procurement,
    documents: archived,
    back,
}: {
    procurement: { id: number; number: string; name: string };
    documents: ArchivedDocument[];
    /** The archive filters the folder was opened from. */
    back: Record<string, string | number>;
}) {
    const [selectedId, setSelectedId] = useState<number | null>(
        archived[0]?.id ?? null,
    );
    const selected = archived.find((document) => document.id === selectedId);

    const pdfUrl = (document: ArchivedDocument) =>
        procurements.documents.show({
            procurement: procurement.id,
            document: document.id,
        }).url;

    return (
        <>
            <Head title={`Arsip · ${procurement.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-3">
                        <FolderOpen className="mt-0.5 size-7 shrink-0 text-amber-500 dark:text-amber-400" />
                        <div className="min-w-0">
                            <p className="section-label">Arsip Dokumen</p>
                            <h1 className="text-lg font-semibold text-foreground">
                                {procurement.name}
                            </h1>
                            <p className="tabular text-xs text-muted-foreground">
                                {procurement.number} · {archived.length} dokumen
                                ·{' '}
                                <Link
                                    href={procurements.show(procurement.id)}
                                    className="text-primary hover:underline"
                                >
                                    Buka detail pengadaan
                                </Link>
                            </p>
                        </div>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={documents.index({ query: back }).url}>
                            <ArrowLeft className="size-4" />
                            Kembali ke Arsip
                        </Link>
                    </Button>
                </div>

                {archived.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={FileText}
                            title="Folder ini kosong"
                            description="Belum ada dokumen yang digenerate untuk pekerjaan ini."
                        />
                    </div>
                ) : (
                    <div className="grid flex-1 gap-4 lg:grid-cols-[22rem_minmax(0,1fr)]">
                        <ul className="space-y-1.5 lg:max-h-[calc(100svh-13rem)] lg:overflow-y-auto">
                            {archived.map((document) => {
                                const isSelected = document.id === selectedId;

                                return (
                                    <li key={document.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setSelectedId(document.id)
                                            }
                                            aria-pressed={isSelected}
                                            className={cn(
                                                'flex w-full items-start gap-2 rounded-md border border-border bg-card p-3 text-left transition-colors hover:border-primary/50 hover:bg-accent/40',
                                                isSelected &&
                                                    'border-primary bg-primary/5 ring-1 ring-primary/30',
                                            )}
                                        >
                                            <FileText
                                                className={cn(
                                                    'mt-0.5 size-4 shrink-0',
                                                    isSelected
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground',
                                                )}
                                            />
                                            <span className="min-w-0">
                                                <span className="block text-sm font-medium text-foreground">
                                                    {document.type}
                                                </span>
                                                <span className="tabular block text-[11px] text-muted-foreground">
                                                    {formatDateTime(
                                                        document.generated_at,
                                                    )}
                                                    {document.generated_by
                                                        ? ` · ${document.generated_by}`
                                                        : ''}
                                                    {` · Template v${document.template_version}`}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>

                        {selected && (
                            <section className="flex min-h-[70svh] flex-col overflow-hidden rounded-md border border-border bg-card">
                                <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-2.5">
                                    <p className="min-w-0 truncate text-sm font-semibold">
                                        {selected.title}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <a
                                                href={`${pdfUrl(selected)}?preview=1`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                Tab Baru
                                            </a>
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a href={pdfUrl(selected)}>
                                                <Download className="size-3.5" />
                                                Unduh PDF
                                            </a>
                                        </Button>
                                    </div>
                                </header>
                                <iframe
                                    key={selected.id}
                                    src={`${pdfUrl(selected)}?preview=1#view=FitH`}
                                    title={selected.title}
                                    className="w-full flex-1 border-0 bg-white"
                                />
                            </section>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

ArchiveFolder.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Arsip Dokumen', href: documents.index() },
    ],
};
