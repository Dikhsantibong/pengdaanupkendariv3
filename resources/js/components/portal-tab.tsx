import { LayoutGrid } from 'lucide-react';
import { useSidebar } from '@/components/ui/sidebar';

/**
 * The "Portal Saya" tab on the left edge, standing in for the sidebar while
 * it is closed: the pointer coming near it, or a click (a tap on a phone),
 * opens the sidebar, the page making room for it. Unless pinned, it closes
 * again once the pointer leaves it.
 */
export function PortalTab() {
    const { open, isMobile, openMobile, setOpenMobile, openForAWhile } =
        useSidebar();

    if (isMobile ? openMobile : open) {
        return null;
    }

    // Open, not pinned: it goes again once the pointer leaves it.
    const show = () => (isMobile ? setOpenMobile(true) : openForAWhile());

    return (
        // A wider, invisible margin around the tab: coming near is enough.
        <div
            className="fixed top-1/2 left-0 z-40 -translate-y-1/2 py-6 pr-4 print:hidden"
            onMouseEnter={() => !isMobile && show()}
        >
            <button
                type="button"
                onClick={show}
                aria-label="Buka menu Portal Saya"
                className="flex flex-col items-center gap-2 rounded-r-lg bg-primary px-1.5 py-3 text-xs font-semibold tracking-wide text-primary-foreground shadow-lg ring-1 ring-black/10 transition-[padding] hover:pr-2.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <LayoutGrid className="size-4" />
                <span className="rotate-180 [writing-mode:vertical-rl]">
                    Portal Saya
                </span>
            </button>
        </div>
    );
}
