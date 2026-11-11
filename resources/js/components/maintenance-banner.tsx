import { Link, usePage } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import maintenance from '@/routes/maintenance';
import type { Auth } from '@/types';

/**
 * Reminds the administrator, on every page, that the application is closed
 * to everyone else for maintenance.
 */
export function MaintenanceBanner() {
    const { auth, maintenance: state } = usePage<{
        auth: Auth;
        maintenance: { active: boolean } | null;
    }>().props;

    if (!state?.active) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm md:px-6"
        >
            <p className="flex items-center gap-2 text-amber-900 dark:text-amber-200">
                <Wrench className="size-4 shrink-0" />
                <span>
                    <b>Mode pemeliharaan aktif.</b> Halaman login menampilkan
                    pemberitahuan dan hanya Administrator yang dapat masuk.
                </span>
            </p>
            {auth.permissions.manageUsers && (
                <Link
                    href={maintenance.edit()}
                    className="text-xs font-semibold text-amber-900 underline-offset-4 hover:underline dark:text-amber-200"
                >
                    Atur mode pemeliharaan
                </Link>
            )}
        </div>
    );
}
