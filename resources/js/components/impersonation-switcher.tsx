import { router, usePage } from '@inertiajs/react';
import { ArrowLeftRight, LogOut, Search, ShieldAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import impersonation from '@/routes/impersonation';

export type ImpersonationState = {
    /** The signed-in administrator may switch into another account. */
    canStart: boolean;
    /** Set while working as another account. */
    active: { impersonator: string; since: string | null } | null;
};

type Candidate = {
    id: number;
    name: string;
    email: string;
    role: string;
    position: string | null;
};

/**
 * "Pindah Akun" in the top-right corner of the header.
 *
 * For the administrator it opens a searchable list of accounts; while
 * working as someone else it shows who is behind the session and the way
 * back. Everything that makes it safe is checked again on the server.
 */
export function ImpersonationSwitcher() {
    const { impersonation: state } = usePage<{
        impersonation: ImpersonationState;
    }>().props;

    if (state.active !== null) {
        return <ReturnButton active={state.active} />;
    }

    if (!state.canStart) {
        return null;
    }

    return <SwitchDialog />;
}

/** The way back to the administrator account. */
function ReturnButton({
    active,
}: {
    active: NonNullable<ImpersonationState['active']>;
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <div className="flex items-center gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 py-1 pr-1 pl-2.5 text-xs text-amber-900 dark:text-amber-200">
            <ShieldAlert className="size-3.5 shrink-0" />
            <span
                className="hidden leading-tight md:block"
                title={
                    active.since
                        ? `Sejak ${formatDateTime(active.since)}`
                        : undefined
                }
            >
                Mode Pindah Akun
                <span className="block text-[0.6875rem] opacity-80">
                    oleh {active.impersonator}
                </span>
            </span>
            <Button
                size="sm"
                variant="outline"
                className="h-7 border-amber-500/50 bg-card text-xs"
                disabled={processing}
                onClick={() =>
                    router.delete(impersonation.destroy().url, {
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                    })
                }
            >
                <LogOut className="size-3.5" />
                Kembali ke Administrator
            </Button>
        </div>
    );
}

/** The administrator's searchable list of accounts to switch into. */
function SwitchDialog() {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [users, setUsers] = useState<Candidate[]>([]);
    const [needsPassword, setNeedsPassword] = useState(true);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [chosen, setChosen] = useState<Candidate | null>(null);
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            setLoadFailed(false);

            fetch(impersonation.index({ query: { search } }).url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    return response.json() as Promise<{
                        users: Candidate[];
                        needsPassword: boolean;
                    }>;
                })
                .then((data) => {
                    setUsers(data.users);
                    setNeedsPassword(data.needsPassword);
                    setLoading(false);
                })
                .catch((reason: unknown) => {
                    if (
                        reason instanceof DOMException &&
                        reason.name === 'AbortError'
                    ) {
                        return;
                    }

                    setLoadFailed(true);
                    setLoading(false);
                });
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, search]);

    const reset = () => {
        setChosen(null);
        setPassword('');
        setError(undefined);
    };

    const switchTo = (user: Candidate) => {
        router.post(
            impersonation.store(user.id).url,
            needsPassword ? { password } : {},
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) => setError(errors.password),
                onSuccess: () => setOpen(false),
            },
        );
    };

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                className="h-8"
                onClick={() => {
                    reset();
                    setSearch('');
                    setOpen(true);
                }}
            >
                <ArrowLeftRight className="size-4" />
                <span className="hidden sm:inline">Pindah Akun</span>
            </Button>

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);

                    if (!next) {
                        reset();
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Pindah Akun</DialogTitle>
                        <DialogDescription>
                            Bekerja sebagai pengguna lain tanpa kata sandinya.
                            Pengaturan keamanan dan profil akun tersebut tetap
                            terkunci, dan setiap perpindahan dicatat.
                        </DialogDescription>
                    </DialogHeader>

                    {chosen === null ? (
                        <div className="space-y-3">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    autoFocus
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari nama, email, atau jabatan"
                                    className="pl-8"
                                />
                            </div>

                            <div className="max-h-80 overflow-y-auto rounded-md border border-border">
                                {loadFailed ? (
                                    <p className="px-3 py-6 text-center text-sm text-destructive">
                                        Daftar pengguna gagal dimuat.
                                    </p>
                                ) : users.length === 0 ? (
                                    <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                        {loading
                                            ? 'Memuat…'
                                            : 'Tidak ada pengguna yang cocok.'}
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border">
                                        {users.map((user) => (
                                            <li key={user.id}>
                                                <button
                                                    type="button"
                                                    className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                                    onClick={() => {
                                                        setChosen(user);

                                                        if (!needsPassword) {
                                                            switchTo(user);
                                                        }
                                                    }}
                                                >
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-medium">
                                                            {user.name}
                                                        </span>
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {user.position ??
                                                                user.email}
                                                        </span>
                                                    </span>
                                                    <span className="shrink-0 rounded-sm bg-muted px-1.5 py-0.5 text-[0.6875rem] font-medium text-muted-foreground">
                                                        {user.role}
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>
                    ) : (
                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                switchTo(chosen);
                            }}
                        >
                            <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                Masuk sebagai <b>{chosen.name}</b>{' '}
                                <span className="text-muted-foreground">
                                    ({chosen.role})
                                </span>
                            </p>

                            {needsPassword && (
                                <div className="grid gap-2">
                                    <Label htmlFor="impersonation-password">
                                        Konfirmasi kata sandi Administrator
                                    </Label>
                                    <PasswordInput
                                        id="impersonation-password"
                                        autoFocus
                                        autoComplete="current-password"
                                        value={password}
                                        onChange={(event) =>
                                            setPassword(event.target.value)
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Diminta sekali; perpindahan berikutnya
                                        dalam 15 menit tidak perlu kata sandi
                                        lagi.
                                    </p>
                                    <InputError message={error} />
                                </div>
                            )}

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={reset}
                                >
                                    Pilih pengguna lain
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (needsPassword && password === '')
                                    }
                                >
                                    <ArrowLeftRight className="size-4" />
                                    Pindah
                                </Button>
                            </div>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
