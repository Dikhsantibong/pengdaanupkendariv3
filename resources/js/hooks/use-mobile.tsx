import { useSyncExternalStore } from 'react';

const MOBILE_BREAKPOINT = 768;

const mql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

function mediaQueryListener(callback: (event: MediaQueryListEvent) => void) {
    if (!mql) {
        return () => {};
    }

    mql.addEventListener('change', callback);

    return () => {
        mql.removeEventListener('change', callback);
    };
}

function isSmallerThanBreakpoint(): boolean {
    return mql?.matches ?? false;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(): boolean {
    return useSyncExternalStore(
        mediaQueryListener,
        isSmallerThanBreakpoint,
        getServerSnapshot,
    );
}

const compactMql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia('(max-width: 1023px)');

function compactListener(callback: (event: MediaQueryListEvent) => void) {
    if (!compactMql) {
        return () => {};
    }

    compactMql.addEventListener('change', callback);

    return () => compactMql.removeEventListener('change', callback);
}

/**
 * A phone or a tablet (narrower than 1024px): too narrow for the sidebar to
 * stand beside the page, so it slides over it instead.
 */
export function useIsCompact(): boolean {
    return useSyncExternalStore(
        compactListener,
        () => compactMql?.matches ?? false,
        getServerSnapshot,
    );
}
