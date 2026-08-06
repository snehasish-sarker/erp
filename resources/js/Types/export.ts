export type ExportRequestStatus =
    | 'queued'
    | 'processing'
    | 'completed'
    | 'failed'
    | 'cancelled'
    | 'expired';

export type ExportRequestSort =
    | 'name'
    | 'export_type'
    | 'status'
    | 'progress_percent'
    | 'rows_exported'
    | 'created_at'
    | 'completed_at';

export interface ExportOption {
    value: string;
    label: string;
}

export interface ExportStatusOption {
    value: ExportRequestStatus;
    label: string;
}

export interface ExportRequester {
    id: number;
    name: string;
    email: string;
}

export interface AuditLogExportFilters {
    search: string;
    event: string;
    subject_type: string;
    actor: string;
    date_from: string;
    date_to: string;
    direction: 'asc' | 'desc';
}

export interface ExportRequestFormData {
    export_type: string;
    format: 'csv' | 'xlsx';
    filters: AuditLogExportFilters;
}

export interface ExportRequestRecord {
    id: number;
    request_key: string;
    name: string;
    export_type: string;
    export_type_label: string;
    format: 'csv' | 'xlsx';
    filters: Record<string, unknown>;
    status: ExportRequestStatus;
    progress_percent: number;
    rows_exported: number;
    error_code: string | null;
    error_message: string | null;
    requester: ExportRequester | null;
    can_download: boolean;
    can_cancel: boolean;
    download_url: string | null;
    queued_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    cancelled_at: string | null;
    expires_at: string | null;
    created_at: string | null;
}

export interface ExportRequestFilters {
    search: string;
    export_type: string;
    status: '' | ExportRequestStatus;
    sort: ExportRequestSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface ExportRequestPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface ExportRequestPagination {
    data: ExportRequestRecord[];
    meta: ExportRequestPaginationMeta;
}