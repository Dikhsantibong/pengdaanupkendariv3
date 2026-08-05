import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search, Users } from 'lucide-react';
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
import { dashboard } from '@/routes';
import users from '@/routes/users';
import type { EnumOption } from '@/types';

const ALL = 'all';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    position: string | null;
    is_active: boolean;
    planned_count: number;
    executed_count: number;
};

type UserFormValues = {
    name: string;
    email: string;
    role: string;
    position: string;
    is_active: boolean;
    password: string;
    password_confirmation: string;
};

export default function UserIndex({
    users: records,
    filters,
    roles,
}: {
    users: ManagedUser[];
    filters: { search: string | null; role: string | null };
    roles: EnumOption[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<ManagedUser | null>(null);
    const [open, setOpen] = useState(false);
    const isFirstRender = useRef(true);

    const defaults: UserFormValues = {
        name: '',
        email: '',
        role: roles[0]?.value ?? 'pic_perencana',
        position: '',
        is_active: true,
        password: '',
        password_confirmation: '',
    };

    const form = useForm<UserFormValues>({ ...defaults });

    const visit = (overrides: Record<string, string | null>) => {
        const merged = { ...filters, ...overrides };
        const query: Record<string, string> = {};

        Object.entries(merged).forEach(([key, value]) => {
            if (value !== null && value !== '') {
                query[key] = value;
            }
        });

        router.get(users.index().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

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

                <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-3 lg:flex-row lg:items-end">
                    <div className="flex-1 space-y-1.5">
                        <Label htmlFor="user-search" className="section-label">
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
                                placeholder="Nama atau alamat email"
                                className="pl-9"
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5 lg:w-64">
                        <Label className="section-label">Peran</Label>
                        <Select
                            value={filters.role ?? ALL}
                            onValueChange={(value) =>
                                visit({ role: value === ALL ? null : value })
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Semua" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Semua</SelectItem>
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
                    </div>
                </div>

                {records.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Users}
                            title="Tidak ada pengguna"
                            description="Tidak ada pengguna yang cocok dengan filter saat ini."
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Peran</TableHead>
                                    <TableHead>Jabatan</TableHead>
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

UserIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengguna', href: users.index() },
    ],
};
