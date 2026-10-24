import { Minus, MoveHorizontal, Plus } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/** The zoom steps, as Word offers them. */
const ZOOM_LEVELS = [0.5, 0.67, 0.75, 0.9, 1, 1.1, 1.25, 1.5, 1.75, 2];

const MIN_ZOOM = 0.3;
const MAX_ZOOM = 3;

/** Where the last zoom the author chose is remembered, per browser. */
const STORAGE_KEY = 'document-editor-zoom';

/** The value of the "fit the page to the width" choice. */
const FIT = 'fit';

/** The room kept beside the sheet when it is fitted to the width, in px. */
const FIT_GUTTER = 48;

function clamp(zoom: number): number {
    return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.round(zoom * 100) / 100));
}

function storedZoom(): number | typeof FIT | null {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        if (stored === FIT) {
            return FIT;
        }

        const zoom = Number(stored);

        return stored !== null && Number.isFinite(zoom) && zoom > 0
            ? clamp(zoom)
            : null;
    } catch {
        return null;
    }
}

function remember(value: number | typeof FIT): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, String(value));
    } catch {
        // Private windows may refuse storage; the zoom still applies.
    }
}

/**
 * The zoom of a sheet of paper on screen, as in Word: a chosen percentage,
 * or fitted to the width of the space it is shown in (followed as that space
 * changes). The choice is remembered for the next document; without one, a
 * narrow screen starts fitted so the page is never cut off.
 */
export function useDocumentZoom(
    container: RefObject<HTMLElement | null>,
    paperWidth: number,
) {
    const [fit, setFit] = useState<boolean>(() => {
        const stored = storedZoom();

        return stored === null ? window.innerWidth < 1200 : stored === FIT;
    });
    const [chosen, setChosen] = useState<number>(() => {
        const stored = storedZoom();

        return typeof stored === 'number' ? stored : 1;
    });
    const [width, setWidth] = useState(0);

    useEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        // Measured on every resize, and once more after the first paint: a
        // page still being laid out on load can report no width at first.
        const measure = () => setWidth(element.clientWidth);
        const observer = new ResizeObserver(measure);
        observer.observe(element);
        const frame = requestAnimationFrame(measure);
        const timer = window.setTimeout(measure, 250);
        window.addEventListener('resize', measure);

        return () => {
            observer.disconnect();
            cancelAnimationFrame(frame);
            window.clearTimeout(timer);
            window.removeEventListener('resize', measure);
        };
    }, [container]);

    const fitted =
        width > 0 && paperWidth > 0
            ? clamp((width - FIT_GUTTER) / paperWidth)
            : 1;
    const zoom = fit ? fitted : chosen;

    const setZoom = useCallback((next: number) => {
        const value = clamp(next);
        setFit(false);
        setChosen(value);
        remember(value);
    }, []);

    const fitWidth = useCallback(() => {
        setFit(true);
        remember(FIT);
    }, []);

    const zoomIn = useCallback(() => {
        setZoom(ZOOM_LEVELS.find((level) => level > zoom + 0.001) ?? MAX_ZOOM);
    }, [setZoom, zoom]);

    const zoomOut = useCallback(() => {
        setZoom(
            [...ZOOM_LEVELS].reverse().find((level) => level < zoom - 0.001) ??
                MIN_ZOOM,
        );
    }, [setZoom, zoom]);

    return { zoom, fit, setZoom, fitWidth, zoomIn, zoomOut };
}

