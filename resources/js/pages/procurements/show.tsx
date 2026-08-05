import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { ActivityTimeline } from '@/components/procurement/activity-timeline';
import { ApprovalCard } from '@/components/procurement/approval-card';
import { ChecklistPanel } from '@/components/procurement/checklist-panel';
import { DocumentPanel } from '@/components/procurement/document-panel';
import { PicAssignmentCard } from '@/components/procurement/pic-assignment-card';
import { StatusCard } from '@/components/procurement/status-card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatCurrency, formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import type {
    ActivityRow,
    ChecklistRow,
    DocumentTypeOption,
    Option,
    ProcurementDocumentRow,
    ProcurementRow,
    StatusOption,
} from '@/types';

type ShowProps = {
    procurement: ProcurementRow;
    detail: {
        notes: string | null;
        planning_review_note: string | null;
        planning_submitted_at: string | null;
        planning_reviewed_at: string | null;
        planning_reviewer: string | null;
        created_by: string | null;
    };
    checklists: {
        perencanaan: ChecklistRow[];
        pelaksanaan: ChecklistRow[];
    };
    documents: ProcurementDocumentRow[];
    activities: ActivityRow[];
    options: {
        progressStatuses: StatusOption[];
        planners: Option[];
        executors: Option[];
        documentTypes: DocumentTypeOption[];
    };
    can: {
        update: boolean;
        assignPic: boolean;
        updateStatus: boolean;
        updatePlanningChecklist: boolean;
        updateExecutionChecklist: boolean;
        submitPlanning: boolean;
        reviewPlanning: boolean;
        complete: boolean;
        generateDocument: boolean;
    };
};

