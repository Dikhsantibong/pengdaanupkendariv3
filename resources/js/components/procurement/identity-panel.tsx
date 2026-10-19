import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { TargetDateField } from '@/components/procurement/planning-identity-fields';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatCurrency, formatDate } from '@/lib/format';
import type { ProcurementRow } from '@/types';

/** Remembered per viewer so the panel stays the way they left it. */
const STORAGE_KEY = 'procurement-identity-details-open';

type Field = {
    label: string;
    value: ReactNode;
    /** Spans the whole row, for long text such as an address. */
    wide?: boolean;
};

/**
 * The identity of a procurement, kept compact so the tabs below lead the
 * page: the figures people look for first in a dense grid, everything else
 * (partner details, reference numbers, notes) one click away.
 */
export function IdentityPanel({
    procurement,
    notes,
    editsInline,
    candidateCount = 0,
}: {
    procurement: ProcurementRow;
    notes: string | null;
    /** The planning PIC edits the target date in place. */
    editsInline: boolean;
    /** How many calon mitra are listed, shown until one is picked. */
    candidateCount?: number;
}) {
    const [open, setOpen] = useState(() => {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    });

    const toggle = (next: boolean) => {
        setOpen(next);

        try {
            window.localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
        } catch {
            // Storage may be unavailable; the panel still works.
        }
    };

    const date = (value: string | null) => (value ? formatDate(value) : '—');

    const primary: Field[] = [
        { label: 'Direksi Pekerjaan', value: procurement.work_director },
        { label: 'Unit Tujuan', value: procurement.target_unit },
        { label: 'Sumber Anggaran', value: procurement.budget_source ?? '—' },
        // Given by the executor with the contract number.
        { label: 'Kategori Kontrak', value: procurement.contract_type ?? '—' },
        {
            label: 'Target Selesai',
            value: editsInline ? (
                <TargetDateField
                    procurementId={procurement.id}
                    value={procurement.target_completion_date}
                />
            ) : (
                date(procurement.target_completion_date)
            ),
        },
        {
            label: 'Pelaksana (Mitra)',
            value:
                procurement.partner_name ??
                (candidateCount > 0
                    ? `${candidateCount} calon mitra, belum dipilih`
                    : '—'),
        },
        {
            label: 'Nilai (Sebelum Nego)',
            value: formatCurrency(procurement.hpe_value),
        },
        {
            label: 'Nilai Setelah Nego',
            value:
                procurement.value_after_negotiation === null
                    ? '—'
                    : formatCurrency(procurement.value_after_negotiation),
        },
    ];

    const partner: Field[] = [
        {
            label: 'Nama Direktur',
            value: procurement.partner_director_name ?? '—',
        },
        {
            label: 'Nomor Surat Penawaran',
            value: procurement.quotation_number ?? '—',
        },
        {
            label: 'Tanggal Surat Penawaran',
            value: date(procurement.quotation_date),
        },
        {
            label: 'Format Dokumen',
            value: procurement.work_category ?? '—',
        },
        {
            label: 'Alamat Perusahaan',
            value: procurement.partner_address ?? '—',
            wide: true,
        },
    ];

    const references: Field[] = [
        { label: 'Nomor PRK', value: procurement.prk_number ?? '—' },
        { label: 'Nomor COA', value: procurement.coa_number ?? '—' },
        { label: 'Nomor PR', value: procurement.pr_po_number ?? '—' },
        { label: 'Nomor SR', value: procurement.sr_number ?? '—' },
        { label: 'Nomor WO', value: procurement.wo_number ?? '—' },
        {
            label: 'Nota Dinas Usulan',
            value: joinDated(
                procurement.proposal_memo_number,
                procurement.proposal_memo_date,
            ),
        },
    ];

    return (
        <section className="rounded-md border border-border bg-card">
            <Collapsible open={open} onOpenChange={toggle}>
                <header className="flex items-center justify-between gap-3 border-b border-border px-4 py-2">
                    <h2 className="text-sm font-semibold text-foreground">
                        Identitas Pengadaan
                    </h2>
                    <CollapsibleTrigger className="inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-xs font-medium text-primary hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                        {open ? 'Sembunyikan detail' : 'Lihat detail lengkap'}
                        <ChevronDown
                            className={`size-3.5 transition-transform ${open ? 'rotate-180' : ''}`}
                        />
                    </CollapsibleTrigger>
                </header>

                <FieldGrid fields={primary} />

                <CollapsibleContent>
                    <div className="space-y-3 border-t border-border py-3">
                        <FieldGroup
                            title="Pelaksana (Mitra)"
                            fields={partner}
                        />
                        <FieldGroup
                            title="Usulan Pekerjaan"
                            fields={references}
                        />
                        {notes && (
                            <p className="px-4 text-sm text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    Catatan:{' '}
                                </span>
                                {notes}
                            </p>
                        )}
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </section>
    );
}

/** A number with its date, as "ND-021/USL/2026 · 13 Okt 2026". */
function joinDated(number: string | null, date: string | null): string {
    const parts = [number, date ? formatDate(date) : null].filter(Boolean);

    return parts.length === 0 ? '—' : parts.join(' · ');
}

function FieldGroup({ title, fields }: { title: string; fields: Field[] }) {
    return (
        <div>
            <p className="section-label px-4">{title}</p>
            <FieldGrid fields={fields} />
        </div>
    );
}

/** Label above value, several to a row, so many fit in little height. */
function FieldGrid({ fields }: { fields: Field[] }) {
    return (
        <dl className="grid grid-cols-2 gap-x-4 gap-y-2.5 px-4 py-3 sm:grid-cols-3 xl:grid-cols-4">
            {fields.map((field) => (
                <div
                    key={field.label}
                    className={`min-w-0 ${field.wide ? 'col-span-2 sm:col-span-3 xl:col-span-4' : ''}`}
                >
                    <dt className="text-[0.6875rem] tracking-wide text-muted-foreground uppercase">
                        {field.label}
                    </dt>
                    <dd
                        className={`tabular text-sm font-medium text-foreground ${
                            typeof field.value === 'string' && !field.wide
                                ? 'truncate'
                                : ''
                        }`}
                        title={
                            typeof field.value === 'string'
                                ? field.value
                                : undefined
                        }
                    >
                        {field.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
