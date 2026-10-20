import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    FileDiff,
    MoreHorizontal,
    FileText,
    History,
    Pencil,
    Send,
    Trash2,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { AmendmentTab } from '@/components/procurement/amendment-tab';
import type {
    Amendment,
    ContractArticle,
} from '@/components/procurement/amendment-tab';
import { ApprovalCard } from '@/components/procurement/approval-card';
import { ApprovalHistoryTable } from '@/components/procurement/approval-history';
import type { ApprovalHistory } from '@/components/procurement/approval-history';
import {
    ApprovalPanel,
    ApprovalStage,
    CompleteProcurementButton,
} from '@/components/procurement/approval-panel';
import type { ApprovalStageState } from '@/components/procurement/approval-panel';
import { ChecklistSteps } from '@/components/procurement/checklist-steps';
import { DeliveryOrderTab } from '@/components/procurement/delivery-order-tab';
import type { DeliveryOrders } from '@/components/procurement/delivery-order-tab';
import {
    ExecutionApproval,
    FinanceStatus,
} from '@/components/procurement/finance-card';
import type { FinanceDetail } from '@/components/procurement/finance-card';
import { IdentityPanel } from '@/components/procurement/identity-panel';
import { PicAssignmentCard } from '@/components/procurement/pic-assignment-card';
import { ProposalDocuments } from '@/components/procurement/proposal-documents';
import { openRememberedPanel } from '@/components/procurement/request-board';
import { StageStepper } from '@/components/procurement/stage-stepper';
import type { Stage } from '@/components/procurement/stage-stepper';
import {
    CancelProcurementDialog,
    StatusCard,
} from '@/components/procurement/status-card';
import type {
    StatusHistoryEntry,
    StatusJourney,
} from '@/components/procurement/status-card';
import { DownloadAllButton } from '@/components/procurement-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tabs, TabsContent } from '@/components/ui/tabs';
import { formatDateTime } from '@/lib/format';
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
        status_journey: StatusJourney;
        status_history: StatusHistoryEntry[];
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
    /** The contract's articles, while an amendment waits on Laksdan. */
    amendmentArticles: ContractArticle[];
    /** The DO tab, for a KHS SPK / PJ only. */
    deliveryOrders: DeliveryOrders | null;
    partnerCandidates: PartnerCandidate[];
    directAppointment: boolean;
    options: {
        progressStatuses: StatusOption[];
        planners: Option[];
        executors: Option[];
        contractTypes: Option[];
        selectionMethods: Option[];
        documentTypes: DocumentTypeOption[];
    };
    can: {
        update: boolean;
        startAmendment: boolean;
        startDeliveryOrder: boolean;
        manageContractItems: boolean;
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
    amendmentArticles,
    deliveryOrders,
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

    const [tab, setTab] = useState(initialTab);

    // The open tab rides in the address, so the page comes back to it after
    // every save.
    const changeTab = (next: string) => {
        setTab(next);
        const query = new URLSearchParams(window.location.search);
        query.set('tab', next);
        router.replace({
            url: `${window.location.pathname}?${query.toString()}`,
            preserveState: true,
            preserveScroll: true,
        });
    };

    /**
     * Where an outstanding step named beside an Ajukan button is done: its
     * own row in the stage's step table, opened in the side panel. The
     * proposal's data and papers have their own places.
     */
    const pendingAction =
        (checklistStage: 'perencanaan' | 'pelaksanaan' | 'keuangan') =>
        (name: string): (() => void) | undefined => {
            if (name.endsWith('(data identitas)')) {
                return can.update
                    ? () => router.visit(procurements.edit(procurement.id).url)
                    : undefined;
            }

            if (name.endsWith('(tab dokumen pengguna)')) {
                return detail.proposal_documents
                    ? () => changeTab('usulan')
                    : undefined;
            }

            const row = checklists[checklistStage].find(
                (candidate) => candidate.name === name,
            );

            if (row === undefined) {
                return undefined;
            }

            const target =
                checklistStage === 'keuangan'
                    ? (row.section ?? 'pembayaran')
                    : checklistStage;

            return () => {
                openRememberedPanel(`checklist:${procurement.id}`, row.id);
                changeTab(target);
            };
        };

    /** "5/8 langkah": the required steps of a checklist, done of all. */
    const progressOf = (rows: ChecklistRow[]): string => {
        const required = rows.filter((row) => !row.is_optional);

        return `${required.filter((row) => row.is_completed).length}/${required.length} langkah`;
    };

    const planningApproved =
        procurement.planning_approval_state === 'disetujui';
    const execution = detail.finance.execution_approval;
    const handedOver = detail.finance.submitted_at !== null;
    const paid = detail.finance.paid_at !== null;
    const handoverRows = financeRows('penyerahan');
    const handoverDone = handoverRows
        .filter((row) => !row.is_optional)
        .every((row) => row.is_completed);
    const stage = procurement.current_stage;

    // The stages in their order, each with where it stands.
    const stages: Stage[] = [
        ...(detail.proposal_documents
            ? [
                  {
                      value: 'usulan',
                      label: 'Usulan',
                      state:
                          stage === 'usulan'
                              ? ('current' as const)
                              : ('done' as const),
                      summary: `${detail.proposal_documents.documents.filter((document) => document.is_signed).length}/${detail.proposal_documents.documents.length} dokumen`,
                  },
              ]
            : []),
        {
            value: 'perencanaan',
            label: 'Perencanaan',
            state: planningApproved
                ? 'done'
                : stage === 'perencanaan'
                  ? 'current'
                  : 'todo',
            summary: planningApproved
                ? 'Disetujui TL ICC'
                : procurement.planning_approval_state === 'menunggu_persetujuan'
                  ? 'Menunggu TL ICC'
                  : procurement.planning_approval_state === 'ditolak'
                    ? 'Dikembalikan'
                    : progressOf(checklists.perencanaan),
            attention: can.submitPlanning || can.reviewPlanning,
        },
        {
            value: 'pelaksanaan',
            label: 'Pelaksanaan',
            state: !planningApproved
                ? 'locked'
                : handedOver
                  ? 'done'
                  : 'current',
            summary: !planningApproved
                ? 'Terkunci'
                : handedOver
                  ? 'Disetujui TL Pengadaan'
                  : execution.state === 'menunggu_persetujuan'
                    ? 'Menunggu TL Pengadaan'
                    : execution.state === 'ditolak'
                      ? 'Dikembalikan'
                      : progressOf(checklists.pelaksanaan),
            attention: can.submitExecution || can.reviewExecution,
        },
        {
            value: 'penyerahan',
            label: 'Penyerahan',
            state: !handedOver ? 'locked' : handoverDone ? 'done' : 'current',
            summary: !handedOver ? 'Terkunci' : progressOf(handoverRows),
        },
        {
            value: 'pembayaran',
            label: 'Pembayaran',
            state: !handedOver
                ? 'locked'
                : paid
                  ? 'done'
                  : handoverDone
                    ? 'current'
                    : 'todo',
            summary: !handedOver
                ? 'Terkunci'
                : paid
                  ? 'Lunas'
                  : progressOf(financeRows('pembayaran')),
            attention: can.completePayment && !paid,
        },
    ];

    const [cancelling, setCancelling] = useState(false);
    const isCancelled = procurement.status.category === 'batal';

    const planningApproval: { state: ApprovalStageState; summary: string } =
        planningApproved
            ? {
                  state: 'done',
                  summary: `Disetujui ${formatDateTime(detail.planning_reviewed_at)}${detail.planning_reviewer ? ` · ${detail.planning_reviewer}` : ''}`,
              }
            : procurement.planning_approval_state === 'menunggu_persetujuan'
              ? { state: 'waiting', summary: 'Menunggu persetujuan TL ICC' }
              : procurement.planning_approval_state === 'ditolak'
                ? { state: 'returned', summary: 'Dikembalikan untuk revisi' }
                : {
                      state: 'active',
                      summary: can.submitPlanning
                          ? 'Siap diajukan ke TL ICC'
                          : detail.pending_required_planning.length > 0
                            ? `Belum diajukan · ${detail.pending_required_planning.length} hal belum selesai`
                            : 'Belum diajukan',
                  };

    const executionApproval: { state: ApprovalStageState; summary: string } =
        !planningApproved
            ? {
                  state: 'locked',
                  summary: 'Terbuka setelah perencanaan disetujui',
              }
            : handedOver
              ? {
                    state: 'done',
                    summary: `Disetujui, diserahkan ke Keuangan ${formatDateTime(detail.finance.submitted_at)}`,
                }
              : execution.state === 'menunggu_persetujuan'
                ? {
                      state: 'waiting',
                      summary: 'Menunggu persetujuan TL Pengadaan',
                  }
                : execution.state === 'ditolak'
                  ? { state: 'returned', summary: 'Dikembalikan untuk revisi' }
                  : {
                        state: 'active',
                        summary: can.submitExecution
                            ? 'Siap diajukan ke TL Pengadaan'
                            : detail.finance.pending_execution.length > 0
                              ? `Belum diajukan · ${detail.finance.pending_execution.length} langkah belum selesai`
                              : 'Belum diajukan',
                    };

    // The approval the user can act on now, for the shortcut on a phone,
    // where the side column sits below the long tables.
    const approvalTask = can.submitPlanning
        ? 'Perencanaan siap diajukan ke TL ICC.'
        : can.reviewPlanning
          ? 'Perencanaan menunggu persetujuan Anda.'
          : can.submitExecution
            ? 'Pelaksanaan siap diajukan ke TL Pengadaan.'
            : can.reviewExecution
              ? 'Pelaksanaan menunggu persetujuan Anda.'
              : null;

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
                                <Button asChild variant="outline">
                                    <Link
                                        href={procurements.edit(procurement.id)}
                                    >
                                        <Pencil className="size-4" />
                                        Edit Identitas Pengadaan
                                    </Link>
                                </Button>
                            )}
                            {(can.update ||
                                (can.updateStatus && !isCancelled)) && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            aria-label="Tindakan lain"
                                        >
                                            <MoreHorizontal className="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {can.updateStatus && !isCancelled && (
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onSelect={() =>
                                                    setCancelling(true)
                                                }
                                            >
                                                <Ban className="size-4" />
                                                Batalkan Pengadaan
                                            </DropdownMenuItem>
                                        )}
                                        {can.updateStatus &&
                                            !isCancelled &&
                                            can.update && (
                                                <DropdownMenuSeparator />
                                            )}
                                        {can.update && (
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onSelect={() => {
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
                                            </DropdownMenuItem>
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
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
                            selectionMethods={options.selectionMethods}
                            candidateCount={partnerCandidates.length}
                        />

                        <Tabs
                            defaultValue={initialTab}
                            value={tab}
                            onValueChange={changeTab}
                        >
                            <StageStepper
                                stages={stages}
                                active={tab}
                                onSelect={changeTab}
                                extras={[
                                    {
                                        value: 'amandemen',
                                        label: 'Amandemen',
                                        icon: <FileDiff className="size-3.5" />,
                                        count: amendments.length,
                                        attention: amendments.some(
                                            (amendment) =>
                                                amendment.can.update ||
                                                amendment.can.decide ||
                                                amendment.can.issue,
                                        ),
                                    },
                                    ...(deliveryOrders
                                        ? [
                                              {
                                                  value: 'do',
                                                  label: 'Delivery Order',
                                                  icon: (
                                                      <Truck className="size-3.5" />
                                                  ),
                                                  count: deliveryOrders.orders
                                                      .length,
                                                  attention:
                                                      deliveryOrders.orders.some(
                                                          (order) =>
                                                              order.can
                                                                  .request ||
                                                              order.can
                                                                  .decide ||
                                                              order.can
                                                                  .forward ||
                                                              order.can.issue,
                                                      ),
                                              },
                                          ]
                                        : []),
                                    {
                                        value: 'riwayat',
                                        label: 'Riwayat Approval',
                                        icon: <History className="size-3.5" />,
                                    },
                                ]}
                            />

                            {approvalTask !== null && (
                                <div className="flex items-center justify-between gap-3 rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm lg:hidden">
                                    <span>{approvalTask}</span>
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            document
                                                .getElementById(
                                                    'approval-cards',
                                                )
                                                ?.scrollIntoView({
                                                    behavior: 'smooth',
                                                    block: 'start',
                                                })
                                        }
                                    >
                                        <Send className="size-3.5" />
                                        Buka
                                    </Button>
                                </div>
                            )}

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
                                <ChecklistSteps
                                    procurementId={procurement.id}
                                    description="Kerjakan langkah dari atas ke bawah. Klik sebuah langkah untuk mengisi data, membuat dokumen, dan mengunggah berkasnya."
                                    doneMessage="Semua langkah wajib perencanaan sudah selesai dan siap diajukan ke TL ICC."
                                    submit={
                                        can.submitPlanning
                                            ? {
                                                  label: 'Ajukan ke TL ICC',
                                                  onSubmit: () =>
                                                      router.post(
                                                          procurements.approval.store(
                                                              procurement.id,
                                                          ).url,
                                                          {},
                                                          {
                                                              preserveScroll: true,
                                                          },
                                                      ),
                                              }
                                            : undefined
                                    }
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
                                <ChecklistSteps
                                    procurementId={procurement.id}
                                    description="Tahapan pelaksanaan pengadaan hingga masa pemeliharaan selesai. Klik sebuah langkah untuk mengisi data, membuat dokumen, dan mengunggah berkasnya."
                                    doneMessage="Semua langkah wajib pelaksanaan sudah selesai dan siap diajukan ke TL Pengadaan."
                                    submit={
                                        can.submitExecution
                                            ? {
                                                  label: 'Ajukan ke TL Pengadaan',
                                                  onSubmit: () =>
                                                      router.post(
                                                          procurements.executionApproval.store(
                                                              procurement.id,
                                                          ).url,
                                                          {},
                                                          {
                                                              preserveScroll: true,
                                                          },
                                                      ),
                                              }
                                            : undefined
                                    }
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
                            </TabsContent>

                            <TabsContent
                                value="penyerahan"
                                className="space-y-3"
                            >
                                {requirementsNotice}
                                {requirementsSource('penyerahan')}
                                <ChecklistSteps
                                    procurementId={procurement.id}
                                    description="Kelengkapan dokumen penyerahan barang / pekerjaan sesuai pasal penyerahan pada kontrak (SPK, Surat Perjanjian, atau Lampiran Surat Pesanan). Klik sebuah langkah untuk mengunduh template surat dan mengunggah berkasnya."
                                    doneMessage="Semua kelengkapan penyerahan sudah lengkap. Lanjutkan ke tab Pembayaran."
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
                                    pendingAction={pendingAction('keuangan')}
                                />
                                {requirementsNotice}
                                {requirementsSource('pembayaran')}
                                <ChecklistSteps
                                    procurementId={procurement.id}
                                    description="Kelengkapan dokumen permintaan pembayaran sesuai pasal cara pembayaran pada kontrak, diperiksa Keuangan sebelum pengadaan ditutup. Klik sebuah langkah untuk mengunduh template surat dan mengunggah berkasnya."
                                    doneMessage="Semua kelengkapan pembayaran sudah lengkap. Keuangan dapat mencatat Pembayaran Lunas & Tutup Pengadaan."
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
                                    articles={amendmentArticles}
                                />
                            </TabsContent>

                            {deliveryOrders && (
                                <TabsContent value="do">
                                    <DeliveryOrderTab
                                        procurementId={procurement.id}
                                        data={deliveryOrders}
                                        canStart={can.startDeliveryOrder}
                                        canManageItems={can.manageContractItems}
                                        contractValue={
                                            procurement.value_after_negotiation
                                        }
                                    />
                                </TabsContent>
                            )}

                            <TabsContent value="riwayat">
                                <ApprovalHistoryTable
                                    history={approvalHistory}
                                />
                            </TabsContent>
                        </Tabs>
                    </div>

                    {/* The side column stays in view beside the long tables. */}
                    <aside className="flex flex-col gap-4 lg:sticky lg:top-4 lg:max-h-[calc(100svh-2rem)] lg:self-start lg:overflow-y-auto">
                        <ApprovalPanel
                            id="approval-cards"
                            footer={
                                can.complete && planningApproved ? (
                                    <CompleteProcurementButton
                                        procurementId={procurement.id}
                                    />
                                ) : undefined
                            }
                        >
                            <ApprovalStage
                                title="Perencanaan"
                                state={planningApproval.state}
                                summary={planningApproval.summary}
                                defaultOpen={planningApproval.state !== 'done'}
                            >
                                <ApprovalCard
                                    embedded
                                    procurementId={procurement.id}
                                    state={procurement.planning_approval_state}
                                    stateLabel={
                                        procurement.planning_approval_label
                                    }
                                    submittedAt={detail.planning_submitted_at}
                                    reviewedAt={detail.planning_reviewed_at}
                                    reviewer={detail.planning_reviewer}
                                    reviewNote={detail.planning_review_note}
                                    canSubmit={can.submitPlanning}
                                    isPlanner={detail.is_planner}
                                    plannerName={detail.planner_name}
                                    revision={detail.planning_revision}
                                    pendingRequired={
                                        detail.pending_required_planning
                                    }
                                    canReview={can.reviewPlanning}
                                    canRevert={can.revertPlanningRejection}
                                    canComplete={false}
                                    completedAt={procurement.completed_at}
                                    pendingAction={pendingAction('perencanaan')}
                                />
                            </ApprovalStage>

                            <ApprovalStage
                                title="Pelaksanaan"
                                state={executionApproval.state}
                                summary={executionApproval.summary}
                                defaultOpen={executionApproval.state !== 'done'}
                            >
                                {planningApproved && !handedOver && (
                                    <ExecutionApproval
                                        embedded
                                        procurementId={procurement.id}
                                        finance={detail.finance}
                                        canSubmit={can.submitExecution}
                                        canReview={can.reviewExecution}
                                        planningApproved={planningApproved}
                                        pendingAction={pendingAction(
                                            'pelaksanaan',
                                        )}
                                    />
                                )}
                            </ApprovalStage>
                        </ApprovalPanel>

                        <StatusCard
                            procurementId={procurement.id}
                            status={procurement.status}
                            rule={detail.status_rule}
                            journey={detail.status_journey}
                            history={detail.status_history}
                            editable={can.updateStatus}
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

                    <CancelProcurementDialog
                        procurementId={procurement.id}
                        open={cancelling}
                        onOpenChange={setCancelling}
                    />
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
