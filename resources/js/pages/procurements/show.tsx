import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, FileText, Pencil, Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { AmendmentTab } from '@/components/procurement/amendment-tab';
import type { Amendment } from '@/components/procurement/amendment-tab';
import { ApprovalCard } from '@/components/procurement/approval-card';
import { ApprovalHistoryTable } from '@/components/procurement/approval-history';
import type { ApprovalHistory } from '@/components/procurement/approval-history';
import { ChecklistPanel } from '@/components/procurement/checklist-panel';
import {
    ExecutionApproval,
    FinanceStatus,
} from '@/components/procurement/finance-card';
import type { FinanceDetail } from '@/components/procurement/finance-card';
import { IdentityPanel } from '@/components/procurement/identity-panel';
import { PicAssignmentCard } from '@/components/procurement/pic-assignment-card';
import { ProposalDocuments } from '@/components/procurement/proposal-documents';
import { StatusCard } from '@/components/procurement/status-card';
import { DownloadAllButton } from '@/components/procurement-table';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import type {
    ChecklistRow,
    PartnerCandidate,
    DocumentTypeOption,
    Option,
    ProcurementRow,
    ProposalDocument,
    StatusOption,
} from '@/types';

type ShowProps = {
    procurement: ProcurementRow;
    detail: {
        notes: string | null;
        /** When the current, automatic status applies. */
        status_rule: string | null;
        planning_review_note: string | null;
        planning_submitted_at: string | null;
        planning_reviewed_at: string | null;
        planning_reviewer: string | null;
        created_by: string | null;
        pending_required_planning: string[];
        is_planner: boolean;
        planner_name: string | null;
        planning_revision: number;
        missing_planning_data: string[];
        missing_identity_data: string[];
        finance: FinanceDetail;
        proposal_documents: {
            proposer: string;
            documents: ProposalDocument[];
        } | null;
    };
    checklists: {
        perencanaan: ChecklistRow[];
        pelaksanaan: ChecklistRow[];
        keuangan: ChecklistRow[];
    };
    approvalHistory: ApprovalHistory;
    amendments: Amendment[];
    partnerCandidates: PartnerCandidate[];
    directAppointment: boolean;
    options: {
        progressStatuses: StatusOption[];
        planners: Option[];
        executors: Option[];
        contractTypes: Option[];
        documentTypes: DocumentTypeOption[];
    };
    can: {
        update: boolean;
        startAmendment: boolean;
        assignPic: boolean;
        assignPlanner: boolean;
        assignExecutor: boolean;
        updateStatus: boolean;
        updatePlanningIdentity: boolean;
        updatePlanningChecklist: boolean;
        updateExecutionChecklist: boolean;
        updateFinanceChecklist: boolean;
        submitExecution: boolean;
        reviewExecution: boolean;
        completePayment: boolean;
        submitPlanning: boolean;
        reviewPlanning: boolean;
        revertPlanningRejection: boolean;
        complete: boolean;
        generateDocument: boolean;
        manageExecutionDocuments: boolean;
        editDocument: boolean;
    };
};

