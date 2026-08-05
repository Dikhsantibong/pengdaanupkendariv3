import { Head, router } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementForm } from '@/components/procurement-form';
import type { ProcurementFormOptions } from '@/components/procurement-form';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';

export default function CreateProcurement({
    options,
    nextNumber,
}: {
    options: ProcurementFormOptions;
    nextNumber: string;
}) {
    return (
        <>
            <Head title="Buat Perencanaan Pengadaan" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Form Input Awal"
                    title="Buat Perencanaan Pengadaan"
                    description="Data pada form ini menjadi identitas utama pengadaan dan tampil pada dashboard, monitoring, serta laporan."
                    actions={
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(procurements.index().url)
                            }
                        >
                            Kembali
                        </Button>
                    }
                />

                <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 px-4 py-3">
                    <span className="section-label">
                        Nomor pengadaan otomatis
                    </span>
                    <span className="tabular text-sm font-semibold text-foreground">
                        {nextNumber}
                    </span>
                </div>

                <ProcurementForm
                    options={options}
                    withPlanner
                    submitLabel="Simpan Pengadaan"
                    onSubmit={(form) => form.post(procurements.store().url)}
                    onCancel={() => router.visit(procurements.index().url)}
                />
            </div>
        </>
    );
}

CreateProcurement.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengadaan', href: procurements.index() },
        { title: 'Buat Perencanaan Pengadaan', href: procurements.create() },
    ],
};
