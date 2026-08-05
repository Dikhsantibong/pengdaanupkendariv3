import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import notifications from '@/routes/notifications';
import type { Auth, BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth, unreadNotifications } = usePage<{
        auth: Auth;
        unreadNotifications: number;
    }>().props;

    return (
        <header className="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-border bg-card px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="flex items-center gap-3">
                <div className="hidden text-right leading-tight sm:block">
                    <p className="text-sm font-medium text-foreground">
                        {auth.user.name}
                    </p>
                    <p className="text-[0.6875rem] tracking-[0.05em] text-muted-foreground uppercase">
                        {auth.user.role_label}
                    </p>
                </div>

                <Link
                    href={notifications.index()}
                    className="relative inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    aria-label="Notifikasi"
                >
                    <Bell className="size-4" />
                    {unreadNotifications > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[0.625rem] leading-4 font-semibold text-white">
                            {unreadNotifications > 9
                                ? '9+'
                                : unreadNotifications}
                        </span>
                    )}
                </Link>
            </div>
        </header>
    );
}
