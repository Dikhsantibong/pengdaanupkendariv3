import { usePage } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useState } from 'react';
import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
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
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption, ProcurementStage } from '@/types';

const ALL = 'all';

/** The filters of the list, kept in the address so a save keeps them. */
type Filters = {
    q: string;
    /** A stage, or "keuangan:penyerahan" / "keuangan:pembayaran". */
    tahap: string;
    format: string;
    kategori: string;
    sifat: string;
    isian: string;
    status: string;
};

const EMPTY: Filters = {
    q: '',
    tahap: ALL,
    format: ALL,
    kategori: ALL,
    sifat: ALL,
    isian: ALL,
    status: ALL,
};

/** Read the filters from the query string. */
function readFilters(query: string): Filters {
    const params = new URLSearchParams(query);
    const filters = { ...EMPTY };

    (Object.keys(EMPTY) as Array<keyof Filters>).forEach((key) => {
        const value = params.get(key);

        if (value !== null && value !== '') {
            filters[key] = value;
        }
    });

    return filters;
}

/** Put the filters in the address without a new visit. */
function writeFilters(filters: Filters): void {
    const params = new URLSearchParams(window.location.search);

    (Object.keys(EMPTY) as Array<keyof Filters>).forEach((key) => {
        if (filters[key] === EMPTY[key]) {
            params.delete(key);
        } else {
            params.set(key, filters[key]);
        }
    });

    const query = params.toString();

    window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${query ? `?${query}` : ''}`,
    );
}

/**
 * Whether an item passes the filters.
 *
 * Format, method and category show what a procurement with that criterion
 * goes through: the items not skipped by it, those for everyone included.
 */
function matches(record: ChecklistItem, filters: Filters): boolean {
    const search = filters.q.trim().toLowerCase();

    if (
        search !== '' &&
        ![record.name, record.description ?? '', ...record.document_types]
            .join(' ')
            .toLowerCase()
            .includes(search)
    ) {
        return false;
    }

    if (filters.tahap !== ALL) {
        const [stage, section] = filters.tahap.split(':');

        if (record.stage !== stage) {
            return false;
        }

        // Keuangan items without a tab show on the Pembayaran tab.
        const recordSection =
            record.section === 'none' ? 'pembayaran' : record.section;

        if (section !== undefined && recordSection !== section) {
            return false;
        }
    }

    if (
        filters.format !== ALL &&
        record.excluded_contract_number_format_ids.includes(
            Number(filters.format),
        )
    ) {
        return false;
    }

    if (
        filters.kategori !== ALL &&
        record.work_category !== 'none' &&
        record.work_category !== filters.kategori
    ) {
        return false;
    }

    if (
        filters.sifat !== ALL &&
        record.is_optional !== (filters.sifat === 'opsional')
    ) {
        return false;
    }

    if (filters.isian !== ALL && record.input_kind !== filters.isian) {
        return false;
    }

    if (
        filters.status !== ALL &&
        record.is_active !== (filters.status === 'aktif')
    ) {
        return false;
    }

    return true;
}

/** One labelled filter with an "all" choice. */
function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: EnumOption[];
    onChange: (value: string) => void;
}) {
    return (
        <div className="w-full space-y-1.5 sm:w-48">
            <Label className="section-label">{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Semua</SelectItem>
                    {options.map((option) => (
                        <SelectItem
                            key={String(option.value)}
                            value={String(option.value)}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

type ChecklistItem = MasterRecord & {
    stage: ProcurementStage;
    stage_label: string;
    section: string;
    section_label: string | null;
    work_category: string;
    work_category_label: string | null;
    name: string;
    description: string | null;
    is_optional: boolean;
    sort_order: number;
    excluded_contract_number_format_ids: number[];
    input_kind: string;
    input_label: string | null;
    /** The only contract format asked the input, or "none" for every one. */
    input_contract_number_format_id: string;
    document_type_ids: number[];
    alternative_document_type_ids: number[];
    document_types: string[];
};

export default function ChecklistItems({
    records,
    stages,
    sections,
    workCategories,
    inputKinds,
    contractNumberFormats,
    documentTypes,
}: {
    records: ChecklistItem[];
    stages: EnumOption[];
    sections: EnumOption[];
    workCategories: EnumOption[];
    inputKinds: EnumOption[];
    contractNumberFormats: EnumOption[];
    documentTypes: EnumOption[];
}) {
    const formatLabel = (id: number) =>
        contractNumberFormats.find((format) => Number(format.value) === id)
            ?.label;

    const { url } = usePage();
    const [filters, setFilters] = useState<Filters>(() =>
        readFilters(url.split('?')[1] ?? ''),
    );

    const update = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        setFilters(merged);
        writeFilters(merged);
    };

    const visible = records.filter((record) => matches(record, filters));
    const isFiltered = Object.keys(filters).some(
        (key) => filters[key as keyof Filters] !== EMPTY[key as keyof Filters],
    );

    const stageOptions: EnumOption[] = stages.flatMap((stage) =>
        stage.value === 'keuangan'
            ? [
                  { value: 'keuangan', label: `${stage.label} (semua)` },
                  ...sections
                      .filter((section) => section.value !== 'none')
                      .map((section) => ({
                          value: `keuangan:${section.value}`,
                          label: `${stage.label} — ${section.label}`,
                      })),
              ]
            : [stage],
    );

    const toolbar = (
        <div className="space-y-2">
            <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                <div className="min-w-64 flex-1 space-y-1.5">
                    <Label htmlFor="checklist-search" className="section-label">
                        Cari
                    </Label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="checklist-search"
                            value={filters.q}
                            onChange={(event) =>
                                update({ q: event.target.value })
                            }
                            placeholder="Nama item, keterangan, atau dokumen"
                            className="pl-9"
                        />
                    </div>
                </div>

                <FilterSelect
                    label="Tahap"
                    value={filters.tahap}
                    options={stageOptions}
                    onChange={(tahap) => update({ tahap })}
                />
                <FilterSelect
                    label="Berlaku untuk Format Kontrak"
                    value={filters.format}
                    options={contractNumberFormats}
                    onChange={(format) => update({ format })}
                />
                <FilterSelect
                    label="Kategori Pekerjaan"
                    value={filters.kategori}
                    options={workCategories.filter(
                        (category) => category.value !== 'none',
                    )}
                    onChange={(kategori) => update({ kategori })}
                />
                <FilterSelect
                    label="Sifat"
                    value={filters.sifat}
                    options={[
                        { value: 'wajib', label: 'Wajib' },
                        { value: 'opsional', label: 'Opsional' },
                    ]}
                    onChange={(sifat) => update({ sifat })}
                />
                <FilterSelect
                    label="Isian Tahapan"
                    value={filters.isian}
                    options={inputKinds}
                    onChange={(isian) => update({ isian })}
                />
                <FilterSelect
                    label="Status"
                    value={filters.status}
                    options={[
                        { value: 'aktif', label: 'Aktif' },
                        { value: 'nonaktif', label: 'Nonaktif' },
                    ]}
                    onChange={(status) => update({ status })}
                />

                {isFiltered && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => update(EMPTY)}
                    >
                        <X className="size-4" />
                        Reset
                    </Button>
                )}
            </div>

            <p className="text-xs text-muted-foreground">
                Menampilkan{' '}
                <span className="tabular font-medium text-foreground">
                    {visible.length}
                </span>{' '}
                dari {records.length} item, berurutan sesuai tahap dan urutan
                tampil.
                {(filters.format !== ALL || filters.kategori !== ALL) &&
                    ' Filter format dan kategori menampilkan item yang dilalui pengadaan dengan kriteria itu, termasuk item yang berlaku untuk semua.'}
            </p>
        </div>
    );

    return (
        <MasterDataPage<ChecklistItem>
            title="Item Checklist"
            description="Daftar dokumen dan tahapan yang harus dilengkapi pada tahap perencanaan, pelaksanaan, serta penyerahan dan pembayaran (Keuangan)."
            addLabel="Tambah Item"
            records={visible}
            filtered={isFiltered}
            toolbar={toolbar}
            nameKey="name"
            columns={[
                {
                    key: 'stage_label',
                    label: 'Tahap',
                    render: (record) =>
                        record.section_label
                            ? `${record.stage_label} — ${record.section_label}`
                            : record.stage_label,
                },
                {
                    key: 'work_category_label',
                    label: 'Kategori',
                    render: (record) => record.work_category_label ?? 'Semua',
                },
                { key: 'name', label: 'Item', className: 'font-medium' },
                { key: 'description', label: 'Keterangan' },
                {
                    key: 'is_optional',
                    label: 'Sifat',
                    render: (record) =>
                        record.is_optional ? 'Opsional' : 'Wajib',
                },
                {
                    key: 'document_types',
                    label: 'Dokumen Wajib',
                    render: (record) =>
                        record.document_types.length === 0
                            ? '—'
                            : record.document_types.join(', '),
                },
                {
                    key: 'input_label',
                    label: 'Isian',
                    render: (record) => record.input_label ?? '—',
                },
                {
                    key: 'excluded_contract_number_format_ids',
                    label: 'Dilewati Format Kontrak',
                    render: (record) =>
                        record.excluded_contract_number_format_ids.length === 0
                            ? '—'
                            : record.excluded_contract_number_format_ids
                                  .map(formatLabel)
                                  .filter(Boolean)
                                  .join(', '),
                },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'stage',
                    label: 'Tahap',
                    type: 'select',
                    options: stages,
                    placeholder: 'Pilih tahap',
                },
                {
                    name: 'section',
                    label: 'Tab Keuangan',
                    type: 'select',
                    options: sections,
                    hint: 'Hanya untuk tahap Keuangan: tampil di tab Penyerahan (kelengkapan penyerahan barang/pekerjaan) atau Pembayaran (kelengkapan permintaan pembayaran).',
                },
                {
                    name: 'work_category',
                    label: 'Kategori Pekerjaan',
                    type: 'select',
                    options: workCategories,
                    hint: 'Batasi item ini untuk pengadaan barang atau jasa saja. Pilih "Barang dan Jasa" bila berlaku untuk keduanya.',
                },
                {
                    name: 'name',
                    label: 'Nama Item',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: Nota Dinas Usulan',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Opsional',
                },
                {
                    name: 'is_optional',
                    label: 'Opsional',
                    type: 'switch',
                    hint: 'Item opsional tidak wajib dicentang sebelum pengajuan persetujuan.',
                },
                {
                    name: 'document_type_ids',
                    label: 'Dokumen yang Dihasilkan',
                    type: 'multiselect',
                    options: documentTypes,
                    hint: 'Setiap dokumen yang dicentang wajib diunggah sebelum tahapan ini dapat ditandai selesai. Dokumen bertanda "Hanya diunggah" di Jenis Dokumen cukup diunggah tanpa generate. Kosongkan bila tahapan ini hanya centang biasa.',
                },
                {
                    name: 'alternative_document_type_ids',
                    label: 'Dokumen Pilihan (cukup salah satu)',
                    type: 'multiselect',
                    options: documentTypes,
                    hint: 'Dokumen yang dicentang di sini adalah pilihan: cukup salah satu yang diunggah. Contoh: Jaminan Bank atau Surat Garansi.',
                },
                {
                    name: 'input_kind',
                    label: 'Isian Tahapan',
                    type: 'select',
                    options: inputKinds,
                    hint: 'Data yang wajib diisi pada tahapan ini sebelum dapat dicentang, mis. rentang waktu, masa garansi, atau rekening pelaksana.',
                },
                {
                    name: 'input_contract_number_format_id',
                    label: 'Isian Hanya untuk Format Kontrak',
                    type: 'select',
                    options: [
                        { value: 'none', label: 'Semua format' },
                        ...contractNumberFormats.map((format) => ({
                            value: String(format.value),
                            label: format.label,
                        })),
                    ],
                    hint: 'Untuk tahapan yang dipakai beberapa format: isian hanya diminta pada format ini, format lain tetap melalui tahapannya tanpa isian. Contoh: Nomor kontrak di tahapan Surat Pesanan hanya untuk SPPL (SPK/PJ mengisinya di tahapan Kontrak).',
                },
                {
                    name: 'excluded_contract_number_format_ids',
                    label: 'Dilewati oleh Format Kontrak',
                    type: 'multiselect',
                    options: contractNumberFormats,
                    hint: 'Centang format kontrak yang tidak melalui tahapan ini, misalnya SPPL (metode Surat Pesanan) tidak memakai Evaluasi Harga, HPE, UPB, RKS, Inisiasi SMART SCM, dan PR/RO. Metode pengadaan mengikuti format, jadi pengecualian cukup diatur di sini. Berlaku untuk pengadaan baru dan saat data pengadaan disimpan ulang.',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                { name: 'is_active', label: 'Aktif', type: 'switch' },
            ]}
            defaults={{
                stage: 'perencanaan',
                section: 'none',
                work_category: 'none',
                name: '',
                description: '',
                is_optional: false,
                document_type_ids: [],
                alternative_document_type_ids: [],
                input_kind: 'none',
                input_contract_number_format_id: 'none',
                excluded_contract_number_format_ids: [],
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.checklistItems.store().url}
            updateUrl={(record) =>
                masterData.checklistItems.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.checklistItems.destroy(record.id).url
            }
        />
    );
}

ChecklistItems.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Item Checklist', href: masterData.checklistItems.index() },
    ],
};
