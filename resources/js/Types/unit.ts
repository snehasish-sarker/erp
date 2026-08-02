export type UnitCategory =
    | 'count'
    | 'weight'
    | 'length'
    | 'volume'
    | 'area'
    | 'time'
    | 'other';

export type UnitStatus =
    | 'active'
    | 'inactive';

export type UnitSort =
    | 'name'
    | 'code'
    | 'category'
    | 'allow_decimal'
    | 'decimal_places'
    | 'status'
    | 'created_at';

export interface UnitOption<
    TValue extends string,
> {
    value: TValue;
    label: string;
}

export interface UnitRecord {
    id: number;
    name: string;
    code: string;
    symbol: string | null;
    category: UnitCategory;
    category_label: string;
    allow_decimal: boolean;
    decimal_places: number;
    status: UnitStatus;
    created_at: string | null;
    updated_at: string | null;
}

export interface UnitFormData {
    name: string;
    code: string;
    symbol: string;
    category: UnitCategory;
    allow_decimal: boolean;
    decimal_places: number;
    status: UnitStatus;
}

export interface UnitFilters {
    search: string;
    category: '' | UnitCategory;
    status: '' | UnitStatus;
    sort: UnitSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface UnitPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface UnitPagination {
    data: UnitRecord[];
    meta: UnitPaginationMeta;
}