import { Head, Link, router } from '@inertiajs/react';
import { Archive, Eye, FileCode2, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { TemplatePreviewDialog } from '@/components/template-preview-dialog';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type TemplateRow = {
    id: number;
    document_type_id: number;
    document_type: string;
    procurement_method_id: number | null;
    procurement_method: string | null;
    name: string;
    version: number;
    body: string;
    placeholders: string[];
    is_active: boolean;
    usage_count: number;
    updated_at: string | null;
};

export default function DocumentTemplates({
    templates,
}: {
    templates: TemplateRow[];
}) {
    const [preview, setPreview] = useState<{
        title: string;
        body: string;
    } | null>(null);

    return (
        <>
            <Head title="Template Dokumen" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Data Master"
                    title="Template Dokumen"
                    description="Template standar sementara. Saat template resmi UP Kendari tersedia, cukup unggah versi baru di sini tanpa mengubah logika aplikasi."
                    actions={
                        <Button asChild>
                            <Link
                                href={masterData.documentTemplates.create().url}
                            >
                                <Plus className="size-4" />
                                Tambah Template
                            </Link>
                        </Button>
                    }
                />

                {templates.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={FileCode2}
                            title="Belum ada template"
                            description="Tambahkan template pertama agar dokumen dapat digenerate dari data pengadaan."
                            action={
                                <Button size="sm" asChild>
                                    <Link
                                        href={
                                            masterData.documentTemplates.create()
                                                .url
                                        }
                                    >
                                        Tambah Template
                                    </Link>
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Jenis Dokumen</TableHead>
                                    <TableHead>Berlaku Untuk</TableHead>
                                    <TableHead>Template</TableHead>
                                    <TableHead>Versi</TableHead>
                                    <TableHead>Placeholder</TableHead>
                                    <TableHead>Dipakai</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {templates.map((template) => (
                                    <TableRow key={template.id}>
                                        <TableCell>
                                            {template.document_type}
                                        </TableCell>
                                        <TableCell>
                                            {template.procurement_method ?? (
                                                <span className="text-muted-foreground">
                                                    Semua metode
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {template.name}
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                Diperbarui{' '}
                                                {formatDateTime(
                                                    template.updated_at,
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="tabular">
                                            v{template.version}
                                        </TableCell>
                                        <TableCell className="tabular">
                                            {template.placeholders.length}
                                        </TableCell>
                                        <TableCell className="tabular text-muted-foreground">
                                            {template.usage_count}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={
                                                    template.is_active
                                                        ? 'inline-flex items-center rounded-sm bg-status-selesai-surface px-2 py-0.5 text-xs font-medium text-status-selesai'
                                                        : 'inline-flex items-center rounded-sm bg-status-pending-surface px-2 py-0.5 text-xs font-medium text-status-pending'
                                                }
                                            >
                                                {template.is_active
                                                    ? 'Aktif'
                                                    : 'Arsip'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setPreview({
                                                        title: `${template.name} v${template.version}`,
                                                        body: template.body,
                                                    })
                                                }
                                            >
                                                <Eye className="size-3.5" />
                                                Pratinjau
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        masterData.documentTemplates.edit(
                                                            template.id,
                                                        ).url
                                                    }
                                                >
                                                    <Pencil className="size-3.5" />
                                                    Ubah
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            `Arsipkan template ${template.name} v${template.version}? Dokumen yang sudah digenerate tidak berubah.`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            masterData.documentTemplates.destroy(
                                                                template.id,
                                                            ).url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Archive className="size-3.5" />
                                                Arsipkan
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <TemplatePreviewDialog
                title={preview?.title ?? ''}
                body={preview?.body ?? null}
                onClose={() => setPreview(null)}
            />
        </>
    );
}

DocumentTemplates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Template Dokumen',
            href: masterData.documentTemplates.index(),
        },
    ],
};
