import { LoaderCircle } from 'lucide-react';
import type { PDFDocumentProxy } from 'pdfjs-dist';
import { useEffect, useRef, useState } from 'react';

type Sheet = { number: number; ratio: number };

/**
 * A PDF drawn page by page onto canvases with pdf.js.
 *
 * For browsers that cannot show a PDF inside the page (Chrome on Android,
 * anything on iOS). The library is loaded only when this viewer is used, and
 * a page is drawn only once it scrolls near the screen, so a long contract
 * does not eat a phone's memory.
 */
export function PdfCanvasViewer({
    url,
    onFailure,
}: {
    url: string;
    onFailure: () => void;
}) {
    const [pdf, setPdf] = useState<PDFDocumentProxy | null>(null);
    const [sheets, setSheets] = useState<Sheet[]>([]);

    useEffect(() => {
        let cancelled = false;
        let loaded: PDFDocumentProxy | null = null;

        (async () => {
            const pdfjs = await import('pdfjs-dist');
            const worker =
                await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
            pdfjs.GlobalWorkerOptions.workerSrc = worker.default;

            loaded = await pdfjs.getDocument(url).promise;

            const found: Sheet[] = [];

            for (let number = 1; number <= loaded.numPages; number++) {
                const page = await loaded.getPage(number);
                const viewport = page.getViewport({ scale: 1 });
                found.push({ number, ratio: viewport.height / viewport.width });
            }

            if (!cancelled) {
                setPdf(loaded);
                setSheets(found);
            }
        })().catch(() => {
            if (!cancelled) {
                onFailure();
            }
        });

        return () => {
            cancelled = true;
            void loaded?.destroy();
        };
    }, [url, onFailure]);

    if (pdf === null) {
        return (
            <div className="flex h-full items-center justify-center gap-2 text-sm text-muted-foreground">
                <LoaderCircle className="size-4 animate-spin" />
                Memuat halaman PDF…
            </div>
        );
    }

    return (
        <div className="h-full overflow-y-auto bg-muted/60 p-2 sm:p-4">
            <div className="mx-auto flex max-w-3xl flex-col gap-3">
                {sheets.map((sheet) => (
                    <PdfPage
                        key={sheet.number}
                        pdf={pdf}
                        sheet={sheet}
                        total={sheets.length}
                    />
                ))}
            </div>
        </div>
    );
}

/**
 * One page, sized from the start so the scrollbar is right, and drawn the
 * first time it comes near the screen.
 */
function PdfPage({
    pdf,
    sheet,
    total,
}: {
    pdf: PDFDocumentProxy;
    sheet: Sheet;
    total: number;
}) {
    const frame = useRef<HTMLDivElement>(null);
    const canvas = useRef<HTMLCanvasElement>(null);
    const [drawn, setDrawn] = useState(false);

    useEffect(() => {
        const element = frame.current;

        if (element === null || drawn) {
            return;
        }

        let cancelled = false;

        const draw = async () => {
            const target = canvas.current;

            if (target === null) {
                return;
            }

            const page = await pdf.getPage(sheet.number);
            const base = page.getViewport({ scale: 1 });
            // Sharp on high density screens without blowing up memory.
            const density = Math.min(window.devicePixelRatio || 1, 2);
            const viewport = page.getViewport({
                scale: (element.clientWidth / base.width) * density,
            });

            target.width = Math.floor(viewport.width);
            target.height = Math.floor(viewport.height);

            await page.render({ canvas: target, viewport }).promise;

            if (!cancelled) {
                setDrawn(true);
            }
        };

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    observer.disconnect();
                    void draw();
                }
            },
            { rootMargin: '600px 0px' },
        );
        observer.observe(element);

        return () => {
            cancelled = true;
            observer.disconnect();
        };
    }, [pdf, sheet.number, drawn]);

    return (
        <div
            ref={frame}
            className="relative w-full bg-white shadow-sm"
            style={{ aspectRatio: `1 / ${sheet.ratio}` }}
        >
            <canvas
                ref={canvas}
                className="block h-full w-full"
                aria-label={`Halaman ${sheet.number} dari ${total}`}
            />
            {!drawn && (
                <div className="absolute inset-0 flex items-center justify-center">
                    <LoaderCircle className="size-5 animate-spin text-muted-foreground" />
                </div>
            )}
        </div>
    );
}
