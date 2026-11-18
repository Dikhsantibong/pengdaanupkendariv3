import { Head, router, useForm } from '@inertiajs/react';
import {
    ClipboardCheck,
    ClipboardList,
    Layers,
    Pencil,
    Plus,
    Power,
    Search,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FieldControl } from '@/components/master-data-page';
import type {
    MasterField,
    MasterFormValue,
    MasterFormValues,
    MasterRecord,
    RowDates,
} from '@/components/master-data-page';
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
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption } from '@/types';

type Aspect = MasterRecord & {
    code: string;
    name: string;
    preamble: string | null;
    indicators: string[];
    indicator_count: number;
    form_count: number;
    sort_order: number;
    weight: number;
};

type Sheet = MasterRecord & {
    code: string;
    name: string;
    assessor_title: string;
    assessor_name: string | null;
    assessor_options: string[];
    /** Beside each name: the last BASTP date it stays hidden on, or blank. */
    assessor_hidden_until: string[];
    description: string | null;
    aspect_ids: number[];
    aspect_names: string[];
    sort_order: number;
};

/** The fields of an aspect, as on its own screen before the merge. */
const ASPECT_FIELDS: MasterField[] = [
    {
        name: 'name',
        label: 'Nama Aspek',
        type: 'text',
        required: true,
        placeholder: 'Contoh: ASPEK INTEGRITAS',
        hint: 'Disimpan dalam huruf kapital seperti pada formulir resmi.',
    },
    {
        name: 'code',
        label: 'Kode',
        type: 'text',
        required: true,
        placeholder: 'Contoh: integritas',
        hint: 'Disimpan dalam bentuk slug, dipakai sebagai kunci acuan.',
    },
    {
        name: 'preamble',
        label: 'Kalimat Pembuka',
        type: 'textarea',
        placeholder:
            'Kalimat pengantar yang tercetak sebelum daftar indikator.',
    },
    {
        name: 'indicators',
        label: 'Indikator Penilaian',
        type: 'list',
        addLabel: 'Tambah Indikator',
        placeholder: 'Tulis satu indikator.',
        hint: 'Tercetak berurutan sebagai a, b, c pada formulir. Gunakan panah untuk mengubah urutan.',
    },
    {
        name: 'weight',
        label: 'Bobot (%)',
        type: 'number',
        hint: 'Persentase bobot aspek pada Sertifikat Akumulasi. Nilai = rata-rata level × bobot. Total bobot seluruh aspek aktif sebaiknya 100%.',
    },
    { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
    {
        name: 'is_active',
        label: 'Aktif',
        type: 'switch',
        hint: 'Hanya aspek aktif yang dapat dipilih pada lembar penilai dan muncul pada rekapitulasi.',
    },
];

const ASPECT_DEFAULTS: MasterFormValues = {
    name: '',
    code: '',
    preamble: '',
    indicators: [],
    weight: 0,
    sort_order: 0,
    is_active: true,
};

/** The fields of a sheet, as on its own screen before the merge. */
const sheetFields = (aspectOptions: EnumOption[]): MasterField[] => [
    {
        name: 'name',
        label: 'Nama Lembar',
        type: 'text',
        required: true,
        placeholder: 'Contoh: Direksi Pekerjaan',
    },
    {
        name: 'code',
        label: 'Kode',
        type: 'text',
        required: true,
        placeholder: 'Contoh: direksi-pekerjaan',
        hint: 'Disimpan dalam bentuk slug, dipakai sebagai nama berkas saat unduh semua dokumen.',
    },
    {
        name: 'assessor_title',
        label: 'Kedudukan Penilai',
        type: 'text',
        required: true,
        placeholder: 'Contoh: Asman Pemeliharaan',
        hint: 'Tercetak di atas garis tanda tangan.',
    },
    {
        name: 'aspect_ids',
        label: 'Aspek yang Dinilai',
        type: 'multiselect',
        options: aspectOptions,
        hint: 'Aspek yang dilepas tidak menghapus nilai yang sudah pernah diberikan pada penilaian lama.',
    },
    {
        name: 'assessor_options',
        label: 'Pilihan Nama Penilai',
        type: 'list',
        addLabel: 'Tambah Nama',
        placeholder: 'Contoh: MUSRIYADI',
        rowDate: {
            name: 'assessor_hidden_until',
            label: 'Tidak muncul s.d. BASTP',
        },
        hint: 'Kosongkan bila nama penilai diketik bebas. Bila diisi, penilai wajib memilih dari daftar ini, termasuk pada halaman tanda tangan WhatsApp. Isi tanggal di samping nama yang baru menjabat: pada penilaian dengan Tgl BASTP sampai tanggal itu, namanya tidak ditawarkan. Kosongkan bila selalu muncul.',
    },
    {
        name: 'assessor_name',
        label: 'Nama Penilai Terakhir',
        type: 'text',
        placeholder: 'Terisi otomatis setelah lembar ditandatangani',
    },
    {
        name: 'description',
        label: 'Keterangan',
        type: 'text',
        placeholder: 'Catatan singkat mengenai lembar ini.',
    },
    { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
    {
        name: 'is_active',
        label: 'Aktif',
        type: 'switch',
        hint: 'Hanya lembar aktif yang dibuatkan baris nilai pada penilaian baru dan ikut terunduh.',
    },
];

const SHEET_DEFAULTS: MasterFormValues = {
    name: '',
    code: '',
    assessor_title: '',
    assessor_name: '',
    assessor_options: [],
    assessor_hidden_until: [],
    description: '',
    aspect_ids: [],
    sort_order: 0,
    is_active: true,
};

/** Every aspect, rather than one sheet's. */
const ALL_ASPECTS = 'semua-aspek';

const LETTERS = 'abcdefghijklmnopqrstuvwxyz';

type Editing =
    | { kind: 'aspect'; record: Aspect | null }
    | { kind: 'sheet'; record: Sheet | null }
    | null;

/**
 * Data Master · Aspek & Lembar Penilai: the sheets each assessor fills in and
 * the aspects they rate, on one screen. An aspect is edited where it is read,
 * under the sheet that rates it.
 */
export default function Assessments({
    records: sheets,
    aspects,
    aspectOptions,
}: {
    records: Sheet[];
    aspects: Aspect[];
    aspectOptions: EnumOption[];
}) {
    const [selected, setSelected] = useState<number | typeof ALL_ASPECTS>(
        sheets[0]?.id ?? ALL_ASPECTS,
    );
    const [search, setSearch] = useState('');
    const [editing, setEditing] = useState<Editing>(null);

    const visibleSheets = useMemo(() => {
        const term = search.trim().toLowerCase();

        return term === ''
            ? sheets
            : sheets.filter(
                  (sheet) =>
                      sheet.name.toLowerCase().includes(term) ||
                      sheet.assessor_title.toLowerCase().includes(term) ||
                      sheet.aspect_names.some((name) =>
                          name.toLowerCase().includes(term),
                      ),
              );
    }, [sheets, search]);

    const sheet =
        selected === ALL_ASPECTS
            ? null
            : (sheets.find((candidate) => candidate.id === selected) ?? null);

    const activeWeight = aspects
        .filter((aspect) => aspect.is_active)
        .reduce((total, aspect) => total + Number(aspect.weight), 0);

    const sheetsRating = (aspect: Aspect) =>
        sheets.filter((candidate) => candidate.aspect_ids.includes(aspect.id));

    return (
        <>
            <Head title="Aspek & Lembar Penilai" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Data Master"
                    title="Aspek & Lembar Penilai"
                    description="Kelola lembar dan aspek penilaian penyedia."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    setEditing({ kind: 'aspect', record: null })
                                }
                            >
                                <Plus className="size-4" />
                                Aspek
                            </Button>
                            <Button
                                onClick={() =>
                                    setEditing({ kind: 'sheet', record: null })
                                }
                            >
                                <Plus className="size-4" />
                                Lembar Penilai
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                    <aside className="flex min-h-0 flex-col gap-3 rounded-md border border-border bg-card p-3 lg:sticky lg:top-4 lg:max-h-[calc(100svh-7rem)]">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari lembar, penilai, aspek…"
                                className="pl-9"
                                aria-label="Cari lembar penilai"
                            />
                        </div>

                        <div className="-mx-1 min-h-0 flex-1 overflow-x-hidden overflow-y-auto px-1">
                            <p className="section-label px-2 pb-1">
                                Lembar Penilai ({sheets.length})
                            </p>
                            {visibleSheets.length === 0 ? (
                                <p className="px-2 py-4 text-sm text-muted-foreground">
                                    Tidak ada lembar yang cocok.
                                </p>
                            ) : (
                                <ul className="grid grid-cols-[minmax(0,1fr)] gap-0.5">
                                    {visibleSheets.map((candidate) => (
                                        <li
                                            key={candidate.id}
                                            className="min-w-0"
                                        >
                                            <ListButton
                                                active={
                                                    selected === candidate.id
                                                }
                                                icon={
                                                    <ClipboardCheck className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                                }
                                                title={candidate.name}
                                                inactive={!candidate.is_active}
                                                subtitle={`${candidate.assessor_title} · ${candidate.aspect_ids.length} aspek`}
                                                onClick={() =>
                                                    setSelected(candidate.id)
                                                }
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <p className="section-label mt-3 px-2 pb-1">
                                Aspek Penilaian
                            </p>
                            <ListButton
                                active={selected === ALL_ASPECTS}
                                icon={
                                    <Layers className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                }
                                title="Semua Aspek"
                                subtitle={`${aspects.length} aspek · bobot aktif ${activeWeight}%`}
                                onClick={() => setSelected(ALL_ASPECTS)}
                            />
                        </div>
                    </aside>

                    <div className="flex min-w-0 flex-col gap-4">
                        {sheet !== null ? (
                            <>
                                <SheetCard
                                    sheet={sheet}
                                    onEdit={() =>
                                        setEditing({
                                            kind: 'sheet',
                                            record: sheet,
                                        })
                                    }
                                />
                                <section className="rounded-md border border-border bg-card">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <ClipboardList className="size-4 text-muted-foreground" />
                                            <h3 className="text-sm font-semibold">
                                                Aspek yang Dinilai pada Lembar
                                                Ini
                                            </h3>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setEditing({
                                                    kind: 'sheet',
                                                    record: sheet,
                                                })
                                            }
                                        >
                                            Pilih Aspek Lembar
                                        </Button>
                                    </div>
                                    {sheet.aspect_ids.length === 0 ? (
                                        <p className="px-4 py-4 text-sm text-muted-foreground">
                                            Lembar ini belum menilai aspek apa
                                            pun. Klik Pilih Aspek Lembar.
                                        </p>
                                    ) : (
                                        <ol className="divide-y divide-border">
                                            {aspects
                                                .filter((aspect) =>
                                                    sheet.aspect_ids.includes(
                                                        aspect.id,
                                                    ),
                                                )
                                                .map((aspect, index) => (
                                                    <AspectItem
                                                        key={aspect.id}
                                                        number={index + 1}
                                                        aspect={aspect}
                                                        sheets={sheetsRating(
                                                            aspect,
                                                        )}
                                                        onEdit={() =>
                                                            setEditing({
                                                                kind: 'aspect',
                                                                record: aspect,
                                                            })
                                                        }
                                                    />
                                                ))}
                                        </ol>
                                    )}
                                </section>
                            </>
                        ) : (
                            <section className="rounded-md border border-border bg-card">
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                                    <div className="min-w-0">
                                        <p className="section-label">
                                            Aspek Penilaian
                                        </p>
                                        <h2 className="mt-0.5 text-lg font-semibold tracking-tight">
                                            Semua Aspek
                                        </h2>
                                        <p
                                            className={cn(
                                                'text-xs',
                                                activeWeight === 100
                                                    ? 'text-muted-foreground'
                                                    : 'text-status-batal',
                                            )}
                                        >
                                            Total bobot aspek aktif{' '}
                                            {activeWeight}%
                                            {activeWeight !== 100 &&
                                                ' (sebaiknya 100%)'}
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setEditing({
                                                kind: 'aspect',
                                                record: null,
                                            })
                                        }
                                    >
                                        <Plus className="size-3.5" />
                                        Tambah Aspek
                                    </Button>
                                </div>
                                {aspects.length === 0 ? (
                                    <EmptyState
                                        icon={Layers}
                                        title="Belum ada aspek"
                                        description="Tambahkan aspek penilaian pertama."
                                    />
                                ) : (
                                    <ol className="divide-y divide-border">
                                        {aspects.map((aspect, index) => (
                                            <AspectItem
                                                key={aspect.id}
                                                number={index + 1}
                                                aspect={aspect}
                                                sheets={sheetsRating(aspect)}
                                                onEdit={() =>
                                                    setEditing({
                                                        kind: 'aspect',
                                                        record: aspect,
                                                    })
                                                }
                                            />
                                        ))}
                                    </ol>
                                )}
                            </section>
                        )}
                    </div>
                </div>
            </div>

            {editing?.kind === 'aspect' && (
                <RecordDialog<Aspect>
                    key={`aspect-${editing.record?.id ?? 'new'}`}
                    title={
                        editing.record === null
                            ? 'Tambah Aspek'
                            : `Ubah Aspek ${editing.record.name}`
                    }
                    note={
                        editing.record !== null &&
                        sheetsRating(editing.record).length > 1
                            ? `Aspek ini dinilai pada ${sheetsRating(editing.record).length} lembar; perubahan berlaku di semua lembar tersebut.`
                            : undefined
                    }
                    record={editing.record}
                    fields={ASPECT_FIELDS}
                    defaults={ASPECT_DEFAULTS}
                    storeUrl={masterData.assessmentAspects.store().url}
                    updateUrl={(record) =>
                        masterData.assessmentAspects.update(record.id).url
                    }
                    onClose={() => setEditing(null)}
                />
            )}
            {editing?.kind === 'sheet' && (
                <RecordDialog<Sheet>
                    key={`sheet-${editing.record?.id ?? 'new'}`}
                    title={
                        editing.record === null
                            ? 'Tambah Lembar Penilai'
                            : `Ubah Lembar ${editing.record.name}`
                    }
                    record={editing.record}
                    fields={sheetFields(aspectOptions)}
                    defaults={SHEET_DEFAULTS}
                    storeUrl={masterData.assessmentForms.store().url}
                    updateUrl={(record) =>
                        masterData.assessmentForms.update(record.id).url
                    }
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function ListButton({
    active,
    icon,
    title,
    subtitle,
    inactive = false,
    onClick,
}: {
    active: boolean;
    icon: ReactNode;
    title: string;
    subtitle: string;
    inactive?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-left text-sm',
                active ? 'bg-primary/10 text-foreground' : 'hover:bg-muted',
            )}
        >
            {icon}
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block truncate font-medium',
                        inactive && 'text-muted-foreground line-through',
                    )}
                >
                    {title}
                </span>
                <span className="block truncate text-[11px] text-muted-foreground">
                    {subtitle}
                </span>
            </span>
        </button>
    );
}

function SheetCard({ sheet, onEdit }: { sheet: Sheet; onEdit: () => void }) {
    return (
        <section className="rounded-md border border-border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="section-label">Lembar Penilai</p>
                    <h2 className="mt-1 text-lg font-semibold tracking-tight">
                        {sheet.name}
                    </h2>
                    {sheet.description && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {sheet.description}
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="outline" onClick={onEdit}>
                        <Pencil className="size-3.5" />
                        Ubah Lembar
                    </Button>
                    {sheet.is_active && (
                        <DeactivateButton
                            label={sheet.name}
                            url={
                                masterData.assessmentForms.destroy(sheet.id).url
                            }
                        />
                    )}
                </div>
            </div>

            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <Fact label="Kode">
                    <span className="tabular">{sheet.code}</span>
                </Fact>
                <Fact label="Kedudukan Penilai">{sheet.assessor_title}</Fact>
                <Fact label="Nama Penilai Terakhir">
                    {sheet.assessor_name ?? '—'}
                </Fact>
                <Fact label="Status">
                    <StatusBadge active={sheet.is_active} />
                </Fact>
                <Fact label="Dipakai">
                    <span className="tabular">
                        {sheet.usage_count ?? 0} nilai
                    </span>
                </Fact>
                <Fact label="Urutan Tampil">
                    <span className="tabular">{sheet.sort_order}</span>
                </Fact>
                <div className="sm:col-span-2">
                    <Fact label="Pilihan Nama Penilai">
                        {sheet.assessor_options.length === 0 ? (
                            'Diketik bebas'
                        ) : (
                            <ul className="flex flex-wrap gap-1.5">
                                {sheet.assessor_options.map((name, index) => (
                                    <li
                                        key={`${name}-${index}`}
                                        className="inline-flex items-center gap-1 rounded-sm bg-muted px-1.5 py-0.5 text-xs"
                                    >
                                        <UserRound className="size-3 text-muted-foreground" />
                                        {name}
                                        {sheet.assessor_hidden_until[index] && (
                                            <span className="text-muted-foreground">
                                                · s.d. BASTP{' '}
                                                {
                                                    sheet.assessor_hidden_until[
                                                        index
                                                    ]
                                                }
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Fact>
                </div>
            </dl>
        </section>
    );
}

function AspectItem({
    number,
    aspect,
    sheets,
    onEdit,
}: {
    number: number;
    aspect: Aspect;
    sheets: Sheet[];
    onEdit: () => void;
}) {
    return (
        <li className="px-4 py-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p
                        className={cn(
                            'font-semibold',
                            !aspect.is_active &&
                                'text-muted-foreground line-through',
                        )}
                    >
                        {number}. {aspect.name}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Bobot {aspect.weight}% · {aspect.indicator_count}{' '}
                        indikator · dinilai pada {sheets.length} lembar
                        {sheets.length > 0 &&
                            ` (${sheets.map((rated) => rated.name).join(', ')})`}
                        {!aspect.is_active && ' · Nonaktif'}
                    </p>
                </div>
                <div className="flex shrink-0 flex-wrap gap-1">
                    <Button size="sm" variant="ghost" onClick={onEdit}>
                        <Pencil className="size-3.5" />
                        Ubah Aspek
                    </Button>
                    {aspect.is_active && (
                        <DeactivateButton
                            label={aspect.name}
                            url={
                                masterData.assessmentAspects.destroy(aspect.id)
                                    .url
                            }
                        />
                    )}
                </div>
            </div>
            {aspect.preamble && (
                <p className="mt-2 text-sm text-muted-foreground">
                    {aspect.preamble}
                </p>
            )}
            {aspect.indicators.length > 0 && (
                <ol className="mt-2 grid gap-1 text-sm">
                    {aspect.indicators.map((indicator, index) => (
                        <li key={index} className="flex gap-2">
                            <span className="w-4 shrink-0 text-muted-foreground">
                                {LETTERS[index] ?? index + 1}.
                            </span>
                            <span>{indicator}</span>
                        </li>
                    ))}
                </ol>
            )}
        </li>
    );
}

function DeactivateButton({ label, url }: { label: string; url: string }) {
    return (
        <Button
            size="sm"
            variant="ghost"
            className="text-destructive hover:text-destructive"
            onClick={() => {
                if (
                    window.confirm(
                        `Nonaktifkan "${label}"? Data penilaian lama tetap utuh.`,
                    )
                ) {
                    router.delete(url, { preserveScroll: true });
                }
            }}
        >
            <Power className="size-3.5" />
            Nonaktifkan
        </Button>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <span
            className={
                active
                    ? 'inline-flex rounded-sm bg-status-selesai-surface px-2 py-0.5 text-xs font-medium text-status-selesai'
                    : 'inline-flex rounded-sm bg-status-pending-surface px-2 py-0.5 text-xs font-medium text-status-pending'
            }
        >
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
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

/**
 * Add or change one aspect or sheet, with the same fields and the same save
 * as its screen had before the two were put together.
 */
function RecordDialog<T extends MasterRecord>({
    title,
    note,
    record,
    fields,
    defaults,
    storeUrl,
    updateUrl,
    onClose,
}: {
    title: string;
    note?: string;
    record: T | null;
    fields: MasterField[];
    defaults: MasterFormValues;
    storeUrl: string;
    updateUrl: (record: T) => string;
    onClose: () => void;
}) {
    const form = useForm<MasterFormValues>(() => {
        if (record === null) {
            return { ...defaults };
        }

        const values: MasterFormValues = {};
        Object.keys(defaults).forEach((key) => {
            values[key] = (record[key] as MasterFormValue) ?? defaults[key];
        });

        return values;
    });

    // The dates a list field keeps beside its rows, in their own field.
    const rowDatesOf = (field: MasterField): RowDates | undefined =>
        field.rowDate === undefined
            ? undefined
            : {
                  value: form.data[field.rowDate.name],
                  onChange: (value) => form.setData(field.rowDate!.name, value),
              };

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };

        if (record === null) {
            form.post(storeUrl, options);
        } else {
            form.put(updateUrl(record), options);
        }
    };

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {note ??
                            'Perubahan berlaku langsung tanpa perlu deploy ulang aplikasi.'}
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="assessment-record-form"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                    className="grid grid-cols-1 items-start gap-4 md:grid-cols-2"
                >
                    {fields.map((field) => (
                        <div
                            key={field.name}
                            className={
                                field.type === 'list' ||
                                field.type === 'multiselect' ||
                                field.type === 'textarea'
                                    ? 'md:col-span-2'
                                    : ''
                            }
                        >
                            <FieldControl
                                field={field}
                                value={form.data[field.name]}
                                error={
                                    form.errors[
                                        field.name as keyof typeof form.errors
                                    ] as string | undefined
                                }
                                onChange={(value) =>
                                    form.setData(field.name, value)
                                }
                                rowDates={rowDatesOf(field)}
                            />
                        </div>
                    ))}
                </form>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="assessment-record-form"
                        disabled={form.processing}
                    >
                        Simpan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

Assessments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Aspek & Lembar Penilai',
            href: masterData.assessmentForms.index(),
        },
    ],
};