export default function ShowProcurement({
    procurement,
    detail,
    checklists,
    approvalHistory,
    amendments,
    partnerCandidates,
    directAppointment,
    options,
    can,
}: ShowProps) {
    const { url } = usePage();
    const linkedTab = new URLSearchParams(url.split('?')[1] ?? '').get('tab');
    // Renamed tabs: Keuangan became Penyerahan and Pembayaran, Aktivitas
    // became Riwayat Approval. Older links still open the right place.
    const requestedTab =
        { keuangan: 'penyerahan', aktivitas: 'riwayat' }[linkedTab ?? ''] ??
        linkedTab;
    // Open on the stage the job is in, unless a link asked for a tab.
    const initialTab =
        requestedTab ??
        {
            usulan: detail.proposal_documents ? 'usulan' : 'perencanaan',
            perencanaan: 'perencanaan',
            pelaksanaan: 'pelaksanaan',
            keuangan: 'penyerahan',
            selesai: detail.finance.paid_at ? 'pembayaran' : 'pelaksanaan',
        }[procurement.current_stage];

    const financeRows = (section: 'penyerahan' | 'pembayaran') =>
        checklists.keuangan.filter(
            (row) => (row.section ?? 'pembayaran') === section,
        );

    const requirementsNotice =
        detail.finance.requirements_waiting_for.length > 0 ? (
            <p className="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm">
                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                <span>
                    Syarat penyerahan dan pembayaran mengikuti kontrak pengadaan
                    ini (SPK Barang, SPK Jasa, Surat Perjanjian, atau Surat
                    Pesanan). Lengkapi dulu{' '}
                    <b>
                        {detail.finance.requirements_waiting_for.join(' dan ')}
                    </b>{' '}
                    lewat tombol Ubah Data.
                </span>
            </p>
        ) : null;

    const requirementsSource = (section: 'penyerahan' | 'pembayaran') =>
        detail.finance.requirements_waiting_for.length > 0 ? null : (
            <p className="flex items-start gap-2 rounded-md border border-border bg-muted/40 px-4 py-2.5 text-xs text-muted-foreground">
                <FileText className="mt-0.5 size-3.5 shrink-0" />
                <span>
                    {detail.finance.requirements_source[section] ? (
                        <>
                            Daftar ini dibaca langsung dari pasal{' '}
                            {section === 'penyerahan'
                                ? 'penyerahan'
                                : 'cara pembayaran'}{' '}
                            pada{' '}
                            <b className="text-foreground">
                                {detail.finance.requirements_source[section]}
                            </b>{' '}
                            dan ikut berubah bila dokumen kontrak itu diubah,
                            sampai pembayaran dicatat.
                        </>
                    ) : (
                        <>
                            Dokumen kontrak pengadaan ini belum dibuat atau
                            daftarnya tidak terbaca, jadi yang dipakai adalah
                            syarat dari template standar. Begitu dokumen kontrak
                            dibuat, daftar ini mengikuti isi kontrak.
                        </>
                    )}
                </span>
            </p>
        );

    const financeLockedReason =
        detail.finance.paid_at !== null
            ? 'Checklist terkunci karena pembayaran sudah dicatat.'
            : detail.finance.submitted_at === null
              ? 'Tahap ini terbuka setelah pelaksanaan disetujui TL Pengadaan.'
              : 'Hanya Keuangan yang dapat mengubah checklist ini.';

    // Whoever has the Edit Identitas button changes the data there; the
    // inline pickers are only for the planning PIC, who has no such form.
    const editsInline = can.updatePlanningIdentity && !can.update;

    return (
        <>
            <Head title={procurement.number} />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow={procurement.number}
                    title={procurement.name}
                    description={`${procurement.target_unit} · ${procurement.work_director}`}
                    actions={
                        <>
                            <DownloadAllButton
                                procurementId={procurement.id}
                                label="Unduh Semua Dokumen (ZIP)"
                            />
                            {can.update && (
                                <>
                                    <Button asChild variant="outline">
                                        <Link
                                            href={procurements.edit(
                                                procurement.id,
                                            )}
                                        >
                                            <Pencil className="size-4" />
                                            Edit Identitas Pengadaan
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
                            )}
                        </>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="flex min-w-0 flex-col gap-5">
                        <IdentityPanel
                            procurement={procurement}
                            notes={detail.notes}
                            editsInline={editsInline}
                            contractTypes={options.contractTypes}
                            candidateCount={partnerCandidates.length}
                        />

                        <Tabs defaultValue={initialTab}>
                            <TabsList className="h-auto flex-wrap">
                                {detail.proposal_documents && (
                                    <TabsTrigger value="usulan">
                                        {detail.proposal_documents.proposer}
                                    </TabsTrigger>
                                )}
                                <TabsTrigger value="perencanaan">
                                    Perencanaan
                                </TabsTrigger>
                                <TabsTrigger value="pelaksanaan">
                                    Pelaksanaan
                                </TabsTrigger>
                                <TabsTrigger value="penyerahan">
                                    Penyerahan
                                </TabsTrigger>
                                <TabsTrigger value="pembayaran">
                                    Pembayaran
                                </TabsTrigger>
                                <TabsTrigger value="amandemen">
                                    Amandemen
                                    {amendments.length > 0 &&
                                        ` (${amendments.length})`}
                                </TabsTrigger>
                                <TabsTrigger value="riwayat">
                                    Riwayat Approval
                                </TabsTrigger>
                            </TabsList>

                            {detail.proposal_documents && (
                                <TabsContent value="usulan">
                                    <ProposalDocuments
                                        procurementId={procurement.id}
                                        proposer={
                                            detail.proposal_documents.proposer
                                        }
                                        documents={
                                            detail.proposal_documents.documents
                                        }
                                        canManage={can.editDocument}
                                    />
                                </TabsContent>
                            )}

                            <TabsContent
                                value="perencanaan"
                                className="space-y-3"
                            >
                                {detail.missing_planning_data.length > 0 && (
                                    <p className="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm">
                                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                        <span>
                                            Usulan ini belum bisa dibuatkan
                                            dokumen perencanaan. Lengkapi dulu{' '}
                                            <b>
                                                {detail.missing_planning_data.join(
                                                    ', ',
                                                )}
                                            </b>{' '}
                                            lewat tombol Ubah Data.
                                        </span>
                                    </p>
                                )}
                                {detail.missing_identity_data.length > 0 && (
                                    <p className="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm">
                                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                        <span>
                                            Data identitas pengadaan belum
                                            lengkap:{' '}
                                            <b>
                                                {detail.missing_identity_data.join(
                                                    ', ',
                                                )}
                                            </b>
                                            . Perencanaan belum bisa diajukan
                                            sebelum data ini dilengkapi lewat
                                            Edit Identitas Pengadaan.
                                        </span>
                                    </p>
                                )}
                                <ChecklistPanel
                                    procurementId={procurement.id}
                                    description="Dokumen yang harus dilengkapi PIC Perencana sebelum diajukan ke TL ICC."
                                    rows={checklists.perencanaan}
                                    editable={can.updatePlanningChecklist}
                                    canManageDocuments={can.generateDocument}
                                    lockedReason={
                                        procurement.planning_approval_state ===
                                        'disetujui'
                                            ? 'Checklist terkunci karena perencanaan sudah disetujui.'
                                            : 'Anda tidak memiliki akses untuk mengubah checklist ini.'
                                    }
                                    partnerCandidates={partnerCandidates}
                                    candidatesEditable={
                                        can.updatePlanningIdentity
                                    }
                                    directAppointment={directAppointment}
                                />
                            </TabsContent>

                            <TabsContent
                                value="pelaksanaan"
                                className="space-y-3"
                            >
                                <ChecklistPanel
                                    procurementId={procurement.id}
                                    description="Tahapan pelaksanaan pengadaan hingga masa pemeliharaan selesai."
                                    rows={checklists.pelaksanaan}
                                    editable={can.updateExecutionChecklist}
                                    partnerCandidates={partnerCandidates}
                                    canManageDocuments={
                                        can.manageExecutionDocuments
                                    }
                                    lockedReason={
                                        procurement.planning_approval_state !==
                                        'disetujui'
                                            ? 'Tahap pelaksanaan terbuka setelah dokumen perencanaan disetujui.'
                                            : procurement.execution_approval_state ===
                                                'menunggu_persetujuan'
                                              ? 'Checklist terkunci selama pelaksanaan ditinjau TL Pengadaan.'
                                              : 'Anda tidak memiliki akses untuk mengubah checklist ini.'
                                    }
                                />
                                <ExecutionApproval
                                    procurementId={procurement.id}
                                    finance={detail.finance}
                                    canSubmit={can.submitExecution}
                                    canReview={can.reviewExecution}
                                    planningApproved={
                                        procurement.planning_approval_state ===
                                        'disetujui'
                                    }
                                />
                            </TabsContent>

                            <TabsContent
                                value="penyerahan"
                                className="space-y-3"
                            >
                                {requirementsNotice}
                                {requirementsSource('penyerahan')}
                                <ChecklistPanel
                                    procurementId={procurement.id}
                                    description="Kelengkapan dokumen penyerahan barang / pekerjaan sesuai pasal penyerahan pada kontrak (SPK, Surat Perjanjian, atau Lampiran Surat Pesanan)."
                                    rows={financeRows('penyerahan')}
                                    editable={can.updateFinanceChecklist}
                                    canManageDocuments={
                                        can.updateFinanceChecklist
                                    }
                                    lockedReason={financeLockedReason}
                                />
                            </TabsContent>

                            <TabsContent
                                value="pembayaran"
                                className="space-y-3"
                            >
                                <FinanceStatus
                                    procurementId={procurement.id}
                                    finance={detail.finance}
                                    canComplete={can.completePayment}
                                />
                                {requirementsNotice}
                                {requirementsSource('pembayaran')}
                                <ChecklistPanel
                                    procurementId={procurement.id}
                                    description="Kelengkapan dokumen permintaan pembayaran sesuai pasal cara pembayaran pada kontrak, diperiksa Keuangan sebelum pengadaan ditutup."
                                    rows={financeRows('pembayaran')}
                                    editable={can.updateFinanceChecklist}
                                    canManageDocuments={
                                        can.updateFinanceChecklist
                                    }
                                    lockedReason={financeLockedReason}
                                />
                            </TabsContent>

                            <TabsContent value="amandemen">
                                <AmendmentTab
                                    procurementId={procurement.id}
                                    amendments={amendments}
                                    canStart={can.startAmendment}
                                />
                            </TabsContent>

                            <TabsContent value="riwayat">
                                <ApprovalHistoryTable
                                    history={approvalHistory}
                                />
                            </TabsContent>
                        </Tabs>
                    </div>

                    <aside className="flex flex-col gap-4">
                        <StatusCard
                            procurementId={procurement.id}
                            status={procurement.status}
                            rule={detail.status_rule}
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
                            isPlanner={detail.is_planner}
                            plannerName={detail.planner_name}
                            revision={detail.planning_revision}
                            pendingRequired={detail.pending_required_planning}
                            canReview={can.reviewPlanning}
                            canRevert={can.revertPlanningRejection}
                            canComplete={can.complete}
                            completedAt={procurement.completed_at}
                        />

                        <PicAssignmentCard
                            procurementId={procurement.id}
                            planner={procurement.planner}
                            executor={procurement.executor}
                            planners={options.planners}
                            executors={options.executors}
                            canAssignPlanner={can.assignPlanner}
                            canAssignExecutor={can.assignExecutor}
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
