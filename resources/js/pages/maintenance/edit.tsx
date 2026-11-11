import { Head, useForm } from '@inertiajs/react';
import { Power, Save, Wrench } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import maintenanceRoutes from '@/routes/maintenance';

type MaintenanceState = {
    active: boolean;
    message: string;
    until: string | null;
    updated_by: string | null;
    updated_at: string | null;
};

/** An ISO date as the value of a datetime-local field, in local time. */
function toLocalInput(value: string | null): string {
    if (value === null) {
        return '';
    }

    const date = new Date(value);
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function MaintenanceEdit({
    maintenance,
    defaultMessage,
}: {
    maintenance: MaintenanceState;
    defaultMessage: string;
}) {
    const form = useForm({
        active: maintenance.active,
        message:
            maintenance.message === defaultMessage ? '' : maintenance.message,
        until: toLocalInput(maintenance.until),
    });

    const submit = () =>
        form.put(maintenanceRoutes.update().url, { preserveScroll: true });

    const preview = form.data.message.trim() || defaultMessage;

    return (
        <>
            <Head title="Mode Pemeliharaan" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Administrasi"
                    title="Mode Pemeliharaan"
                    description="Tutup sementara aplikasi saat pemeliharaan. Selama aktif, halaman login menampilkan pemberitahuan dan hanya Administrator yang dapat masuk."
                />

                <section
                    className={cn(
                        'flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between',
                        maintenance.active
                            ? 'border-amber-500/40 bg-amber-500/10'
                            : 'border-border bg-card',
                    )}
                >
                    <div className="flex items-start gap-3">
                        <span
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-md',
                                maintenance.active
                                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                    : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                            )}
                        >
                            {maintenance.active ? (
                                <Wrench className="size-4" />
                            ) : (
                                <Power className="size-4" />
                            )}
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-foreground">
                                {maintenance.active
                                    ? 'Mode pemeliharaan sedang aktif'
                                    : 'Aplikasi dapat digunakan seperti biasa'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {maintenance.updated_at
                                    ? `Terakhir diubah ${maintenance.updated_by ?? '—'} · ${formatDateTime(maintenance.updated_at)}`
                                    : 'Belum pernah diaktifkan.'}
                            </p>
                        </div>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <form
                        className="space-y-4 rounded-md border border-border bg-card p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                    >
                        <div className="flex items-center justify-between gap-4 rounded-md border border-border px-3 py-3">
                            <div className="space-y-0.5">
                                <Label htmlFor="maintenance-active">
                                    Aktifkan mode pemeliharaan
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Pengguna lain yang sedang masuk akan
                                    dikeluarkan saat membuka halaman berikutnya.
                                    Halaman publik (Beranda, Monitoring Publik,
                                    formulir vendor) ikut ditutup.
                                </p>
                            </div>
                            <Switch
                                id="maintenance-active"
                                checked={form.data.active}
                                onCheckedChange={(checked) =>
                                    form.setData('active', checked)
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="maintenance-message">
                                Pesan di halaman login
                            </Label>
                            <Textarea
                                id="maintenance-message"
                                value={form.data.message}
                                onChange={(event) =>
                                    form.setData('message', event.target.value)
                                }
                                placeholder={defaultMessage}
                                rows={4}
                            />
                            <p className="text-xs text-muted-foreground">
                                Kosongkan untuk memakai pesan bawaan.
                            </p>
                            <InputError message={form.errors.message} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="maintenance-until">
                                Perkiraan selesai (opsional)
                            </Label>
                            <Input
                                id="maintenance-until"
                                type="datetime-local"
                                value={form.data.until}
                                onChange={(event) =>
                                    form.setData('until', event.target.value)
                                }
                                className="sm:w-64"
                            />
                            <p className="text-xs text-muted-foreground">
                                Hanya informasi. Mode pemeliharaan tetap aktif
                                sampai dimatikan di halaman ini.
                            </p>
                            <InputError message={form.errors.until} />
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                <Save className="size-4" />
                                Simpan
                            </Button>
                        </div>
                    </form>

                    <section className="space-y-3 rounded-md border border-border bg-card p-4">
                        <p className="section-label">
                            Pratinjau di halaman login
                        </p>
                        {form.data.active ? (
                            <div className="flex items-start gap-3 rounded-md border border-amber-500/40 bg-amber-500/10 p-4">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-amber-500/15 text-amber-700 dark:text-amber-400">
                                    <Wrench className="size-4" />
                                </span>
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold text-foreground">
                                        Aplikasi Sedang dalam Pemeliharaan
                                    </p>
                                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                                        {preview}
                                    </p>
                                    {form.data.until && (
                                        <p className="text-xs font-medium text-amber-800 dark:text-amber-300">
                                            Perkiraan selesai:{' '}
                                            {formatDateTime(form.data.until)}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Untuk sementara hanya Administrator yang
                                        dapat masuk.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <p className="rounded-md border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                                Mode pemeliharaan tidak aktif: halaman login
                                tampil seperti biasa.
                            </p>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

MaintenanceEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Mode Pemeliharaan', href: maintenanceRoutes.edit() },
    ],
};
