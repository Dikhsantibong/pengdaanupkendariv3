import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

/*
 * The application is locked to a single light theme.
 *
 * Theme switching was deliberately removed: the interface is fixed to the PLN
 * light-blue-and-white palette on every page, so there is no dark mode and no
 * appearance setting. The hook and its API are kept so existing consumers keep
 * compiling, but every path resolves to light.
 */

const forceLight = (): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
};

export function initializeTheme(): void {
    forceLight();
}

export function useAppearance(): UseAppearanceReturn {
    // A stable snapshot: the value never changes, so the store never notifies.
    const appearance = useSyncExternalStore(
        () => () => {},
        () => 'light' as const,
        () => 'light' as const,
    );

    const updateAppearance = (): void => {
        // Theme is fixed; keep the signature but do nothing but re-assert light.
        forceLight();
    };

    return {
        appearance,
        resolvedAppearance: 'light',
        updateAppearance,
    } as const;
}