export default function ShowProcurement({
    procurement,
    detail,
    checklists,
    documents,
    activities,
    options,
    can,
}: ShowProps) {
    const summary: Array<{ label: string; value: string }> = [
        { label: 'Direksi Pekerjaan', value: procurement.work_director },
        { label: 'Unit Tujuan', value: procurement.target_unit },
        {
            label: 'Metode Pengadaan',
            value: procurement.procurement_method ?? '—',
        },
        {
            label: 'Sumber Anggaran',
            value: procurement.budget_source ?? '—',
        },
        { label: 'Nomor PR/RO', value: procurement.pr_ro_number ?? '—' },
        { label: 'Nomor PRK', value: procurement.prk_number ?? '—' },
        {
            label: 'Nilai HPE / Anggaran',
            value: formatCurrency(procurement.hpe_value),
        },
        {
            label: 'Target Penyelesaian',
            value: formatDate(procurement.target_completion_date),
        },
        { label: 'Dibuat Oleh', value: detail.created_by ?? '—' },
        { label: 'Tanggal Dibuat', value: formatDate(procurement.created_at) },
    ];

    return (
        <>
            <Head title={procurement.number} />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow={procurement.number}
                    title={procurement.name}
                    description={`${procurement.target_unit} · ${procurement.work_director}`}
                    actions={
                        can.update && (
                            <>
                                <Button asChild variant="outline">
                                    <Link
                                        href={procurements.edit(procurement.id)}
                                    >
                                        <Pencil className="size-4" />
                                        Ubah Data
                                    </Link>
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Arsipkan pengadaan ${procurement.number}?`,
                                            )
                                        ) {
                                            router.delete(
                                                procurements.destroy(
                                                    procurement.id,
                                                ).url,
                                            );
                                        }
                                    }}
                                >
                                    <Trash2 className="size-4" />
                                    Arsipkan
                                </Button>
                            </>
                        )
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="flex min-w-0 flex-col gap-5">
                        <section className="rounded-md border border-border bg-card">
                            <header className="border-b border-border px-4 py-3">
                                <h2 className="text-sm font-semibold text-foreground">
                                    Identitas Pengadaan
                                </h2>
                            </header>

                            <dl className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                {summary.map((item) => (
                                    <div
                                        key={item.label}
                                        className="flex items-baseline justify-between gap-4 border-b border-border px-4 py-2.5 last:border-b-0 sm:[&:nth-last-child(2)]:border-b-0"
                                    >
                                        <dt className="text-sm text-muted-foreground">
                                            {item.label}
                                        </dt>
                                        <dd className="tabular text-right text-sm font-medium text-foreground">
                                            {item.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>

                            {detail.notes && (
                                <p className="border-t border-border px-4 py-3 text-sm text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        Catatan:{' '}
                                    </span>
                                    {detail.notes}
                                </p>
                            )}
                        </section>

                        <Tabs defaultValue="perencanaan">
                            <TabsList>
                                <TabsTrigger value="perencanaan">
                                    Perencanaan
                                </TabsTrigger>
                                <TabsTrigger value="pelaksanaan">
                                    Pelaksanaan
                                </TabsTrigger>
                                <TabsTrigger value="dokumen">
                                    Dokumen
                                </TabsTrigger>
                                <TabsTrigger value="aktivitas">
                                    Aktivitas
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="perencanaan">
                                <ChecklistPanel
                                    procurementId={procurement.id}
                                    title="Checklist Perencanaan"
                                    description="Dokumen yang harus dilengkapi PIC Perencana sebelum diajukan ke TL Perencanaan."
                                    rows={checklists.perencanaan}
                                    editable={can.updatePlanningChecklist}
                                    lockedReason={
                                        procurement.planning_approval_state ===
                                        'disetujui'
                                            ? 'Checklist terkunci karena perencanaan sudah disetujui.'
                                            : 'Anda tidak memiliki akses untuk mengubah checklist ini.'
                                    }
                                />
                            </TabsContent>

                            <TabsContent value="pelaksanaan">
                                <ChecklistPanel
                                    procurementId={procurement.id}
                                    title="Checklist Pelaksanaan"
                                    description="Tahapan pelaksanaan pengadaan hingga masa pemeliharaan selesai."
                                    rows={checklists.pelaksanaan}
                                    editable={can.updateExecutionChecklist}
                                    lockedReason={
                                        procurement.planning_approval_state !==
                                        'disetujui'
                                            ? 'Tahap pelaksanaan terbuka setelah dokumen perencanaan disetujui.'
                                            : 'Anda tidak memiliki akses untuk mengubah checklist ini.'
                                    }
                                />
                            </TabsContent>

                            <TabsContent value="dokumen">
                                <DocumentPanel
                                    procurementId={procurement.id}
                                    documents={documents}
                                    documentTypes={options.documentTypes}
                                    canGenerate={can.generateDocument}
                                />
                            </TabsContent>

                            <TabsContent value="aktivitas">
                                <ActivityTimeline activities={activities} />
                            </TabsContent>
                        </Tabs>
                    </div>

                    <aside className="flex flex-col gap-4">
                        <StatusCard
                            procurementId={procurement.id}
                            status={procurement.status}
                            statuses={options.progressStatuses}
                            editable={can.updateStatus}
                        />

                        <ApprovalCard
                            procurementId={procurement.id}
                            state={procurement.planning_approval_state}
                            stateLabel={procurement.planning_approval_label}
                            submittedAt={detail.planning_submitted_at}
                            reviewedAt={detail.planning_reviewed_at}
                            reviewer={detail.planning_reviewer}
                            reviewNote={detail.planning_review_note}
                            canSubmit={can.submitPlanning}
                            canReview={can.reviewPlanning}
                            canComplete={can.complete}
                            completedAt={procurement.completed_at}
                        />

                        <PicAssignmentCard
                            procurementId={procurement.id}
                            planner={procurement.planner}
                            executor={procurement.executor}
                            planners={options.planners}
                            executors={options.executors}
                            editable={can.assignPic}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}

ShowProcurement.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Daftar Pengadaan', href: procurements.index() },
    ],
};
