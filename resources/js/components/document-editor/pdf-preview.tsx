import { LoaderCircle, RefreshCw, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type Draft = { title: string; body: string };

type Rendered = {
    draft: Draft;
    url: string | null;
    error: string | null;
};

/**
 * The XSRF header Laravel accepts on a same-origin request, read from the
 * cookie it sets. Modern browsers are already let through on Sec-Fetch-Site;
 * this covers the ones that do not send it.
 */
function xsrfHeader(): Record<string, string> {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match === null
        ? {}
        : { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) };
}

/** Turn a failed response into a sentence the author can act on. */
async function failureMessage(response: Response): Promise<string> {
    if (response.status === 422) {
        try {
            const payload = (await response.json()) as {
                errors?: Record<string, string[]>;
            };
            const first = Object.values(payload.errors ?? {})[0]?.[0];

            if (first) {
                return first;
            }
        } catch {
            // Fall through to the generic message.
        }

        return 'Judul dan isi dokumen wajib diisi sebelum pratinjau dibuat.';
    }

    if (response.status === 403) {
        return 'Anda tidak berwenang membuat pratinjau dokumen ini.';
    }

    return 'Pratinjau PDF gagal dimuat. Coba lagi.';
}

/**
 * The document exactly as its PDF will print, including unsaved edits.
 *
 * The draft is sent to the server, rendered by the same engine as the download
 * and shown inline. It is snapshotted when the tab opens and re-rendered only
 * on request, so typing elsewhere on the page does not rebuild it every key.
 */
export function PdfPreview({
    endpoint,
    title,
    body,
}: {
    endpoint: string;
    title: string;
    body: string;
}) {
    const [draft, setDraft] = useState<Draft>(() => ({ title, body }));
    const [rendered, setRendered] = useState<Rendered | null>(null);

    const loading = rendered === null || rendered.draft !== draft;
    const stale = draft.title !== title || draft.body !== body;

    useEffect(() => {
        const controller = new AbortController();
        let objectUrl: string | null = null;

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/pdf, application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...xsrfHeader(),
            },
            body: JSON.stringify(draft),
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(await failureMessage(response));
                }

                objectUrl = URL.createObjectURL(await response.blob());
                setRendered({ draft, url: objectUrl, error: null });
            })
            .catch((error: unknown) => {
                if (controller.signal.aborted) {
                    return;
                }

                setRendered({
                    draft,
                    url: null,
                    error:
                        error instanceof Error
                            ? error.message
                            : 'Pratinjau PDF gagal dimuat. Coba lagi.',
                });
            });

        return () => {
            controller.abort();

            if (objectUrl !== null) {
                URL.revokeObjectURL(objectUrl);
            }
        };
    }, [endpoint, draft]);

    const refresh = () => setDraft({ title, body });

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">
                    {stale
                        ? 'Ada perubahan sejak pratinjau dibuat. Perbarui untuk melihatnya.'
                        : 'Tampilan persis seperti PDF yang diunduh, termasuk perubahan yang belum disimpan.'}
                </p>
                <Button
                    type="button"
                    size="sm"
                    variant={stale ? 'default' : 'outline'}
                    onClick={refresh}
                    disabled={loading}
                >
                    <RefreshCw className="size-3.5" />
                    Perbarui Pratinjau
                </Button>
            </div>

            <div className="relative h-[80vh] overflow-hidden rounded-md border border-border bg-muted/40">
                {loading ? (
                    <div className="flex h-full items-center justify-center gap-2 text-sm text-muted-foreground">
                        <LoaderCircle className="size-4 animate-spin" />
                        Menyusun pratinjau PDF…
                    </div>
                ) : rendered?.error ? (
                    <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                        <TriangleAlert className="size-6 text-destructive" />
                        <p className="max-w-md text-sm">{rendered.error}</p>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={refresh}
                        >
                            <RefreshCw className="size-3.5" />
                            Coba Lagi
                        </Button>
                    </div>
                ) : (
                    <iframe
                        src={`${rendered?.url ?? ''}#view=FitH`}
                        title="Pratinjau PDF"
                        className="h-full w-full bg-white"
                    />
                )}
            </div>
        </div>
    );
}
