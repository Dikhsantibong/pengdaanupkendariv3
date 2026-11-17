import { Head, router, useForm } from '@inertiajs/react';
import { Database, Pencil, Plus, Power, RotateCcw, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FieldControl } from '@/components/master-data-page';
import type {
    MasterField,
    MasterFormValue,
    MasterFormValues,
    MasterRecord,
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption } from '@/types';

type UnitManager = MasterRecord & {
    name: string;
    position: string;
    level: string;
    field: string | null;
    target_unit_ids: number[];
    target_unit_id: string | null;
    unit_ids?: string[];
    unit_names: string[];
    description: string | null;
    sort_order: number;
};

type WorkDirector = MasterRecord & {
    name: string;
    description: string | null;
    sort_order: number;
};

type OfficialTab = 'officials' | 'directors';

const ALL = '__all__';
const PER_PAGE = 10;

/** Ranks that hold several units, and the one that holds a single unit. */
const SEVERAL_UNITS = ['manager_ul', 'tl'];
const ONE_UNIT = 'tl_ophar';

const OFFICIAL_DEFAULTS: MasterFormValues = {
    name: '',
    level: 'manager',
    field: null,
    target_unit_ids: [],
    target_unit_id: null,
    description: '',
    sort_order: 0,
    is_active: true,
};

