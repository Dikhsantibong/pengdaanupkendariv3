import { useState } from 'react';
import { PdfPreview } from '@/components/document-editor/pdf-preview';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    fillSampleValues,
    isLandscape,
    renderPreviewHtml,
} from '@/lib/template-document';
import masterData from '@/routes/master-data';

/**
 * Show a template body filled with sample procurement data: as its PDF will
 * print, or as a quick page in the browser.
 */
export function TemplatePreviewDialog({
    title,
    body,
    documentStylesheet,
    documentTypeId,
    onClose,
}: {
    title: string;
    body: string | null;
    documentStylesheet?: string;
    documentTypeId?: number | null;
    onClose: () => void;
}) {
    const landscape = body !== null && isLandscape(body);
    const [tab, setTab] = useState('pdf');

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
                className={`flex h-[94vh] flex-col p-4 sm:p-6 ${landscape ? 'sm:max-w-6xl' : 'sm:max-w-5xl'}`}
            >
                <DialogHeader className="shrink-0">
                    <DialogTitle>Pratinjau: {title}</DialogTitle>
                    <DialogDescription>
                        Dokumen dengan contoh data pengadaan.
                    </DialogDescription>
                </DialogHeader>

                {body !== null && (
                    <Tabs
                        defaultValue="pdf"
                        value={tab}
                        onValueChange={setTab}
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <TabsList className="shrink-0 self-start">
                            <TabsTrigger value="pdf">Pratinjau PDF</TabsTrigger>
                            <TabsTrigger value="html">
                                Tampilan Cepat
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="pdf" className="min-h-0 flex-1">
                            {tab === 'pdf' && (
                                <PdfPreview
                                    endpoint={
                                        masterData.documentTemplates.pdfPreview()
                                            .url
                                    }
                                    title={title}
                                    body={fillSampleValues(body)}
                                    documentTypeId={documentTypeId}
                                />
                            )}
                        </TabsContent>
                        <TabsContent
                            value="html"
                            className="flex min-h-0 flex-1 justify-center overflow-hidden rounded-md border border-border bg-muted/40 p-2 sm:p-4"
                        >
                            <iframe
                                title="Pratinjau Template Dokumen"
                                srcDoc={renderPreviewHtml(
                                    body,
                                    documentStylesheet,
                                )}
                                className={`h-full w-full border border-border bg-white shadow-sm ${landscape ? 'max-w-[297mm]' : 'max-w-[210mm]'}`}
                            />
                        </TabsContent>
                    </Tabs>
                )}

                <DialogFooter className="shrink-0">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
