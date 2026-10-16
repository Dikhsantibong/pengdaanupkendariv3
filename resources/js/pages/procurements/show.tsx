import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, FileText, Pencil, Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { ApprovalCard } from '@/components/procurement/approval-card';
import { ApprovalHistoryTable } from '@/components/procurement/approval-history';
import type { ApprovalHistory } from '@/components/procurement/approval-history';
import { ChecklistPanel } from '@/components/procurement/checklist-panel';
import {
    FinanceHandover,
    FinanceStatus,
} from '@/components/procurement/finance-card';
import type { FinanceDetail } from '@/components/procurement/finance-card';
import { PicAssignmentCard } from '@/components/procurement/pic-assignment-card';
import {
    ContractTypePicker,
    TargetDateField,
} from '@/components/procurement/planning-identity-fields';
import { ProposalDocuments } from '@/components/procurement/proposal-documents';
import { StatusCard } from '@/components/procurement/status-card';
import { DownloadAllButton } from '@/components/procurement-table';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatCurrency, formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import type {
    ChecklistRow,
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
    options: {
        progressStatuses: StatusOption[];
        planners: Option[];
        executors: Option[];
        contractTypes: Option[];
        documentTypes: DocumentTypeOption[];
    };
    can: {
        update: boolean;
        assignPic: boolean;
        updateStatus: boolean;
        updatePlanningIdentity: boolean;
        updatePlanningChecklist: boolean;
        updateExecutionChecklist: boolean;
        updateFinanceChecklist: boolean;
        submitToFinance: boolean;
        completePayment: boolean;
        submitPlanning: boolean;
        reviewPlanning: boolean;
        revertPlanningRejection: boolean;
        complete: boolean;
        generateDocument: boolean;
        editDocument: boolean;
    };
};

