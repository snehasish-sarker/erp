export type BranchStatus = 'active' | 'inactive' | 'archived';

export interface BranchRecord {
    id: number;
    name: string;
    code: string;
    status: BranchStatus;
    email: string | null;
    phone: string | null;
    address: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface BranchStatusOption {
    value: BranchStatus;
    label: string;
}

export interface BranchFilters {
    search: string;
    status: '' | BranchStatus;
    sort: 'name' | 'code' | 'status' | 'created_at';
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface BranchPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    previous_page_url: string | null;
    next_page_url: string | null;
}

export interface BranchPagination {
    data: BranchRecord[];
    meta: BranchPaginationMeta;
}