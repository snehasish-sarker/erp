export type RoleTypeFilter = '' | 'system' | 'custom';

export type RoleSort =
    | 'name'
    | 'users_count'
    | 'permissions_count'
    | 'created_at';

export interface RoleListRecord {
    id: number;
    name: string;
    is_system: boolean;
    is_tenant_owner: boolean;
    users_count: number;
    permissions_count: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface RoleEditRecord {
    id: number;
    name: string;
    is_system: boolean;
    is_tenant_owner: boolean;
    permission_ids: number[];
}

export interface RolePermission {
    id: number;
    name: string;
    label: string;
}

export interface RolePermissionGroup {
    key: string;
    label: string;
    permissions: RolePermission[];
}

export interface RoleAbilities {
    update_details: boolean;
    assign_permissions: boolean;
}

export interface RoleFilters {
    search: string;
    type: RoleTypeFilter;
    sort: RoleSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface RolePaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface RolePagination {
    data: RoleListRecord[];
    meta: RolePaginationMeta;
}