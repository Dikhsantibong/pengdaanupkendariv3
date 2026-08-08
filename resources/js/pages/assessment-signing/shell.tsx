import type { ReactNode } from 'react';

/**
 * The frame around the pages an assessor reaches from a WhatsApp link.
 *
 * These pages are opened on a phone by somebody with no account, so the shell
 * carries no navigation, no session controls and nothing else about the
 * procurement — only the unit's identity and the sheet being signed.
 */
export function SigningShell({
    title,
    subtitle,
    children,
}: {
    title: string;
    subtitle: string;
    children: ReactNode;
}) {
    return (
        <div className="min-h-svh bg-muted/40">
            <header className="border-b border-border bg-card">
                <div className="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4">
                    <img
                        src="/logo/sidebar-logo.png"
                        alt="PT PLN Nusantara Power"
                        className="h-10 w-auto"
                    />
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">
                            {title}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-4">{children}</main>
        </div>
    );
}
