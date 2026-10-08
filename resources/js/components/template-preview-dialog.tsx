import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { isLandscape, renderPreviewHtml } from '@/lib/template-document';

/**
 * Show a template body filled with sample procurement data.
 */
export function TemplatePreviewDialog({
    title,
    body,
    documentStylesheet,
    onClose,
}: {
    title: string;
    body: string | null;
    documentStylesheet?: string;
    onClose: () => void;
}) {
    const landscape = body !== null && isLandscape(body);

    return (
        <Dialog
            open={body !== null}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className={`flex h-[92vh] flex-col p-4 sm:p-6 ${landscape ? 'sm:max-w-6xl' : 'sm:max-w-5xl'}`}
            >
                <DialogHeader className="shrink-0">
                    <DialogTitle>Pratinjau: {title}</DialogTitle>
                    <DialogDescription>
                        Tampilan dokumen dengan contoh data pengadaan.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex min-h-0 w-full flex-1 justify-center overflow-hidden rounded-md border border-border bg-muted/40 p-2 sm:p-4">
                    {body !== null && (
                        <iframe
                            title="Pratinjau Template Dokumen"
                            srcDoc={renderPreviewHtml(body, documentStylesheet)}
                            className={`h-full w-full border border-border bg-white shadow-sm ${landscape ? 'max-w-[297mm]' : 'max-w-[210mm]'}`}
                        />
                    )}
                </div>

                <DialogFooter className="shrink-0">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
