import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated, PaginationLink } from '@/types';

/**
 * Read the numbered page links regardless of how the paginator was serialised.
 * Plain paginators keep them at the root; resource collections move them under
 * `meta` and use the root `links` for the first/last/prev/next cursors.
 */
function pageLinks<T>(page: Paginated<T>): PaginationLink[] {
    if (Array.isArray(page.links)) {
        return page.links;
    }

    return page.meta?.links ?? [];
}

export function DataPagination<T>({ page }: { page: Paginated<T> }) {
    const meta = page.meta ?? page;
    const from = meta.from ?? 0;
    const to = meta.to ?? 0;
    const total = meta.total ?? 0;
    const links = pageLinks(page);

    const summary = (
        <span className="tabular text-xs text-muted-foreground">
            Menampilkan {from}–{to} dari {total} data
        </span>
    );

    if (links.length <= 3) {
        return (
            <div className="flex items-center justify-between border-t border-border px-3 py-2">
                {summary}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-2 border-t border-border px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
            {summary}

            <nav className="flex flex-wrap items-center gap-1">
                {links.map((link, index) =>
                    link.url === null ? (
                        <span
                            key={`${link.label}-${index}`}
                            className="rounded-sm px-2 py-1 text-xs text-muted-foreground/50"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={cn(
                                'rounded-sm px-2 py-1 text-xs transition-colors',
                                link.active
                                    ? 'bg-primary font-medium text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </nav>
        </div>
    );
}
