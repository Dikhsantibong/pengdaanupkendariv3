import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    LayoutList,
    Network,
    Pencil,
    Plus,
    Power,
    RotateCcw,
    Search,
    Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import users from '@/routes/users';
import type { EnumOption } from '@/types';

const ALL = 'all';
const NO_DIVISION = 'none';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    position: string | null;
    work_director_id: number | null;
    work_director: string | null;
    team_leader_id: number | null;
    team_leader: string | null;
    /** Where the account sits: direksi, tl, staf or lainnya. */
    level: string;
    team_size: number;
    is_active: boolean;
    planned_count: number;
    executed_count: number;
};

type UserFormValues = {
    name: string;
    email: string;
    role: string;
    position: string;
    work_director_id: string;
    team_leader_id: string;
    is_active: boolean;
    password: string;
    password_confirmation: string;
};

type UserFilters = {
    search: string | null;
    role: string | null;
    work_director_id: string | null;
    level: string | null;
    team_leader_id: number | null;
    status: string | null;
    missing_leader: boolean;
    view: 'table' | 'structure';
};

const LEVEL_TONES: Record<string, string> = {
    direksi: 'bg-primary/10 text-primary',
    tl: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
    staf: 'bg-muted text-muted-foreground',
    lainnya: 'bg-muted text-muted-foreground',
};

