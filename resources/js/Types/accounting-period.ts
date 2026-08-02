export type FiscalYearStatus =
    | 'active'
    | 'closed';

export type AccountingPeriodStatus =
    | 'open'
    | 'closed';

export type FiscalYearSort =
    | 'name'
    | 'code'
    | 'start_date'
    | 'end_date'
    | 'status'
    | 'created_at';

export interface FiscalYearStatusOption {
    value: FiscalYearStatus;
    label: string;
}

export interface FiscalYearFilters {
    search: string;
    status: '' | FiscalYearStatus;
    sort: FiscalYearSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface FiscalYearRecord {
    id: number;
    name: string;
    code: string;
    start_date: string;
    end_date: string;
    status: FiscalYearStatus;
    periods_count: number;
    open_periods_count: number;
    closed_periods_count: number;
    is_current: boolean;
    created_at: string | null;
    updated_at: string | null;
}

export interface FiscalYearPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface FiscalYearPagination {
    data: FiscalYearRecord[];
    meta: FiscalYearPaginationMeta;
}

export interface ClosedByUser {
    id: number;
    name: string;
    email: string;
}

export interface AccountingPeriodRecord {
    id: number;
    fiscal_year_id: number;
    period_number: number;
    name: string;
    code: string;
    start_date: string;
    end_date: string;
    status: AccountingPeriodStatus;
    closed_at: string | null;
    closed_by: ClosedByUser | null;
    is_current: boolean;
    can_close: boolean;
    can_reopen: boolean;
}