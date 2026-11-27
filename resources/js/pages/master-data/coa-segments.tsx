import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type SegmentMeta = {
    value: string;
    label: string;
    shortLabel: string;
};

type CoaSegmentRecord = MasterRecord & {
    segment: string;
    segment_label: string;
    segment_short_label: string;
    code: string;
    name: string;
    description: string | null;
    sort_order: number;
};

export default function CoaSegments({
    records,
    segments,
}: {
    records: CoaSegmentRecord[];
    segments: SegmentMeta[];
}) {
    const [activeSegment, setActiveSegment] = useState<string>('all');
    const [search, setSearch] = useState('');

    const counts = useMemo(() => {
        const map: Record<string, number> = { all: records.length };

        for (const record of records) {
            map[record.segment] = (map[record.segment] ?? 0) + 1;
        }

        return map;
    }, [records]);

    const filteredRecords = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return records.filter((record) => {
            if (activeSegment !== 'all' && record.segment !== activeSegment) {
                return false;
            }

            if (needle === '') {
                return true;
            }

            return (
                record.code.toLowerCase().includes(needle) ||
                record.name.toLowerCase().includes(needle) ||
                record.segment_label.toLowerCase().includes(needle) ||
                (record.description ?? '').toLowerCase().includes(needle)
            );
        });
    }, [records, activeSegment, search]);

    const defaultSegment =
        activeSegment !== 'all' ? activeSegment : (segments[0]?.value ?? 'segment_1');

    return (
        <MasterDataPage<CoaSegmentRecord>
            title="Segmen COA"
            description="Kode dan pilihan tiap segmen penyusun nomor COA (Segment 1–7 dan Expense Element)."
            addLabel="Tambah Kode COA"
            records={filteredRecords}
            filtered={activeSegment !== 'all' || search.trim() !== ''}
            nameKey="name"
            toolbar={
                <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-3">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => setActiveSegment('all')}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium transition-colors',
                                activeSegment === 'all'
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-foreground',
                            )}
                        >
                            <span>Semua Segmen</span>
                            <span className="tabular opacity-80">
                                {counts.all ?? 0}
                            </span>
                        </button>
                        {segments.map((seg) => (
                            <button
                                key={seg.value}
                                type="button"
                                onClick={() => setActiveSegment(seg.value)}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium transition-colors',
                                    activeSegment === seg.value
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-foreground',
                                )}
                            >
                                <span>{seg.label}</span>
                                <span className="tabular opacity-80">
                                    {counts[seg.value] ?? 0}
                                </span>
                            </button>
                        ))}
                    </div>

                    <div className="relative max-w-sm">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari kode atau keterangan segmen..."
                            className="pl-8"
                        />
                    </div>
                </div>
            }
            columns={[
                {
                    key: 'segment_label',
                    label: 'Segmen',
                    render: (record) => (
                        <span className="inline-flex rounded bg-muted px-2 py-0.5 text-xs font-medium text-foreground">
                            {record.segment_label}
                        </span>
                    ),
                },
                {
                    key: 'code',
                    label: 'Kode',
                    className: 'font-mono font-semibold tabular',
                },
                {
                    key: 'name',
                    label: 'Keterangan',
                    className: 'font-medium',
                },
                {
                    key: 'description',
                    label: 'Catatan',
                },
                {
                    key: 'sort_order',
                    label: 'Urutan',
                    className: 'tabular',
                },
            ]}
            fields={[
                {
                    name: 'segment',
                    label: 'Segmen COA',
                    type: 'select',
                    required: true,
                    options: segments.map((seg) => ({
                        value: seg.value,
                        label: seg.label,
                    })),
                },
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: A, KD, 21, 372, 000, F102/JBJSLN',
                    hint: 'Disimpan dalam huruf kapital tanpa spasi.',
                },
                {
                    name: 'name',
                    label: 'Keterangan / Nama Pilihan',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: UPDK KENDARI atau PLTMH',
                },
                {
                    name: 'description',
                    label: 'Catatan Tambahan',
                    type: 'text',
                    placeholder: 'Opsional',
                },
                {
                    name: 'sort_order',
                    label: 'Urutan Tampil',
                    type: 'number',
                },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya opsi aktif yang muncul pada form Request COA.',
                },
            ]}
            defaults={{
                segment: defaultSegment,
                code: '',
                name: '',
                description: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.coaSegments.store().url}
            updateUrl={(record) =>
                masterData.coaSegments.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.coaSegments.destroy(record.id).url
            }
        />
    );
}

CoaSegments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Segmen COA', href: masterData.coaSegments.index() },
    ],
};
