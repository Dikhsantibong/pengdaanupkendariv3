export type StatusCategory = 'pending' | 'batal' | 'berjalan' | 'selesai';

export type ProcurementStage = 'perencanaan' | 'pelaksanaan' | 'keuangan';

/** Where a job is in its journey, from proposal to closing. */
export type CurrentStage =
    'usulan' | 'perencanaan' | 'pelaksanaan' | 'keuangan' | 'selesai';

export type PlanningApprovalState =
    'belum_diajukan' | 'menunggu_persetujuan' | 'disetujui' | 'ditolak';

export type Option = {
    value: number;
    label: string;
    description?: string | null;
};

export type StatusOption = Option & { category: StatusCategory };

/** How busy a PIC candidate currently is. */
export type PicWorkload = {
    /** Procurements still in planning with this user as PIC Perencana. */
    planning: number;
    /** Procurements in execution with this user as PIC Pelaksana. */
    execution: number;
    /** Every unfinished procurement held in either role. */
    active: number;
};

export type PicOption = Option & { workload?: PicWorkload };

export type DocumentTypeOption = Option & {
    stage: ProcurementStage;
    hasTemplate: boolean;
};

export type EnumOption = { value: string; label: string };

export type ChecklistProgress = {
    completed: number;
    total: number;
    percentage: number;
};

export type UserRef = { id: number; name: string };

export type ProcurementRow = {
    id: number;
    number: string;
    name: string;
    work_director: string;
    /** Every unit served, joined with commas. */
    target_unit: string;
    target_units: string[];
    partner_name: string | null;
    partner_director_name: string | null;
    partner_address: string | null;
    procurement_method: string | null;
    budget_source: string | null;
    contract_type: string | null;
    contract_type_id: number | null;
    /** The CSMS risk level stated by the user (Low, Moderat, High, Extreme). */
    csms_risk_level?: string | null;
    /** The metode pengadaan picked by the planning PIC. */
    selection_method?: string | null;
    selection_method_id?: number | null;
    /** Barang or Jasa, for the formats that ask for it. */
    work_category?: string | null;
    manager_memo_number: string | null;
    pr_po_number: string | null;
    prk_number: string | null;
    proposal_memo_number: string | null;
    proposal_memo_date: string | null;
    icc_memo_number: string | null;
    icc_memo_date: string | null;
    coa_number: string | null;
    wo_number: string | null;
    sr_number: string | null;
    quotation_number: string | null;
    quotation_date: string | null;
    hpe_value: number;
    value_after_negotiation: number | null;
    status: { id: number; name: string; category: StatusCategory };
    planner: UserRef | null;
    executor: UserRef | null;
    planning_approval_state: PlanningApprovalState;
    planning_approval_label: string;
    execution_approval_state: PlanningApprovalState;
    execution_approval_label: string;
    current_stage: CurrentStage;
    current_stage_label: string;
    finance_submitted_at: string | null;
    payment_completed_at: string | null;
    planning_progress?: ChecklistProgress;
    execution_progress?: ChecklistProgress;
    target_completion_date: string | null;
    completed_at: string | null;
    created_at: string | null;
};

/** One signed scan filed against a generated document. */
export type SignedUpload = {
    id: number;
    file_name: string;
    size: number | null;
    uploaded_by: string | null;
    uploaded_at: string | null;
};

/** A document a checklist step produces. */
export type ChecklistDocument = {
    type_id: number;
    type_name: string | null;
    id: number | null;
    title: string | null;
    is_signed: boolean;
    uploads: SignedUpload[];
    has_template: boolean;
    /** Uploaded rather than generated: only an upload is offered. */
    upload_only: boolean;
    /** One of a set of alternatives; one signed copy among them is enough. */
    is_alternative: boolean;
};

/** A proposal document (TOR or Nota Dinas Usulan) with readable links. */
export type ProposalDocument = Omit<ChecklistDocument, 'uploads'> & {
    uploads: Array<SignedUpload & { url: string; is_image: boolean }>;
    /** The TOR drafted from its template, as a PDF. */
    preview_url: string | null;
};