const DIRECTOR_FIELDS: MasterField[] = [
    {
        name: 'name',
        label: 'Nama Jabatan',
        type: 'text',
        required: true,
        placeholder: 'Contoh: Asman Pemeliharaan',
    },
    {
        name: 'description',
        label: 'Keterangan',
        type: 'text',
        placeholder: 'Opsional',
    },
    { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
    {
        name: 'is_active',
        label: 'Aktif',
        type: 'switch',
        hint: 'Hanya data aktif yang muncul pada dropdown pengadaan.',
    },
];

const DIRECTOR_DEFAULTS: MasterFormValues = {
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
};

type EditingModal =
    | { kind: 'official'; record: UnitManager | null }
    | { kind: 'director'; record: WorkDirector | null }
    | null;

export default function UnitManagers({
    records,
    directors = [],
    levelOptions,
    fieldOptions,
    unitOptions,
    initialTab = 'officials',
}: {
    records: UnitManager[];
    directors?: WorkDirector[];
    levelOptions: EnumOption[];
    fieldOptions: EnumOption[];
    unitOptions: EnumOption[];
    initialTab?: OfficialTab;
}) {
    const [tab, setTab] = useState<OfficialTab>(initialTab);
    const [editing, setEditing] = useState<EditingModal>(null);

    // Search, filters & page for Tab 1: Pejabat Unit & Layanan.
    const [officialSearch, setOfficialSearch] = useState('');
    const [levelFilter, setLevelFilter] = useState<string>(ALL);
    const [unitFilter, setUnitFilter] = useState<string>(ALL);
    const [officialStatus, setOfficialStatus] = useState<string>(ALL);
    const [officialPage, setOfficialPage] = useState(1);

    // Search, filter & page for Tab 2: Direksi Pekerjaan.
    const [directorSearch, setDirectorSearch] = useState('');
    const [directorStatus, setDirectorStatus] = useState<string>(ALL);
    const [directorPage, setDirectorPage] = useState(1);

    const officialFields: MasterField[] = [
        {
            name: 'name',
            label: 'Nama Lengkap & Gelar',
            type: 'text',
            required: true,
            placeholder: 'Contoh: MUHAMMAD RUSLI',
        },
        {
            name: 'level',
            label: 'Jabatan',
            type: 'select',
            options: levelOptions,
            placeholder: 'Pilih jabatan',
        },
        {
            name: 'field',
            label: 'Bidang',
            type: 'select',
            options: fieldOptions,
            placeholder: 'Pilih bidang',
            hint: 'Jabatan tertulis "TL" diikuti bidangnya, mis. TL PEMELIHARAAN.',
            visible: (values) => values.level === 'tl',
        },
        {
            name: 'target_unit_ids',
            label: 'Unit yang Dipegang',
            type: 'multiselect',
            options: unitOptions,
            hint: 'Diambil dari Data Master Unit Tujuan.',
            visible: (values) => SEVERAL_UNITS.includes(String(values.level)),
        },
        {
            name: 'target_unit_id',
            label: 'Unit yang Dipegang',
            type: 'select',
            options: unitOptions,
            placeholder: 'Pilih satu unit',
            hint: 'Jabatan tertulis "TL OPHAR" diikuti nama unitnya.',
            visible: (values) => values.level === ONE_UNIT,
        },
        {
            name: 'description',
            label: 'Keterangan',
            type: 'text',
            placeholder: 'Contoh: ULPLTD WUA WUA',
        },
        { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
        {
            name: 'is_active',
            label: 'Aktif',
            type: 'switch',
            hint: 'Manager aktif dengan urutan terkecil digunakan sebagai nama manager saat mencetak template dokumen.',
        },
    ];

    const filteredOfficials = useMemo(() => {
        const term = officialSearch.trim().toLowerCase();

        return records.filter((record) => {
            if (levelFilter !== ALL && record.level !== levelFilter) {
                return false;
            }

            if (unitFilter !== ALL) {
                const holdsUnit = (record.unit_ids ?? []).includes(unitFilter);

                if (!holdsUnit) {
                    return false;
                }
            }

            if (officialStatus === 'aktif' && !record.is_active) {
                return false;
            }

            if (officialStatus === 'nonaktif' && record.is_active) {
                return false;
            }

            if (term === '') {
                return true;
            }

            return (
                record.name.toLowerCase().includes(term) ||
                record.position.toLowerCase().includes(term) ||
                record.unit_names.some((u) => u.toLowerCase().includes(term)) ||
                (record.description ?? '').toLowerCase().includes(term)
            );
        });
    }, [records, officialSearch, levelFilter, unitFilter, officialStatus]);

    const officialTotalPages = Math.max(
        1,
        Math.ceil(filteredOfficials.length / PER_PAGE),
    );
    const safeOfficialPage = Math.min(officialPage, officialTotalPages);
    const pagedOfficials = filteredOfficials.slice(
        (safeOfficialPage - 1) * PER_PAGE,
        safeOfficialPage * PER_PAGE,
    );

    const hasOfficialFilter =
        officialSearch.trim() !== '' ||
        levelFilter !== ALL ||
        unitFilter !== ALL ||
        officialStatus !== ALL;

    const resetOfficialFilters = () => {
        setOfficialSearch('');
        setLevelFilter(ALL);
        setUnitFilter(ALL);
        setOfficialStatus(ALL);
        setOfficialPage(1);
    };

    const filteredDirectors = useMemo(() => {
        const term = directorSearch.trim().toLowerCase();

        return directors.filter((record) => {
            if (directorStatus === 'aktif' && !record.is_active) {
                return false;
            }

            if (directorStatus === 'nonaktif' && record.is_active) {
                return false;
            }

            if (term === '') {
                return true;
            }

            return (
                record.name.toLowerCase().includes(term) ||
                (record.description ?? '').toLowerCase().includes(term)
            );
        });
    }, [directors, directorSearch, directorStatus]);

    const directorTotalPages = Math.max(
        1,
        Math.ceil(filteredDirectors.length / PER_PAGE),
    );
    const safeDirectorPage = Math.min(directorPage, directorTotalPages);
    const pagedDirectors = filteredDirectors.slice(
        (safeDirectorPage - 1) * PER_PAGE,
        safeDirectorPage * PER_PAGE,
    );

    const hasDirectorFilter =
        directorSearch.trim() !== '' || directorStatus !== ALL;

    const resetDirectorFilters = () => {
        setDirectorSearch('');
        setDirectorStatus(ALL);
        setDirectorPage(1);
    };

    return (
        <>
            <Head title="Pejabat Unit & Direksi" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Data Master"
                    title="Pejabat Unit & Direksi"
                    description="Pengelolaan pejabat UP Kendari & Unit Layanan (Manager UP, Manager UL, TL Pemeliharaan, TL Operasi, TL OPHAR, TL K3L) serta Direksi Pekerjaan dalam satu menu."
                    actions={
                        tab === 'directors' ? (
                            <Button
                                onClick={() =>
                                    setEditing({
                                        kind: 'director',
                                        record: null,
                                    })
                                }
                            >
                                <Plus className="size-4" />
                                Tambah Direksi
                            </Button>
                        ) : (
                            <Button
                                onClick={() =>
                                    setEditing({
                                        kind: 'official',
                                        record: null,
                                    })
                                }
                            >
                                <Plus className="size-4" />
                                Tambah Pejabat
                            </Button>
                        )
                    }
                />

                <Tabs
                    defaultValue={tab}
                    value={tab}
                    onValueChange={(next) => setTab(next as OfficialTab)}
                    className="flex flex-col gap-4"
                >
                    <TabsList>
                        <TabsTrigger value="officials">
                            Pejabat Unit &amp; Layanan ({records.length})
                        </TabsTrigger>
                        <TabsTrigger value="directors">
                            Direksi Pekerjaan ({directors.length})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="officials" className="mt-0 space-y-3">
                        <div className="grid gap-3 rounded-md border border-border bg-card p-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_13rem_13rem_10rem_auto] lg:items-end">
                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="official-search"
                                    className="section-label"
                                >
                                    Cari
                                </Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="official-search"
                                        value={officialSearch}
                                        onChange={(event) => {
                                            setOfficialSearch(
                                                event.target.value,
                                            );
                                            setOfficialPage(1);
                                        }}
                                        placeholder="Cari nama, jabatan, unit, keterangan…"
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="official-level-filter"
                                    className="section-label"
                                >
                                    Jabatan
                                </Label>
                                <Select
                                    value={levelFilter}
                                    onValueChange={(next) => {
                                        setLevelFilter(next);
                                        setOfficialPage(1);
                                    }}
                                >
                                    <SelectTrigger
                                        id="official-level-filter"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            Semua Jabatan
                                        </SelectItem>
                                        {levelOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="official-unit-filter"
                                    className="section-label"
                                >
                                    Unit
                                </Label>
                                <Select
                                    value={unitFilter}
                                    onValueChange={(next) => {
                                        setUnitFilter(next);
                                        setOfficialPage(1);
                                    }}
                                >
                                    <SelectTrigger
                                        id="official-unit-filter"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            Semua Unit
                                        </SelectItem>
                                        {unitOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="official-status-filter"
                                    className="section-label"
                                >
                                    Status
                                </Label>
                                <Select
                                    value={officialStatus}
                                    onValueChange={(next) => {
                                        setOfficialStatus(next);
                                        setOfficialPage(1);
                                    }}
                                >
                                    <SelectTrigger
                                        id="official-status-filter"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            Semua Status
                                        </SelectItem>
                                        <SelectItem value="aktif">
                                            Aktif
                                        </SelectItem>
                                        <SelectItem value="nonaktif">
                                            Nonaktif
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {hasOfficialFilter && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={resetOfficialFilters}
                                    className="justify-self-start"
                                >
                                    <RotateCcw className="size-3.5" />
                                    Reset
                                </Button>
                            )}
                        </div>

                        {filteredOfficials.length === 0 ? (
                            <div className="rounded-md border border-border bg-card">
                                <EmptyState
                                    icon={Database}
                                    title={
                                        hasOfficialFilter
                                            ? 'Tidak ada pejabat yang cocok'
                                            : 'Belum ada data pejabat'
                                    }
                                    description={
                                        hasOfficialFilter
                                            ? 'Ubah atau reset filter untuk melihat data lainnya.'
                                            : 'Tambahkan data pejabat pertama agar dapat dipakai pada dokumen pengadaan.'
                                    }
                                    action={
                                        hasOfficialFilter ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={resetOfficialFilters}
                                            >
                                                Reset Filter
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    setEditing({
                                                        kind: 'official',
                                                        record: null,
                                                    })
                                                }
                                            >
                                                Tambah Pejabat
                                            </Button>
                                        )
                                    }
                                />
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-md border border-border bg-card">
                                <Table>
                                    <TableHeader className="bg-muted/60">
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead>Nama Pejabat</TableHead>
                                            <TableHead>Jabatan</TableHead>
                                            <TableHead>Unit</TableHead>
                                            <TableHead>Keterangan</TableHead>
                                            <TableHead>Urutan</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Aksi
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {pagedOfficials.map((record) => (
                                            <TableRow key={record.id}>
                                                <TableCell className="font-medium">
                                                    {record.name}
                                                </TableCell>
                                                <TableCell>
                                                    {record.position}
                                                </TableCell>
                                                <TableCell>
                                                    {record.unit_names
                                                        .length === 0
                                                        ? '—'
                                                        : record.unit_names.join(
                                                              ', ',
                                                          )}
                                                </TableCell>
                                                <TableCell>
                                                    {record.description || '—'}
                                                </TableCell>
                                                <TableCell className="tabular">
                                                    {record.sort_order}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusPill
                                                        active={
                                                            record.is_active
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right whitespace-nowrap">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setEditing({
                                                                kind: 'official',
                                                                record,
                                                            })
                                                        }
                                                    >
                                                        <Pencil className="size-3.5" />
                                                        Ubah
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    `Nonaktifkan "${record.name}"? Data pengadaan lama tetap utuh.`,
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    masterData.unitManagers.destroy(
                                                                        record.id,
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Power className="size-3.5" />
                                                        Nonaktifkan
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <ClientPagination
                                    page={safeOfficialPage}
                                    totalPages={officialTotalPages}
                                    total={filteredOfficials.length}
                                    perPage={PER_PAGE}
                                    onPageChange={setOfficialPage}
                                />
                            </div>
                        )}
                    </TabsContent>

                    <TabsContent value="directors" className="mt-0 space-y-3">
                        <div className="grid gap-3 rounded-md border border-border bg-card p-3 sm:grid-cols-[minmax(0,1fr)_11rem_auto] sm:items-end">
                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="director-search"
                                    className="section-label"
                                >
                                    Cari
                                </Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="director-search"
                                        value={directorSearch}
                                        onChange={(event) => {
                                            setDirectorSearch(
                                                event.target.value,
                                            );
                                            setDirectorPage(1);
                                        }}
                                        placeholder="Cari nama jabatan direksi atau keterangan…"
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="director-status-filter"
                                    className="section-label"
                                >
                                    Status
                                </Label>
                                <Select
                                    value={directorStatus}
                                    onValueChange={(next) => {
                                        setDirectorStatus(next);
                                        setDirectorPage(1);
                                    }}
                                >
                                    <SelectTrigger
                                        id="director-status-filter"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            Semua Status
                                        </SelectItem>
                                        <SelectItem value="aktif">
                                            Aktif
                                        </SelectItem>
                                        <SelectItem value="nonaktif">
                                            Nonaktif
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {hasDirectorFilter && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={resetDirectorFilters}
                                    className="justify-self-start"
                                >
                                    <RotateCcw className="size-3.5" />
                                    Reset
                                </Button>
                            )}
                        </div>

                        {filteredDirectors.length === 0 ? (
                            <div className="rounded-md border border-border bg-card">
                                <EmptyState
                                    icon={Database}
                                    title={
                                        hasDirectorFilter
                                            ? 'Tidak ada direksi yang cocok'
                                            : 'Belum ada data direksi'
                                    }
                                    description={
                                        hasDirectorFilter
                                            ? 'Ubah atau reset filter untuk melihat data lainnya.'
                                            : 'Tambahkan direksi pekerjaan pertama agar dapat dipilih pada form pengadaan.'
                                    }
                                    action={
                                        hasDirectorFilter ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={resetDirectorFilters}
                                            >
                                                Reset Filter
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    setEditing({
                                                        kind: 'director',
                                                        record: null,
                                                    })
                                                }
                                            >
                                                Tambah Direksi
                                            </Button>
                                        )
                                    }
                                />
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-md border border-border bg-card">
                                <Table>
                                    <TableHeader className="bg-muted/60">
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead>Nama Jabatan</TableHead>
                                            <TableHead>Keterangan</TableHead>
                                            <TableHead>Urutan</TableHead>
                                            <TableHead>Dipakai</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Aksi
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {pagedDirectors.map((record) => (
                                            <TableRow key={record.id}>
                                                <TableCell className="font-medium">
                                                    {record.name}
                                                </TableCell>
                                                <TableCell>
                                                    {record.description || '—'}
                                                </TableCell>
                                                <TableCell className="tabular">
                                                    {record.sort_order}
                                                </TableCell>
                                                <TableCell className="tabular text-muted-foreground">
                                                    {record.usage_count ?? 0}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusPill
                                                        active={
                                                            record.is_active
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right whitespace-nowrap">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setEditing({
                                                                kind: 'director',
                                                                record,
                                                            })
                                                        }
                                                    >
                                                        <Pencil className="size-3.5" />
                                                        Ubah
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    `Nonaktifkan "${record.name}"? Data pengadaan lama tetap utuh.`,
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    masterData.workDirectors.destroy(
                                                                        record.id,
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Power className="size-3.5" />
                                                        Nonaktifkan
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <ClientPagination
                                    page={safeDirectorPage}
                                    totalPages={directorTotalPages}
                                    total={filteredDirectors.length}
                                    perPage={PER_PAGE}
                                    onPageChange={setDirectorPage}
                                />
                            </div>
                        )}
                    </TabsContent>
                </Tabs>

                <p className="text-xs text-muted-foreground">
                    Data master yang sudah dipakai pada pengadaan tidak dihapus
                    permanen, melainkan dinonaktifkan agar riwayat pengadaan
                    lama tetap utuh.
                </p>
            </div>

            {editing?.kind === 'official' && (
                <RecordDialog
                    key={`official-${editing.record?.id ?? 'new'}`}
                    title={
                        editing.record === null
                            ? 'Tambah Pejabat Unit'
                            : `Ubah ${editing.record.name}`
                    }
                    fields={officialFields}
                    defaults={OFFICIAL_DEFAULTS}
                    record={editing.record}
                    storeUrl={masterData.unitManagers.store().url}
                    updateUrl={(record) =>
                        masterData.unitManagers.update(record.id).url
                    }
                    onClose={() => setEditing(null)}
                />
            )}

            {editing?.kind === 'director' && (
                <RecordDialog
                    key={`director-${editing.record?.id ?? 'new'}`}
                    title={
                        editing.record === null
                            ? 'Tambah Direksi Pekerjaan'
                            : `Ubah ${editing.record.name}`
                    }
                    fields={DIRECTOR_FIELDS}
                    defaults={DIRECTOR_DEFAULTS}
                    record={editing.record}
                    storeUrl={masterData.workDirectors.store().url}
                    updateUrl={(record) =>
                        masterData.workDirectors.update(record.id).url
                    }
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function StatusPill({ active }: { active: boolean }) {
    return (
        <span
            className={
                active
                    ? 'inline-flex items-center gap-1.5 rounded-sm bg-status-selesai-surface px-2 py-0.5 text-xs font-medium text-status-selesai'
                    : 'inline-flex items-center gap-1.5 rounded-sm bg-status-pending-surface px-2 py-0.5 text-xs font-medium text-status-pending'
            }
        >
            {active ? 'Aktif' : 'Nonaktif'}
        </span>
    );
}

function ClientPagination({
    page,
    totalPages,
    total,
    perPage,
    onPageChange,
}: {
    page: number;
    totalPages: number;
    total: number;
    perPage: number;
    onPageChange: (next: number) => void;
}) {
    const from = total === 0 ? 0 : (page - 1) * perPage + 1;
    const to = Math.min(page * perPage, total);

    return (
        <div className="flex flex-col gap-2 border-t border-border px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
            <span className="tabular text-xs text-muted-foreground">
                Menampilkan {from}–{to} dari {total} data
            </span>

            {totalPages > 1 && (
                <nav
                    aria-label="Navigasi halaman"
                    className="flex flex-wrap items-center gap-1"
                >
                    <button
                        type="button"
                        disabled={page <= 1}
                        onClick={() => onPageChange(page - 1)}
                        className="rounded-sm px-2 py-1 text-xs text-muted-foreground transition-colors enabled:hover:bg-accent enabled:hover:text-accent-foreground disabled:opacity-40"
                    >
                        Sebelumnya
                    </button>
                    {Array.from(
                        { length: totalPages },
                        (_, idx) => idx + 1,
                    ).map((num) => (
                        <button
                            key={num}
                            type="button"
                            onClick={() => onPageChange(num)}
                            aria-current={num === page ? 'page' : undefined}
                            className={cn(
                                'tabular rounded-sm px-2 py-1 text-xs transition-colors',
                                num === page
                                    ? 'bg-primary font-medium text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )}
                        >
                            {num}
                        </button>
                    ))}
                    <button
                        type="button"
                        disabled={page >= totalPages}
                        onClick={() => onPageChange(page + 1)}
                        className="rounded-sm px-2 py-1 text-xs text-muted-foreground transition-colors enabled:hover:bg-accent enabled:hover:text-accent-foreground disabled:opacity-40"
                    >
                        Berikutnya
                    </button>
                </nav>
            )}
        </div>
    );
}

function RecordDialog({
    title,
    fields,
    defaults,
    record,
    storeUrl,
    updateUrl,
    onClose,
}: {
    title: string;
    fields: MasterField[];
    defaults: MasterFormValues;
    record: MasterRecord | null;
    storeUrl: string;
    updateUrl: (record: MasterRecord) => string;
    onClose: () => void;
}) {
    const initial: MasterFormValues = {};
    Object.keys(defaults).forEach((key) => {
        initial[key] =
            (record?.[key] as MasterFormValue) ?? defaults[key];
    });

    const form = useForm<MasterFormValues>(initial);

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
        };

        if (record === null) {
            form.post(storeUrl, options);

            return;
        }

        form.put(updateUrl(record), options);
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
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Perubahan pada data master langsung berlaku untuk
                        pengadaan berikutnya.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="unit-manager-form"
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    {fields.map((field) =>
                        field.visible === undefined ||
                        field.visible(form.data) ? (
                            <FieldControl
                                key={field.name}
                                field={field}
                                value={form.data[field.name]}
                                onChange={(next) =>
                                    form.setData(field.name, next)
                                }
                                error={form.errors[field.name]}
                            />
                        ) : null,
                    )}
                </form>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={form.processing}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="unit-manager-form"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

UnitManagers.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Pejabat Unit & Direksi',
            href: masterData.unitManagers.index(),
        },
    ],
};
