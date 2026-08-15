import { Head, router } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementForm } from '@/components/procurement-form';
import type {
    ProcurementFormOptions,
    ProcurementFormValues,
} from '@/components/procurement-form';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';

type EditableProcurement = ProcurementFormValues & {
    id: number;
    number: string;
};

export default function EditProcurement({
    procurement,
    options,
    nextNumbers,
}: {
    procurement: EditableProcurement;
    options: ProcurementFormOptions;
    nextNumbers: Record<number, string>;
}) {
    return (
        <>
            <Head title={`Ubah ${procurement.number}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={procurement.number}
                    title="Ubah Data Pengadaan"
                    description="Perubahan pada data identitas akan tercatat pada histori aktivitas pengadaan."
                    actions={
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(
                                    procurements.show(procurement.id).url,
                                )
                            }
                        >
                            Kembali
                        </Button>
                    }
                />

                <ProcurementForm
                    options={options}
                    initialValues={procurement}
                    nextNumbers={nextNumbers}
                    submitLabel="Simpan Perubahan"
                    onSubmit={(form) =>
                        form.put(procurements.update(procurement.id).url)
                    }
                    onCancel={() =>
                        router.visit(procurements.show(procurement.id).url)
                    }
                />
            </div>
        </>
    );
}

EditProcurement.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengadaan', href: procurements.index() },
    ],
};
