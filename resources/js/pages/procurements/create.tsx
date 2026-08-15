import { Head, router } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementForm } from '@/components/procurement-form';
import type { ProcurementFormOptions } from '@/components/procurement-form';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';

export default function CreateProcurement({
    options,
    nextNumbers,
}: {
    options: ProcurementFormOptions;
    nextNumbers: Record<number, string>;
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

                <ProcurementForm
                    options={options}
                    withPlanner
                    nextNumbers={nextNumbers}
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