export type ChecklistRow = {
    id: number;
    name: string;
    description: string | null;
    /** The Keuangan tab of the step: Penyerahan or Pembayaran. */
    section?: 'penyerahan' | 'pembayaran' | null;
    is_optional: boolean;
    is_completed: boolean;
    notes: string | null;
    completed_by: string | null;
    completed_at: string | null;
    documents: ChecklistDocument[];
    /** Finished by uploading its paper on the step (Penyerahan / Pembayaran). */
    needs_upload: boolean;
    files: ChecklistFile[];
    /** The partner's letter (Surat Jalan, Garansi, Permohonan Pembayaran) to download as Word, filled in. */
    template_url: string | null;
    /** Data the step asks for before it can be ticked, if any. */
    input: ChecklistInput | null;
};

/** A file uploaded on a checklist step. */
export type ChecklistFile = {
    id: number;
    file_name: string;
    size: number | null;
    uploaded_by: string | null;
    uploaded_at: string | null;
    url: string;
};

/** A partner candidate (calon mitra) listed in planning. */
export type PartnerCandidate = {
    id: number;
    name: string;
    /** Kode rekanan (vendor code). */
    vendor_code: string | null;
    director_name: string | null;
    address: string | null;
    quotation_number: string | null;
    quotation_date: string | null;
    /** The offer's total before negotiation. */
    quotation_value: number | null;
    /** The scanned quotation (penawaran harga). */
    quotation_file: { name: string; size: number | null; url: string } | null;
    /** What is still to be filled in before the Penawaran step counts. */
    missing: string[];
    is_selected: boolean;
};

export type ChecklistInputKind =
    | 'contract_period'
    | 'warranty'
    | 'bank_account'
    | 'partner_candidates'
    | 'procurement_memo'
    | 'contract_number';

export type ChecklistInput = {
    kind: ChecklistInputKind;
    label: string;
    is_filled: boolean;
    values: {
        execution_start_date: string | null;
        execution_duration_days: number | null;
        execution_end_date: string | null;
        warranty_months: number | null;
        /** The calon mitra picked as the executor. */
        partner_candidate_id: number | null;
        bank_account_number: string | null;
        bank_name: string | null;
        bank_account_holder: string | null;
        /** The Nota Dinas ke Pengadaan. */
        icc_memo_number: string | null;
        icc_memo_date: string | null;
        /** The contract number, once the executor has given it. */
        number: string | null;
        /** The running number of the job's contract format, to offer. */
        suggested_number: string | null;
    };
};

export type ProcurementDocumentRow = {
    id: number;
    title: string;
    type: string;
    upload_only: boolean;
    template_version: number;
    revision: number;
    generated_by: string | null;
    generated_at: string;
    edited_by: string | null;
    edited_at: string | null;
    uploads: SignedUpload[];
};

export type ActivityRow = {
    id: number;
    type: string;
    type_label: string;
    description: string;
    user: string | null;
    created_at: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * Where a paginator puts its page links depends on how it was serialised.
 *
 * A plain paginator exposes `links` as an array at the root, while an Eloquent
 * resource collection wraps them: `links` becomes {first,last,prev,next} and
 * the numbered links move to `meta.links`. Both shapes reach the client, so
 * `Paginated` models both and `DataPagination` normalises them.
 */
export type PaginationCursorLinks = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

export type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links?: PaginationLink[];
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[] | PaginationCursorLinks;
    meta?: PaginationMeta;
    current_page?: number;
    from?: number | null;
    last_page?: number;
    per_page?: number;
    to?: number | null;
    total?: number;
};

export type ProcurementFilters = {
    search: string | null;
    progress_status_id: number | null;
    work_director_id: number | null;
    target_unit_id: number | null;
    budget_source_id: number | null;
    contract_number_format_id: number | null;
    contract_type_id: number | null;
    planner_id: number | null;
    executor_id: number | null;
};

export type FilterOptions = {
    workDirectors: Option[];
    targetUnits: Option[];
    budgetSources: Option[];
    progressStatuses: StatusOption[];
};
