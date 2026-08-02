export type AuditJsonPrimitive =
    | string
    | number
    | boolean
    | null;

export type AuditJsonValue =
    | AuditJsonPrimitive
    | AuditJsonValue[]
    | {
        [key: string]: AuditJsonValue;
    };

export type AuditValueMap = Record<
    string,
    AuditJsonValue
>;

export type AuditLogSort =
    | 'created_at'
    | 'event'
    | 'subject_label'
    | 'actor_name';

export interface AuditFilterOption {
    value: string;
    label: string;
}

export interface AuditActorOption {
    value: string;
    name: string;
    email: string | null;
}

export interface AuditLogListRecord {
    id: number;
    event: string;
    actor_user_id: number | null;
    actor_name: string | null;
    actor_email: string | null;
    subject_type: string;
    subject_type_label: string;
    subject_id: number | null;
    subject_label: string | null;
    changes_count: number;
    request_id: string | null;
    route_name: string | null;
    http_method: string | null;
    ip_address: string | null;
    created_at: string | null;
}

export interface AuditLogDetailRecord
    extends AuditLogListRecord {
    old_values: AuditValueMap | null;
    new_values: AuditValueMap | null;
    metadata: AuditValueMap | null;
    url: string | null;
    user_agent: string | null;
}

export interface AuditLogFilters {
    search: string;
    event: string;
    subject_type: string;
    actor: string;
    date_from: string;
    date_to: string;
    sort: AuditLogSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface AuditLogPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface AuditLogPagination {
    data: AuditLogListRecord[];
    meta: AuditLogPaginationMeta;
}