export default function ShowProcurement({
    procurement,
    detail,
    checklists,
    approvalHistory,
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
              ? 'Tahap ini terbuka setelah pengadaan diserahkan oleh PIC Pelaksana.'
              : 'Hanya Keuangan yang dapat mengubah checklist ini.';

    const identitySummary: Array<{
        label: string;
        value: string;
        field?: 'contract_type' | 'target_date';
    }> = [
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
        {
            label: 'Jenis Kontrak',
            value: procurement.contract_type ?? '—',
            field: 'contract_type' as const,
        },
        {
            label: 'Target Selesai',
            value: procurement.target_completion_date
                ? formatDate(procurement.target_completion_date)
                : '—',
            field: 'target_date' as const,
        },
        ...(procurement.work_category
            ? [
                  {
                      label: 'Format Dokumen',
                      value: procurement.work_category,
                  },
              ]
            : []),
        // Sembunyikan sementara sesuai permintaan user:
        // { label: 'Dibuat Oleh', value: detail.created_by ?? '—' },
        // { label: 'Tanggal Dibuat', value: formatDate(procurement.created_at) },
    ];

    const partnerSummary: Array<{
        label: string;
        value: string;
        fullWidth?: boolean;
    }> = [
        {
            label: 'Nama Calon Mitra',
            value: procurement.partner_name ?? '—',
        },
        {
            label: 'Nama Direktur',
            value: procurement.partner_director_name ?? '—',
        },
        {
            label: 'Nomor Surat Penawaran',
            value: procurement.quotation_number ?? '—',
        },
        {
            label: 'Tanggal Surat Penawaran',
            value: procurement.quotation_date
                ? formatDate(procurement.quotation_date)
                : '—',
        },
        {
            label: 'Alamat Perusahaan',
            value: procurement.partner_address ?? '—',
            fullWidth: true,
        },
    ];

    const proposalSummary: Array<{
        label: string;
        value: string;
    }> = [
        { label: 'Nomor PRK', value: procurement.prk_number ?? '—' },
        { label: 'Nomor COA', value: procurement.coa_number ?? '—' },
        { label: 'Nomor PR', value: procurement.pr_po_number ?? '—' },
        { label: 'Nomor SR', value: procurement.sr_number ?? '—' },
        { label: 'Nomor WO', value: procurement.wo_number ?? '—' },
        {
            label: 'Nomor Nota Dinas Usulan',
            value: procurement.proposal_memo_number ?? '—',
        },
        {
            label: 'Tanggal Nota Dinas Usulan',
            value: procurement.proposal_memo_date
                ? formatDate(procurement.proposal_memo_date)
                : '—',
        },
        {
            label: 'Nomor Nota Dinas ke Manager',
            value: procurement.icc_memo_number ?? '—',
        },
        {
            label: 'Tanggal Nota Dinas ke Manager',
            value: procurement.icc_memo_date
                ? formatDate(procurement.icc_memo_date)
                : '—',
        },
        {
            label: 'Nilai (Sebelum Nego)',
            value: formatCurrency(procurement.hpe_value),
        },
        {
            label: 'Nilai Setelah Nego',
            value:
                procurement.value_after_negotiation === null
                    ? '—'
                    : formatCurrency(procurement.value_after_negotiation),
        },
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
                        <section className="rounded-md border border-border bg-card">
                            <header className="border-b border-border px-4 py-3">
                                <h2 className="text-sm font-semibold text-foreground">
                                    Identitas Pengadaan
                                </h2>
                            </header>

                            <dl className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                {identitySummary.map((item) => (
                                    <div
                                        key={item.label}
                                        className="flex items-baseline justify-between gap-4 border-b border-border px-4 py-2.5 last:border-b-0 sm:[&:nth-last-child(2)]:border-b-0"
                                    >
                                        <dt className="text-sm text-muted-foreground">
                                            {item.label}
                                        </dt>
                                        <dd className="tabular text-right text-sm font-medium text-foreground">
                                            {can.updatePlanningIdentity &&
                                            item.field === 'target_date' ? (
                                                <TargetDateField
                                                    procurementId={
                                                        procurement.id
                                                    }
                                                    value={
                                                        procurement.target_completion_date
                                                    }
                                                />
                                            ) : can.updatePlanningIdentity &&
                                              item.field === 'contract_type' ? (
                                                <ContractTypePicker
                                                    procurementId={
                                                        procurement.id
                                                    }
                                                    value={
                                                        procurement.contract_type_id
                                                    }
                                                    options={
                                                        options.contractTypes
                                                    }
                                                />
                                            ) : (
                                                item.value
                                            )}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section className="rounded-md border border-border bg-card">
                            <header className="border-b border-border px-4 py-3">
                                <h2 className="text-sm font-semibold text-foreground">
                                    Calon Mitra
                                </h2>
                            </header>

                            <dl className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                {partnerSummary.map((item) => (
                                    <div
                                        key={item.label}
                                        className={`flex items-baseline justify-between gap-4 border-b border-border px-4 py-2.5 last:border-b-0 sm:[&:nth-last-child(2)]:border-b-0 ${item.fullWidth ? 'sm:col-span-2' : ''}`}
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
                        </section>

                        <section className="rounded-md border border-border bg-card">
                            <header className="border-b border-border px-4 py-3">
                                <h2 className="text-sm font-semibold text-foreground">
                                    Usulan Pekerjaan
                                </h2>
                            </header>

                            <dl className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                                {proposalSummary.map((item) => (
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
                                    canManageDocuments={can.generateDocument}
                                    lockedReason={
                                        procurement.planning_approval_state !==
                                        'disetujui'
                                            ? 'Tahap pelaksanaan terbuka setelah dokumen perencanaan disetujui.'
                                            : 'Anda tidak memiliki akses untuk mengubah checklist ini.'
                                    }
                                />
                                <FinanceHandover
                                    procurementId={procurement.id}
                                    finance={detail.finance}
                                    canSubmit={can.submitToFinance}
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