/** Zoom out, the percentage (or fitted to the width) and zoom in. */
export function ZoomControls({
    zoom,
    fit,
    onZoom,
    onFitWidth,
    onZoomIn,
    onZoomOut,
    className,
}: {
    zoom: number;
    fit: boolean;
    onZoom: (zoom: number) => void;
    onFitWidth: () => void;
    onZoomIn: () => void;
    onZoomOut: () => void;
    className?: string;
}) {
    const percent = Math.round(zoom * 100);

    return (
        <div className={cn('flex items-center gap-1', className)}>
            <button
                type="button"
                title="Perkecil (Ctrl + gulir)"
                aria-label="Perkecil"
                onMouseDown={(event) => event.preventDefault()}
                onClick={onZoomOut}
                disabled={zoom <= MIN_ZOOM}
                className="inline-flex size-7 items-center justify-center rounded-md text-foreground/80 hover:bg-muted hover:text-foreground disabled:opacity-40"
            >
                <Minus className="size-3.5" />
            </button>

            <Select
                value={fit ? FIT : String(percent)}
                onValueChange={(next) => {
                    if (next === FIT) {
                        onFitWidth();

                        return;
                    }

                    // The list can report an empty value while the zoom moves
                    // between its levels (a pinch): that is no choice, and
                    // read as 0 it would drop the zoom to its minimum.
                    const percentage = Number(next);

                    if (
                        next !== '' &&
                        Number.isFinite(percentage) &&
                        percentage > 0
                    ) {
                        onZoom(percentage / 100);
                    }
                }}
            >
                <SelectTrigger
                    className="h-7 w-[7.5rem] text-xs"
                    title="Perbesaran halaman"
                    onMouseDown={(event) => event.stopPropagation()}
                >
                    <SelectValue>
                        {fit ? `Lebar (${percent}%)` : `${percent}%`}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={FIT}>
                        <MoveHorizontal className="size-3.5" />
                        Sesuai lebar
                    </SelectItem>
                    {ZOOM_LEVELS.map((level) => (
                        <SelectItem
                            key={level}
                            value={String(Math.round(level * 100))}
                        >
                            {Math.round(level * 100)}%
                        </SelectItem>
                    ))}
                    {!fit &&
                        !ZOOM_LEVELS.some(
                            (level) => Math.round(level * 100) === percent,
                        ) && (
                            <SelectItem value={String(percent)}>
                                {percent}%
                            </SelectItem>
                        )}
                </SelectContent>
            </Select>

            <button
                type="button"
                title="Perbesar (Ctrl + gulir)"
                aria-label="Perbesar"
                onMouseDown={(event) => event.preventDefault()}
                onClick={onZoomIn}
                disabled={zoom >= MAX_ZOOM}
                className="inline-flex size-7 items-center justify-center rounded-md text-foreground/80 hover:bg-muted hover:text-foreground disabled:opacity-40"
            >
                <Plus className="size-3.5" />
            </button>
        </div>
    );
}

/**
 * Ctrl (or ⌘) with the mouse wheel zooms the sheet, as in Word, instead of
 * the whole browser page.
 */
export function useWheelZoom(
    target: RefObject<HTMLElement | null>,
    zoom: number,
    setZoom: (zoom: number) => void,
): void {
    const step = usePinchZoom(zoom, setZoom);

    useEffect(() => {
        const element = target.current;

        if (!element) {
            return;
        }

        const onWheel = (event: WheelEvent) => {
            if (step(event)) {
                event.preventDefault();
            }
        };

        element.addEventListener('wheel', onWheel, { passive: false });

        return () => element.removeEventListener('wheel', onWheel);
    }, [target, step]);
}

/**
 * Ctrl + wheel and a touchpad pinch (which the browser sends as Ctrl +
 * wheel), as in Word. A pinch comes as many small steps, each zooming by as
 * much as the fingers moved, gathered on the zoom as it is now so none is
 * lost to rounding or to a render in between; a mouse wheel's notch zooms
 * 10%. Returns the handler, true when it zoomed.
 */
export function usePinchZoom(
    zoom: number,
    setZoom: (zoom: number) => void,
): (event: WheelEvent) => boolean {
    const current = useRef(zoom);
    const setter = useRef(setZoom);

    // Zoomed from elsewhere (the buttons, the fit): carry on from there.
    useEffect(() => {
        current.current = zoom;
    }, [zoom]);

    useEffect(() => {
        setter.current = setZoom;
    }, [setZoom]);

    return useCallback((event: WheelEvent) => {
        if (!event.ctrlKey && !event.metaKey) {
            return false;
        }

        // Lines or pages (some mice) read as pixels.
        const delta =
            event.deltaY *
            (event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? 400 : 1);
        // A mouse notch is a large, whole step; a pinch, small fractions.
        const isNotch = Math.abs(delta) >= 50 && Number.isInteger(delta);
        const factor = isNotch
            ? delta < 0
                ? 1.1
                : 1 / 1.1
            : Math.exp(-delta * 0.01);

        current.current = Math.min(
            MAX_ZOOM,
            Math.max(MIN_ZOOM, current.current * factor),
        );
        setter.current(current.current);

        return true;
    }, []);
}
