import { Head, router } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementForm } from '@/components/procurement-form';
import type {
    ProcurementFormOptions,
    ProposalMode,
} from '@/components/procurement-form';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import proposals from '@/routes/proposals';

export default function CreateProcurement({
    options,
    nextNumbers,
    proposal,
}: {
    options: ProcurementFormOptions;
    nextNumbers: Record<number, string>;
    proposal?: ProposalMode;
}) {
    const isProposal = proposal?.enabled === true;
    const backUrl = isProposal
        ? proposals.index().url
        : procurements.index().url;

    return (
        <>
            <Head
                title={
                    isProposal
                        ? 'Usulkan Pekerjaan'
                        : 'Buat Perencanaan Pengadaan'
                }
            />

            <div className="flex w-full flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={
                        isProposal ? 'Usulan Pekerjaan' : 'Form Input Awal'
                    }
                    title={
                        isProposal
                            ? 'Usulkan Pekerjaan'
                            : 'Buat Perencanaan Pengadaan'
                    }
                    description={
                        isProposal
                            ? 'Isi kebutuhan pekerjaan dan lampirkan dokumen pendukung. Usulan akan ditinjau dan disetujui Direksi Pekerjaan bidang Anda sebelum diteruskan ke perencanaan.'
                            : 'Data pada form ini menjadi identitas utama pengadaan dan tampil pada dashboard, monitoring, serta laporan.'
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
                    withPlanner
                    proposal={proposal}
                    nextNumbers={nextNumbers}
                    submitLabel={
                        isProposal ? 'Kirim Usulan' : 'Simpan Pengadaan'
                    }
                    onSubmit={(form) =>
                        form.post(procurements.store().url, {
                            forceFormData: isProposal,
                        })
                    }
                    onCancel={() => router.visit(backUrl)}
                />
            </div>
        </>
    );
}

CreateProcurement.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengadaan', href: procurements.index() },
        { title: 'Buat / Usulkan Pekerjaan', href: procurements.create() },
    ],
};