export default function UserIndex({
    users: records,
    filters,
    levels,
    roles,
    divisions,
    teamLeaders,
}: {
    users: ManagedUser[];
    filters: UserFilters;
    levels: EnumOption[];
    roles: EnumOption[];
    divisions: Array<{ value: number; label: string }>;
    teamLeaders: Array<{ value: number; label: string }>;
}) {
    const levelLabel = (level: string) =>
        levels.find((option) => option.value === level)?.label ?? level;
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<ManagedUser | null>(null);
    const [open, setOpen] = useState(false);
    const isFirstRender = useRef(true);

    const defaults: UserFormValues = {
        name: '',
        email: '',
        role: roles[0]?.value ?? 'pic_perencana',
        position: '',
        work_director_id: NO_DIVISION,
        team_leader_id: NO_DIVISION,
        is_active: true,
        password: '',
        password_confirmation: '',
    };

    const form = useForm<UserFormValues>({ ...defaults });

    const visit = (
        overrides: Partial<
            Record<keyof UserFilters, string | number | boolean | null>
        >,
    ) => {
        const merged = { ...filters, ...overrides };
        const query: Record<string, string> = {};

        Object.entries(merged).forEach(([key, value]) => {
            // Defaults stay out of the address.
            if (
                value === null ||
                value === '' ||
                value === false ||
                (key === 'view' && value === 'table')
            ) {
                return;
            }

            query[key] = value === true ? '1' : String(value);
        });

        router.get(users.index().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const isFiltered =
        filters.search !== null ||
        filters.role !== null ||
        filters.work_director_id !== null ||
        filters.level !== null ||
        filters.team_leader_id !== null ||
        filters.status !== null ||
        filters.missing_leader;
    const missingLeaders = records.filter(
        (user) => user.level === 'staf' && user.team_leader_id === null,
    ).length;

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            visit({ search: search || null });
        }, 350);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const openCreate = () => {
        setEditing(null);
        form.setDefaults({ ...defaults });
        form.reset();
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (user: ManagedUser) => {
        const values: UserFormValues = {
            name: user.name,
            email: user.email,
            role: user.role,
            position: user.position ?? '',
            work_director_id:
                user.work_director_id === null
                    ? NO_DIVISION
                    : String(user.work_director_id),
            team_leader_id:
                user.team_leader_id === null
                    ? NO_DIVISION
                    : String(user.team_leader_id),
            is_active: user.is_active,
            password: '',
            password_confirmation: '',
        };

        setEditing(user);
        form.setDefaults(values);
        form.setData(values);
        form.clearErrors();
        setOpen(true);
    };

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        form.transform((data) => ({
            ...data,
            work_director_id:
                data.work_director_id === NO_DIVISION
                    ? null
                    : Number(data.work_director_id),
            team_leader_id:
                data.team_leader_id === NO_DIVISION
                    ? null
                    : Number(data.team_leader_id),
        }));

        if (editing === null) {
            form.post(users.store().url, options);

            return;
        }

        form.put(users.update(editing.id).url, options);
    };

    return (
        <>
            <Head title="Manajemen Pengguna" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Administrasi"
                    title="Manajemen Pengguna"
                    description="Kelola akun, peran, dan hak akses pengguna sistem pengadaan."
                    actions={
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            Tambah Pengguna
                        </Button>
                    }
                />

                <section
                    aria-label="Filter pengguna"
                    className="rounded-md border border-border bg-card p-3"
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="w-full space-y-1.5 lg:w-auto lg:min-w-64 lg:flex-[2_1_auto]">
                            <Label
                                htmlFor="user-search"
                                className="section-label"
                            >
                                Cari
                            </Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="user-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Nama, email, atau jabatan"
                                    className="pl-9"
                                />
                            </div>
                        </div>

                        <FilterSelect
                            label="Peran"
                            value={filters.role}
                            options={roles.map((role) => ({
                                value: role.value,
                                label: role.label,
                            }))}
                            onChange={(value) => visit({ role: value })}
                        />
                        <FilterSelect
                            label="Bidang"
                            value={filters.work_director_id}
                            options={[
                                ...divisions.map((division) => ({
                                    value: String(division.value),
                                    label: division.label,
                                })),
                                { value: NO_DIVISION, label: 'Tanpa bidang' },
                            ]}
                            onChange={(value) =>
                                visit({ work_director_id: value })
                            }
                        />
                        <FilterSelect
                            label="Tingkat Jabatan"
                            value={filters.level}
                            options={levels.map((level) => ({
                                value: level.value,
                                label: level.label,
                            }))}
                            onChange={(value) => visit({ level: value })}
                        />
                        <FilterSelect
                            label="TL / Atasan"
                            value={
                                filters.team_leader_id === null
                                    ? null
                                    : String(filters.team_leader_id)
                            }
                            options={teamLeaders.map((leader) => ({
                                value: String(leader.value),
                                label: leader.label,
                            }))}
                            onChange={(value) =>
                                visit({
                                    team_leader_id:
                                        value === null ? null : Number(value),
                                })
                            }
                        />
                        <FilterSelect
                            label="Status"
                            value={filters.status}
                            options={[
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Nonaktif' },
                            ]}
                            onChange={(value) => visit({ status: value })}
                        />

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={!isFiltered}
                            onClick={() => {
                                setSearch('');
                                visit({
                                    search: null,
                                    role: null,
                                    work_director_id: null,
                                    level: null,
                                    team_leader_id: null,
                                    status: null,
                                    missing_leader: false,
                                });
                            }}
                        >
                            <RotateCcw className="size-3.5" />
                            Reset Filter
                        </Button>
                    </div>

                    <div className="mt-3 flex flex-col gap-2 border-t border-border pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span className="tabular">
                                {records.length} pengguna
                            </span>
                            <button
                                type="button"
                                onClick={() =>
                                    visit({
                                        missing_leader: !filters.missing_leader,
                                    })
                                }
                                aria-pressed={filters.missing_leader}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 font-medium transition-colors',
                                    filters.missing_leader
                                        ? 'border-amber-500/60 bg-amber-500/10 text-amber-800 dark:text-amber-300'
                                        : 'border-border hover:bg-muted',
                                )}
                            >
                                <AlertTriangle className="size-3.5" />
                                Staf tanpa TL
                                {!filters.missing_leader &&
                                    missingLeaders > 0 && (
                                        <span className="tabular rounded-full bg-amber-500/15 px-1.5 text-amber-800 dark:text-amber-300">
                                            {missingLeaders}
                                        </span>
                                    )}
                            </button>
                        </div>

                        <div
                            role="tablist"
                            aria-label="Tampilan"
                            className="inline-flex w-fit rounded-md border border-border p-0.5"
                        >
                            {(
                                [
                                    {
                                        value: 'table',
                                        label: 'Tabel',
                                        icon: LayoutList,
                                    },
                                    {
                                        value: 'structure',
                                        label: 'Struktur Jabatan',
                                        icon: Network,
                                    },
                                ] as const
                            ).map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    role="tab"
                                    aria-selected={
                                        filters.view === option.value
                                    }
                                    onClick={() =>
                                        visit({ view: option.value })
                                    }
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-sm px-3 py-1.5 text-xs font-medium transition-colors',
                                        filters.view === option.value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    <option.icon className="size-3.5" />
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </section>

                {records.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Users}
                            title="Tidak ada pengguna"
                            description="Tidak ada pengguna yang cocok dengan filter saat ini."
                        />
                    </div>
                ) : filters.view === 'structure' ? (
                    <OrgStructure
                        records={records}
                        divisions={divisions}
                        levelLabel={levelLabel}
                        onEdit={openEdit}
                    />
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Peran</TableHead>
                                    <TableHead>Jabatan</TableHead>
                                    <TableHead>Bidang</TableHead>
                                    <TableHead>Penugasan</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {records.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <span className="font-medium">
                                                {user.name}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {user.email}
                                            </span>
                                        </TableCell>
                                        <TableCell>{user.role_label}</TableCell>
                                        <TableCell>
                                            {user.position ?? '—'}
                                            <span
                                                className={cn(
                                                    'mt-0.5 block w-fit rounded-sm px-1.5 py-px text-[0.6875rem] font-medium',
                                                    LEVEL_TONES[user.level],
                                                )}
                                            >
                                                {levelLabel(user.level)}
                                                {user.team_size > 0 &&
                                                    ` · ${user.team_size} staf`}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {user.work_director ?? '—'}
                                            {user.team_leader ? (
                                                <span className="block text-xs text-muted-foreground">
                                                    TL: {user.team_leader}
                                                </span>
                                            ) : (
                                                user.level === 'staf' && (
                                                    <span className="mt-0.5 flex items-center gap-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                                                        <AlertTriangle className="size-3" />
                                                        Belum ada TL
                                                    </span>
                                                )
                                            )}
                                        </TableCell>
                                        <TableCell className="tabular text-xs text-muted-foreground">
                                            {user.planned_count} perencanaan ·{' '}
                                            {user.executed_count} pelaksanaan
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={
                                                    user.is_active
                                                        ? 'inline-flex items-center rounded-sm bg-status-selesai-surface px-2 py-0.5 text-xs font-medium text-status-selesai'
                                                        : 'inline-flex items-center rounded-sm bg-status-pending-surface px-2 py-0.5 text-xs font-medium text-status-pending'
                                                }
                                            >
                                                {user.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => openEdit(user)}
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
                                                            `Nonaktifkan akun ${user.name}?`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            users.destroy(
                                                                user.id,
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
                    </div>
                )}
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing === null
                                ? 'Tambah Pengguna'
                                : `Ubah ${editing.name}`}
                        </DialogTitle>
                        <DialogDescription>
                            PIC hanya dapat melihat pengadaan yang ditugaskan
                            kepadanya. Team Leader dan Administrator melihat
                            seluruh data.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        id="user-form"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                        className="space-y-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="user-name">Nama</Label>
                            <Input
                                id="user-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                autoComplete="off"
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user-email">Alamat Email</Label>
                            <Input
                                id="user-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                autoComplete="off"
                                required
                            />
                            <InputError message={form.errors.email} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="user-role">Peran</Label>
                                <Select
                                    value={form.data.role}
                                    onValueChange={(value) =>
                                        form.setData('role', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="user-role"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.role} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="user-position">Jabatan</Label>
                                <Input
                                    id="user-position"
                                    value={form.data.position}
                                    onChange={(event) =>
                                        form.setData(
                                            'position',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="off"
                                    placeholder="Opsional"
                                />
                                <InputError message={form.errors.position} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user-division">
                                Bidang (Direksi Pekerjaan)
                            </Label>
                            <Select
                                value={form.data.work_director_id}
                                onValueChange={(value) =>
                                    form.setData('work_director_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="user-division"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_DIVISION}>
                                        Tidak terikat bidang
                                    </SelectItem>
                                    {divisions.map((division) => (
                                        <SelectItem
                                            key={division.value}
                                            value={String(division.value)}
                                        >
                                            {division.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Wajib untuk Pengusul dan Direksi Pekerjaan:
                                usulan diteruskan ke Direksi Pekerjaan bidang
                                ini.
                            </p>
                            <InputError
                                message={form.errors.work_director_id}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user-team-leader">
                                TL / Atasan Langsung
                            </Label>
                            <Select
                                value={form.data.team_leader_id}
                                onValueChange={(value) =>
                                    form.setData('team_leader_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="user-team-leader"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_DIVISION}>
                                        Tidak ada (langsung ke Asman)
                                    </SelectItem>
                                    {teamLeaders
                                        .filter(
                                            (leader) =>
                                                leader.value !== editing?.id,
                                        )
                                        .map((leader) => (
                                            <SelectItem
                                                key={leader.value}
                                                value={String(leader.value)}
                                            >
                                                {leader.label}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Usulan pekerjaan staf disetujui TL ini dulu,
                                lalu Asman bidangnya.
                            </p>
                            <InputError message={form.errors.team_leader_id} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="user-password">
                                    Kata Sandi
                                </Label>
                                <Input
                                    id="user-password"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="new-password"
                                    placeholder={
                                        editing === null
                                            ? ''
                                            : 'Kosongkan jika tidak diubah'
                                    }
                                    required={editing === null}
                                />
                                <InputError message={form.errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="user-password-confirmation">
                                    Konfirmasi Kata Sandi
                                </Label>
                                <Input
                                    id="user-password-confirmation"
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(event) =>
                                        form.setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="new-password"
                                    required={editing === null}
                                />
                            </div>
                        </div>

                        <div className="flex items-center justify-between gap-4 rounded-md border border-border px-3 py-2.5">
                            <div className="space-y-0.5">
                                <Label htmlFor="user-active">Akun Aktif</Label>
                                <p className="text-xs text-muted-foreground">
                                    Akun nonaktif tidak dapat dipilih sebagai
                                    PIC.
                                </p>
                            </div>
                            <Switch
                                id="user-active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                        </div>
                    </form>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="user-form"
                            disabled={form.processing}
                        >
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * One filter dropdown with an "Semua" choice that clears it.
 */
function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string | null;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string | null) => void;
}) {
    return (
        <div className="w-full space-y-1.5 sm:w-auto sm:min-w-40 sm:flex-[1_1_auto]">
            <Label className="section-label">{label}</Label>
            <Select
                value={value ?? ALL}
                onValueChange={(next) => onChange(next === ALL ? null : next)}
            >
                <SelectTrigger className="w-0 min-w-full">
                    <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Semua</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

/**
 * The accounts as the organisation: per division its Direksi Pekerjaan,
 * each TL with the staff they lead, and the staff still without a TL.
 */
function OrgStructure({
    records,
    divisions,
    levelLabel,
    onEdit,
}: {
    records: ManagedUser[];
    divisions: Array<{ value: number; label: string }>;
    levelLabel: (level: string) => string;
    onEdit: (user: ManagedUser) => void;
}) {
    const groups = [
        ...divisions.map((division) => ({
            key: String(division.value),
            name: division.label,
            members: records.filter(
                (user) => user.work_director_id === division.value,
            ),
        })),
        {
            key: 'none',
            name: 'Tanpa bidang',
            members: records.filter((user) => user.work_director_id === null),
        },
    ].filter((group) => group.members.length > 0);

    return (
        <div className="grid gap-4 xl:grid-cols-2">
            {groups.map((group) => {
                const ids = new Set(group.members.map((user) => user.id));
                const directors = group.members.filter(
                    (user) => user.level === 'direksi',
                );
                const leaders = group.members.filter(
                    (user) => user.level === 'tl',
                );
                const staffOf = (leaderId: number) =>
                    group.members.filter(
                        (user) =>
                            user.team_leader_id === leaderId &&
                            user.level !== 'tl',
                    );
                const placed = new Set([
                    ...directors.map((user) => user.id),
                    ...leaders.map((user) => user.id),
                    ...leaders.flatMap((leader) =>
                        staffOf(leader.id).map((user) => user.id),
                    ),
                ]);
                const unplaced = group.members.filter(
                    (user) => !placed.has(user.id),
                );
                const withoutLeader = unplaced.filter(
                    (user) =>
                        user.level === 'staf' &&
                        (user.team_leader_id === null ||
                            !ids.has(user.team_leader_id)),
                );
                const others = unplaced.filter(
                    (user) => !withoutLeader.includes(user),
                );

                return (
                    <section
                        key={group.key}
                        className="flex min-w-0 flex-col rounded-md border border-border bg-card"
                    >
                        <header className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                            <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                <Building2 className="size-4 text-primary" />
                                {group.name}
                            </h2>
                            <span className="tabular text-xs text-muted-foreground">
                                {group.members.length} akun
                            </span>
                        </header>

                        <div className="space-y-3 p-4">
                            {directors.length > 0 && (
                                <div className="space-y-1.5">
                                    <p className="section-label">
                                        Direksi Pekerjaan
                                    </p>
                                    {directors.map((user) => (
                                        <PersonRow
                                            key={user.id}
                                            user={user}
                                            tone="direksi"
                                            caption={levelLabel(user.level)}
                                            onEdit={onEdit}
                                        />
                                    ))}
                                </div>
                            )}

                            {leaders.map((leader) => {
                                const staff = staffOf(leader.id);

                                return (
                                    <div
                                        key={leader.id}
                                        className="rounded-md border border-border"
                                    >
                                        <div className="border-b border-border bg-muted/40 px-2 py-1.5">
                                            <PersonRow
                                                user={leader}
                                                tone="tl"
                                                caption={`${staff.length} staf`}
                                                onEdit={onEdit}
                                            />
                                        </div>
                                        {staff.length === 0 ? (
                                            <p className="px-3 py-2 text-xs text-muted-foreground">
                                                Belum ada staf.
                                            </p>
                                        ) : (
                                            <ul className="space-y-0.5 py-1.5 pr-2 pl-6">
                                                {staff.map((user) => (
                                                    <li
                                                        key={user.id}
                                                        className="relative before:absolute before:top-1/2 before:-left-3 before:h-px before:w-2.5 before:bg-border"
                                                    >
                                                        <PersonRow
                                                            user={user}
                                                            tone="staf"
                                                            onEdit={onEdit}
                                                        />
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                );
                            })}

                            {withoutLeader.length > 0 && (
                                <div className="rounded-md border border-amber-500/50 bg-amber-500/5">
                                    <p className="flex items-center gap-1.5 border-b border-amber-500/30 px-3 py-1.5 text-xs font-semibold text-amber-800 dark:text-amber-300">
                                        <AlertTriangle className="size-3.5 shrink-0" />
                                        Staf tanpa TL ({withoutLeader.length}):
                                        usulannya tidak lewat TL. Atur TL /
                                        Atasan Langsung.
                                    </p>
                                    <ul className="space-y-0.5 p-1.5">
                                        {withoutLeader.map((user) => (
                                            <li key={user.id}>
                                                <PersonRow
                                                    user={user}
                                                    tone="staf"
                                                    onEdit={onEdit}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {others.length > 0 && (
                                <div className="space-y-1.5">
                                    <p className="section-label">Lainnya</p>
                                    {others.map((user) => (
                                        <PersonRow
                                            key={user.id}
                                            user={user}
                                            tone="lainnya"
                                            caption={
                                                user.team_leader
                                                    ? `TL: ${user.team_leader}`
                                                    : user.role_label
                                            }
                                            onEdit={onEdit}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}

/**
 * One account in the structure; clicking it opens the edit form.
 */
function PersonRow({
    user,
    tone,
    caption,
    onEdit,
}: {
    user: ManagedUser;
    tone: string;
    caption?: string;
    onEdit: (user: ManagedUser) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onEdit(user)}
            title="Ubah pengguna"
            className="group flex w-full items-center gap-2.5 rounded-sm px-1.5 py-1 text-left hover:bg-muted"
        >
            <span
                className={cn(
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-[0.6875rem] font-semibold',
                    LEVEL_TONES[tone],
                )}
            >
                {user.name
                    .split(' ')
                    .slice(0, 2)
                    .map((part) => part.charAt(0))
                    .join('')
                    .toUpperCase()}
            </span>
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block truncate text-sm font-medium text-foreground group-hover:text-primary',
                        !user.is_active && 'text-muted-foreground line-through',
                    )}
                >
                    {user.name}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                    {user.position ?? user.role_label}
                </span>
            </span>
            {caption && (
                <span className="shrink-0 text-xs text-muted-foreground">
                    {caption}
                </span>
            )}
            {!user.is_active && (
                <span className="shrink-0 rounded-sm bg-status-pending-surface px-1.5 py-px text-[0.6875rem] font-medium text-status-pending">
                    Nonaktif
                </span>
            )}
        </button>
    );
}

UserIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: users.index() },
    ],
};
