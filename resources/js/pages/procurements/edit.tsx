import { Head, router } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementForm } from '@/components/procurement-form';
import type {
    ProcurementFormOptions,
    ProcurementFormValues,
    ProposalMode,
} from '@/components/procurement-form';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import proposals from '@/routes/proposals';

type EditableProcurement = ProcurementFormValues & {
    id: number;
    number: string;
};

export default function EditProcurement({
    procurement,
    options,
    nextNumbers,
    proposal,
}: {
    procurement: EditableProcurement;
    options: ProcurementFormOptions;
    nextNumbers: Record<number, string>;
    proposal?: ProposalMode;
}) {
    const isProposal = proposal?.enabled === true;
    // A proposer revising their proposal goes back to it to resubmit.
    const backUrl = isProposal
        ? proposals.show(procurement.id).url
        : procurements.show(procurement.id).url;

    return (
        <>
            <Head title={`Ubah ${procurement.number}`} />

            <div className="flex w-full flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={procurement.number}
                    title={
                        isProposal ? 'Perbaiki Usulan' : 'Ubah Data Pengadaan'
                    }
                    description={
                        isProposal
                            ? 'Perbaiki usulan sesuai catatan Direksi Pekerjaan, lalu ajukan ulang dari halaman usulan.'
                            : 'Perubahan pada data identitas akan tercatat pada histori aktivitas pengadaan.'
                    }
                    actions={
                        <Button
                            variant="outline"
                            onClick={() => router.visit(backUrl)}
                        >
                            Kembali
                        </Button>
                    }
                />

                <ProcurementForm
                    options={options}
                    initialValues={procurement}
                    proposal={proposal}
                    nextNumbers={nextNumbers}
                    submitLabel="Simpan Perubahan"
                    onSubmit={(form) =>
                        form.put(procurements.update(procurement.id).url)
                    }
                    onCancel={() => router.visit(backUrl)}
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
