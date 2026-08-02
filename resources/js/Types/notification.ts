export type UserNotificationCategory =
    | 'system'
    | 'security'
    | 'approval'
    | 'procurement'
    | 'inventory'
    | 'sales'
    | 'accounting'
    | 'export';

export type UserNotificationSeverity =
    | 'info'
    | 'success'
    | 'warning'
    | 'error';

export type UserNotificationReadStatus =
    | 'unread'
    | 'read';

export type UserNotificationSort =
    | 'created_at'
    | 'title'
    | 'category'
    | 'severity'
    | 'read_at';

export interface UserNotificationActor {
    id: number | null;
    name: string | null;
    email: string | null;
}

export interface UserNotificationRecord {
    id: number;
    notification_key: string;
    category: UserNotificationCategory;
    category_label: string;
    type: string;
    severity: UserNotificationSeverity;
    title: string;
    message: string;
    action_url: string | null;
    action_label: string | null;
    source_type: string | null;
    source_id: string | null;
    actor: UserNotificationActor;
    is_read: boolean;
    read_at: string | null;
    created_at: string | null;
}

export interface SharedHeaderNotifications {
    unread_count: number;
    items: UserNotificationRecord[];
}

export interface UserNotificationOption<
    TValue extends string,
> {
    value: TValue;
    label: string;
}

export interface UserNotificationFilters {
    search: string;
    category: '' | UserNotificationCategory;
    severity: '' | UserNotificationSeverity;
    status: '' | UserNotificationReadStatus;
    sort: UserNotificationSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface UserNotificationPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface UserNotificationPagination {
    data: UserNotificationRecord[];
    meta: UserNotificationPaginationMeta;
}