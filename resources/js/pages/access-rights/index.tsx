import { Head, useForm } from '@inertiajs/react';
import { Lock, RotateCcw, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import accessRights from '@/routes/access-rights';

type PermissionRow = {
    value: string;
    label: string;
    description: string;
    group: string;
    defaults: string[];
};

type RoleColumn = { value: string; label: string; locked: boolean };

type Grants = Record<string, string[]>;

export default function AccessRights({
    permissions,
    roles,
    grants,
}: {
    permissions: PermissionRow[];
    roles: RoleColumn[];
    grants: Grants;
}) {
    const form = useForm<{ grants: Grants }>({ grants });

    const configurable = roles.filter((role) => !role.locked);

    const holds = (role: string, permission: string) =>
        form.data.grants[role]?.includes(permission) ?? false;

    const toggle = (role: string, permission: string, checked: boolean) => {
        const current = new Set(form.data.grants[role] ?? []);

        if (checked) {
            current.add(permission);
        } else {
            current.delete(permission);
        }

        form.setData('grants', {
            ...form.data.grants,
            [role]: permissions
                .map((row) => row.value)
                .filter((value) => current.has(value)),
        });
    };

    /** Put every role back to the access it had before any change. */
    const restoreDefaults = () =>
        form.setData(
            'grants',
            Object.fromEntries(
                configurable.map((role) => [
                    role.value,
                    permissions
                        .filter((row) => row.defaults.includes(role.value))
                        .map((row) => row.value),
                ]),
            ),
        );

    const groups = permissions.reduce<Record<string, PermissionRow[]>>(
        (carry, row) => {
            (carry[row.group] ??= []).push(row);

            return carry;
        },
        {},
    );

    return (
        <>
            <Head title="Hak Akses" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Administrasi"
                    title="Hak Akses"
                    description="Atur fitur yang boleh digunakan tiap peran. Perubahan berlaku pada permintaan berikutnya, tanpa perlu login ulang."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={restoreDefaults}
                            >
                                <RotateCcw className="size-4" />
                                Kembalikan Bawaan
                            </Button>
                            <Button
                                type="button"
                                onClick={() =>
                                    form.put(accessRights.update().url, {
                                        preserveScroll: true,
                                    })
                                }
                                disabled={!form.isDirty || form.processing}
                            >
                                <Save className="size-4" />
                                Simpan Hak Akses
                            </Button>
                        </div>
                    }
                />

                <InputError message={form.errors.grants} />

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <Table>
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="min-w-72">
                                    Fitur
                                </TableHead>
                                {roles.map((role) => (
                                    <TableHead
                                        key={role.value}
                                        className="w-32 text-center whitespace-nowrap"
                                    >
                                        {role.label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Object.entries(groups).map(([group, rows]) => (
                                <GroupRows
                                    key={group}
                                    group={group}
                                    rows={rows}
                                    roles={roles}
                                    holds={holds}
                                    toggle={toggle}
                                />
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="space-y-1 text-xs text-muted-foreground">
                    <p className="flex items-center gap-1.5">
                        <Lock className="size-3" />
                        Administrator selalu memiliki seluruh hak akses dan
                        tidak dapat diubah, agar sistem tidak pernah terkunci.
                    </p>
                    <p>
                        Pengelolaan pengguna dan hak akses hanya untuk
                        Administrator. Fitur yang mengikuti penugasan — mengisi
                        checklist, generate dokumen, dan mengajukan perencanaan
                        — tetap mengikuti PIC yang ditunjuk.
                    </p>
                    <p>
                        Peran yang diberi hak menyetujui perencanaan tetap tidak
                        dapat menyetujui perencanaan yang ia ajukan sendiri.
                        Pembuat pengadaan selalu dapat melihat pengadaan yang ia
                        buat.
                    </p>
                </div>
            </div>
        </>
    );
}

function GroupRows({
    group,
    rows,
    roles,
    holds,
    toggle,
}: {
    group: string;
    rows: PermissionRow[];
    roles: RoleColumn[];
    holds: (role: string, permission: string) => boolean;
    toggle: (role: string, permission: string, checked: boolean) => void;
}) {
    return (
        <>
            <TableRow className="bg-muted/30 hover:bg-muted/30">
                <TableCell
                    colSpan={roles.length + 1}
                    className="section-label py-2"
                >
                    {group}
                </TableCell>
            </TableRow>
            {rows.map((row) => (
                <TableRow key={row.value}>
                    <TableCell className="align-top">
                        <p className="text-sm font-medium">{row.label}</p>
                        <p className="text-xs whitespace-normal text-muted-foreground">
                            {row.description}
                        </p>
                    </TableCell>
                    {roles.map((role) => (
                        <TableCell
                            key={role.value}
                            className="text-center align-top"
                        >
                            <Checkbox
                                aria-label={`${row.label} — ${role.label}`}
                                checked={
                                    role.locked || holds(role.value, row.value)
                                }
                                disabled={role.locked}
                                onCheckedChange={(next) =>
                                    toggle(role.value, row.value, next === true)
                                }
                            />
                        </TableCell>
                    ))}
                </TableRow>
            ))}
        </>
    );
}

AccessRights.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Hak Akses', href: accessRights.index() },
    ],
};
