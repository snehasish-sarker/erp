export type DocumentSequenceStatus =
    | 'active'
    | 'inactive';

export type DocumentResetPolicy =
    | 'never'
    | 'calendar_year'
    | 'fiscal_year';

export type DocumentSequenceScopeFilter =
    | ''
    | 'company'
    | 'branch';

export type DocumentSequenceSort =
    | 'name'
    | 'document_type'
    | 'current_number'
    | 'status'
    | 'updated_at';

export interface DocumentSequenceBranch {
    id: number;
    name: string;
    code: string;
    status: 'active' | 'inactive' | 'archived';
}

export interface DocumentTypeOption {
    value: string;
    label: string;
    default_prefix: string;
}

export interface DocumentSequenceOption<
    TValue extends string,
> {
    value: TValue;
    label: string;
}

export interface DocumentNumberPreviewContext {
    timezone: string;
    company_code: string;
    current_year: number;
    current_month: number;
}

export interface DocumentSequenceRecord {
    id: number;
    branch_id: number | null;
    branch: DocumentSequenceBranch | null;
    name: string;
    document_type: string;
    document_type_label: string;
    prefix: string | null;
    suffix: string | null;
    current_number: number;
    number_padding: number;
    reset_policy: DocumentResetPolicy;
    fiscal_year_start_month: number | null;
    last_reset_key: string | null;
    status: DocumentSequenceStatus;
    allocations_count: number;
    has_allocations: boolean;
    preview: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface DocumentSequenceFilters {
    search: string;
    scope: DocumentSequenceScopeFilter;
    branch_id: number | null;
    document_type: string;
    reset_policy: '' | DocumentResetPolicy;
    status: '' | DocumentSequenceStatus;
    sort: DocumentSequenceSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface DocumentSequencePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface DocumentSequencePagination {
    data: DocumentSequenceRecord[];
    meta: DocumentSequencePaginationMeta;
}