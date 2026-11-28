import { Head, router, usePage } from '@inertiajs/react';
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
import type { Auth } from '@/types';

export default function CreateProcurement({
    options,
    proposal,
    withPlanner,
}: {
    options: ProcurementFormOptions;
    proposal?: ProposalMode;
    /** Only TL ICC appoints the planning PIC, here or on the PIC board. */
    withPlanner: boolean;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isProposalForm = proposal?.enabled === true;
    const proposeOnly = auth.permissions.proposeOnly;
    const backUrl = proposeOnly
        ? proposals.index().url
        : procurements.index().url;

    return (
        <>
            <Head
                title={
                    proposeOnly
                        ? 'Usulkan Pekerjaan'
                        : 'Buat Perencanaan Pengadaan'
                }
            />

            <div className="flex w-full flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={
                        proposeOnly ? 'Usulan Pekerjaan' : 'Form Input Awal'
                    }
                    title={
                        proposeOnly
                            ? 'Usulkan Pekerjaan'
                            : 'Buat Perencanaan Pengadaan'
                    }
                    description={
                        isProposalForm
                            ? 'Lengkapi detail usulan pekerjaan dan lampiran pendukung.'
                            : 'Lengkapi data identitas awal pengadaan.'
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
                    withPlanner={withPlanner}
                    proposal={proposal}
                    submitLabel={
                        proposeOnly ? 'Kirim Usulan' : 'Simpan Pengadaan'
                    }
                    onSubmit={(form) =>
                        form.post(procurements.store().url, {
                            forceFormData: isProposalForm,
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
