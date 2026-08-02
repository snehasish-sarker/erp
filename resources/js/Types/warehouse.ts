export type WarehouseType =
    | 'general'
    | 'transit'
    | 'returns'
    | 'damaged';

export type WarehouseStatus =
    | 'active'
    | 'inactive'
    | 'archived';

export type WarehouseBranchStatus =
    | 'active'
    | 'inactive'
    | 'archived';

export interface WarehouseBranch {
    id: number;
    name: string;
    code: string;
    status: WarehouseBranchStatus;
}

export interface WarehouseRecord {
    id: number;
    branch_id: number;
    branch: WarehouseBranch;
    name: string;
    code: string;
    type: WarehouseType;
    status: WarehouseStatus;
    is_default: boolean;
    address: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface WarehouseTypeOption {
    value: WarehouseType;
    label: string;
}

export interface WarehouseStatusOption {
    value: WarehouseStatus;
    label: string;
}

export interface WarehouseFilters {
    search: string;
    branch_id: number | null;
    type: '' | WarehouseType;
    status: '' | WarehouseStatus;
    sort:
        | 'name'
        | 'code'
        | 'type'
        | 'status'
        | 'created_at';
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface WarehousePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface WarehousePagination {
    data: WarehouseRecord[];
    meta: WarehousePaginationMeta;
}