export type StatusCategory = 'pending' | 'batal' | 'berjalan' | 'selesai';

export type ProcurementStage = 'perencanaan' | 'pelaksanaan';

export type PlanningApprovalState =
    'belum_diajukan' | 'menunggu_persetujuan' | 'disetujui' | 'ditolak';

export type Option = {
    value: number;
    label: string;
    description?: string | null;
};

export type StatusOption = Option & { category: StatusCategory };

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
    target_unit: string;
    procurement_method: string | null;
    budget_source: string | null;
    pr_ro_number: string | null;
    prk_number: string | null;
    hpe_value: number;
    status: { id: number; name: string; category: StatusCategory };
    planner: UserRef | null;
    executor: UserRef | null;
    planning_approval_state: PlanningApprovalState;
    planning_approval_label: string;
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
};

export type ChecklistRow = {
    id: number;
    name: string;
    description: string | null;
    is_optional: boolean;
    is_completed: boolean;
    notes: string | null;
    completed_by: string | null;
    completed_at: string | null;
    documents: ChecklistDocument[];
};

export type ProcurementDocumentRow = {
    id: number;
    title: string;
    type: string;
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
    procurement_method_id: number | null;
    budget_source_id: number | null;
    planner_id: number | null;
    executor_id: number | null;
};

export type FilterOptions = {
    workDirectors: Option[];
    targetUnits: Option[];
    procurementMethods: Option[];
    budgetSources: Option[];
    progressStatuses: StatusOption[];
};
