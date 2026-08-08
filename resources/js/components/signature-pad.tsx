import { Eraser } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

/**
 * A place to draw a signature with a finger or a mouse.
 *
 * Pointer events are used rather than separate mouse and touch handlers so the
 * same code works on the phone the WhatsApp link is opened on and on a desktop
 * browser. The drawing is reported upwards as a PNG data URI whenever a stroke
 * ends, which is the only shape the server accepts.
 */
export function SignaturePad({
    onChange,
    className,
}: {
    onChange: (dataUri: string | null) => void;
    className?: string;
}) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const [hasInk, setHasInk] = useState(false);

    /** Match the backing store to the box so strokes are not blurred. */
    const resize = useCallback(() => {
        const element = canvas.current;

        if (element === null) {
            return;
        }

        const ratio = window.devicePixelRatio || 1;
        const bounds = element.getBoundingClientRect();

        element.width = Math.round(bounds.width * ratio);
        element.height = Math.round(bounds.height * ratio);

        const context = element.getContext('2d');

        if (context === null) {
            return;
        }

        context.scale(ratio, ratio);
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#111827';
    }, []);

    useEffect(() => {
        resize();

        window.addEventListener('resize', resize);

        return () => window.removeEventListener('resize', resize);
    }, [resize]);

    const pointAt = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const bounds = event.currentTarget.getBoundingClientRect();

        return {
            x: event.clientX - bounds.left,
            y: event.clientY - bounds.top,
        };
    };

    const start = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const context = canvas.current?.getContext('2d');

        if (!context) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        drawing.current = true;

        const point = pointAt(event);

        context.beginPath();
        context.moveTo(point.x, point.y);
    };

    const move = (event: React.PointerEvent<HTMLCanvasElement>) => {
        if (!drawing.current) {
            return;
        }

        // Keep the page from scrolling under the finger mid-stroke.
        event.preventDefault();

        const context = canvas.current?.getContext('2d');

        if (!context) {
            return;
        }

        const point = pointAt(event);

        context.lineTo(point.x, point.y);
        context.stroke();
        setHasInk(true);
    };

    const end = () => {
        if (!drawing.current) {
            return;
        }

        drawing.current = false;

        const element = canvas.current;

        if (element === null) {
            return;
        }

        onChange(hasInk ? element.toDataURL('image/png') : null);
    };

    const clear = () => {
        const element = canvas.current;
        const context = element?.getContext('2d');

        if (element === undefined || element === null || !context) {
            return;
        }

        context.clearRect(0, 0, element.width, element.height);
        setHasInk(false);
        onChange(null);
    };

    return (
        <div className={className}>
            <div className="relative rounded-md border border-dashed border-border bg-white">
                <canvas
                    ref={canvas}
                    className="h-40 w-full touch-none rounded-md"
                    onPointerDown={start}
                    onPointerMove={move}
                    onPointerUp={end}
                    onPointerLeave={end}
                    onPointerCancel={end}
                />
                {!hasInk && (
                    <p className="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-neutral-400">
                        Tanda tangan di sini
                    </p>
                )}
            </div>

            <div className="mt-2 flex items-center justify-between">
                <p className="text-xs text-muted-foreground">
                    Gunakan jari pada layar sentuh atau tetikus.
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={clear}
                    disabled={!hasInk}
                >
                    <Eraser className="size-4" />
                    Hapus
                </Button>
            </div>
        </div>
    );
}
