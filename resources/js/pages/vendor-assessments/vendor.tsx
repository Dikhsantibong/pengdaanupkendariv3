import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleCheck,
    CircleDashed,
    ClipboardCheck,
    ExternalLink,
    FolderOpen,
    Pencil,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import vendorAssessments from '@/routes/vendor-assessments';

type VendorAssessmentRow = {
    id: number;
    project: string;
    po_number: string | null;
    po_date: string | null;
    amendment_number: string | null;
    procurement_number: string | null;
    overall_average: number | null;
    scored: number;
    total: number;
    created_by: string | null;
    created_at: string | null;
};

/** Which PDF of the chosen assessment is shown. */
type Sheet = 'sertifikat' | 'semua';

/**
 * One vendor's folder on a page of its own: its assessments listed beside,
 * the chosen one's certificate (or every sheet) shown large as a PDF.
 */
export default function VendorFolder({
    vendor,
    assessments,
    back,
}: {
    vendor: {
        name: string;
        vendor_code: string | null;
        director_name: string | null;
        address: string | null;
    };
    assessments: VendorAssessmentRow[];
    /** The list filters the folder was opened from. */
    back: Record<string, string | number>;
}) {
    const [selectedId, setSelectedId] = useState(assessments[0]?.id ?? null);
    const [sheet, setSheet] = useState<Sheet>('sertifikat');
    const selected = assessments.find(
        (assessment) => assessment.id === selectedId,
    );

    const pdfUrl = (assessment: VendorAssessmentRow) =>
        sheet === 'sertifikat'
            ? vendorAssessments.print({ assessment: assessment.id }).url
            : vendorAssessments.printAll(assessment.id).url;

    const isComplete = (assessment: VendorAssessmentRow) =>
        assessment.total > 0 && assessment.scored === assessment.total;

    return (
        <>
            <Head title={`Penilaian · ${vendor.name}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-3">
                        <FolderOpen className="mt-0.5 size-7 shrink-0 text-amber-500 dark:text-amber-400" />
                        <div className="min-w-0">
                            <p className="section-label">Penilaian Penyedia</p>
                            <h1 className="text-lg font-semibold text-foreground">
                                {vendor.name}
                            </h1>
                            <p className="text-xs text-muted-foreground">
                                {[
                                    `${assessments.length} penilaian`,
                                    vendor.vendor_code &&
                                        `Kode rekanan ${vendor.vendor_code}`,
                                    vendor.director_name &&
                                        `Direktur ${vendor.director_name}`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={vendorAssessments.index({ query: back }).url}
                        >
                            <ArrowLeft className="size-4" />
                            Kembali ke Penilaian
                        </Link>
                    </Button>
                </div>

                <div className="grid flex-1 gap-4 lg:grid-cols-[22rem_minmax(0,1fr)]">
                    <ul className="space-y-1.5 lg:max-h-[calc(100svh-13rem)] lg:overflow-y-auto">
                        {assessments.map((assessment) => {
                            const isSelected = assessment.id === selectedId;

                            return (
                                <li key={assessment.id}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSelectedId(assessment.id)
                                        }
                                        aria-pressed={isSelected}
                                        className={cn(
                                            'flex w-full items-start gap-2 rounded-md border border-border bg-card p-3 text-left transition-colors hover:border-primary/50 hover:bg-accent/40',
                                            isSelected &&
                                                'border-primary bg-primary/5 ring-1 ring-primary/30',
                                        )}
                                    >
                                        <ClipboardCheck
                                            className={cn(
                                                'mt-0.5 size-4 shrink-0',
                                                isSelected
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground',
                                            )}
                                        />
                                        <span className="min-w-0 space-y-1">
                                            <span className="block text-sm font-medium text-foreground">
                                                {assessment.project}
                                            </span>
                                            <span className="tabular block text-[11px] text-muted-foreground">
                                                {[
                                                    assessment.po_number ??
                                                        assessment.procurement_number,
                                                    assessment.po_date &&
                                                        formatDate(
                                                            assessment.po_date,
                                                        ),
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'}
                                            </span>
                                            <span className="flex flex-wrap items-center gap-1.5">
                                                {isComplete(assessment) ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400">
                                                        <CircleCheck className="size-3" />
                                                        Lengkap
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400">
                                                        <CircleDashed className="size-3" />
                                                        {assessment.scored}/
                                                        {assessment.total} aspek
                                                    </span>
                                                )}
                                                <span className="tabular text-[11px] font-semibold text-foreground">
                                                    Nilai{' '}
                                                    {assessment.overall_average ===
                                                    null
                                                        ? '—'
                                                        : assessment.overall_average
                                                              .toFixed(2)
                                                              .replace(
                                                                  '.',
                                                                  ',',
                                                              )}
                                                </span>
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
                                <div className="flex rounded-md border border-border p-0.5">
                                    {(
                                        [
                                            [
                                                'sertifikat',
                                                'Sertifikat (Akumulasi)',
                                            ],
                                            ['semua', 'Semua Lembar'],
                                        ] as const
                                    ).map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => setSheet(value)}
                                            aria-pressed={sheet === value}
                                            className={cn(
                                                'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                                sheet === value
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                                <div className="flex gap-2">
                                    <Button asChild size="sm" variant="ghost">
                                        <a
                                            href={pdfUrl(selected)}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink className="size-3.5" />
                                            Tab Baru
                                        </a>
                                    </Button>
                                    <Button asChild size="sm">
                                        <Link
                                            href={vendorAssessments.show(
                                                selected.id,
                                            )}
                                        >
                                            <Pencil className="size-3.5" />
                                            Buka &amp; Isi Nilai
                                        </Link>
                                    </Button>
                                </div>
                            </header>
                            <iframe
                                key={`${selected.id}-${sheet}`}
                                src={`${pdfUrl(selected)}#view=FitH`}
                                title={`${selected.project} — ${vendor.name}`}
                                className="w-full flex-1 border-0 bg-white"
                            />
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}

VendorFolder.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penilaian Penyedia', href: vendorAssessments.index() },
    ],
};
