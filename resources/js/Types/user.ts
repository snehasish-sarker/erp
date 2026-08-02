export type UserStatus =
    | 'active'
    | 'inactive'
    | 'suspended'
    | 'archived';

export type UserBranchStatus =
    | 'active'
    | 'inactive'
    | 'archived';

export interface UserBranchOption {
    id: number;
    name: string;
    code: string;
    status: UserBranchStatus;
}

export interface UserRoleOption {
    id: number;
    name: string;
}

export interface UserStatusOption {
    value: UserStatus;
    label: string;
}

export interface ManagedUserRecord {
    id: number;
    name: string;
    email: string;
    status: UserStatus;
    branch_id: number | null;
    branch: UserBranchOption | null;
    roles: UserRoleOption[];
    is_current_user: boolean;
    is_tenant_owner: boolean;
    created_at: string | null;
    updated_at: string | null;
}

export interface UserFilters {
    search: string;
    branch_id: number | null;
    role_id: number | null;
    status: '' | UserStatus;
    sort: 'name' | 'email' | 'status' | 'created_at';
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface UserPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface UserPagination {
    data: ManagedUserRecord[];
    meta: UserPaginationMeta;
}