export type CustomerStatus =
    | 'active'
    | 'inactive';

export type CustomerType =
    | 'company'
    | 'individual'
    | 'government'
    | 'other';

export type CustomerSort =
    | 'name'
    | 'code'
    | 'customer_type'
    | 'payment_terms_days'
    | 'credit_limit'
    | 'status'
    | 'created_at';

export interface CustomerOption<
    TValue extends string,
> {
    value: TValue;
    label: string;
}

export interface CustomerRecord {
    id: number;
    name: string;
    code: string;
    customer_type: CustomerType;
    customer_type_label: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    alternate_phone: string | null;
    tax_number: string | null;
    registration_number: string | null;
    billing_address_line_1: string | null;
    billing_address_line_2: string | null;
    billing_city: string | null;
    billing_state: string | null;
    billing_postal_code: string | null;
    billing_country_code: string | null;
    shipping_address_line_1: string | null;
    shipping_address_line_2: string | null;
    shipping_city: string | null;
    shipping_state: string | null;
    shipping_postal_code: string | null;
    shipping_country_code: string | null;
    payment_terms_days: number;
    credit_limit: string | null;
    notes: string | null;
    status: CustomerStatus;
    created_at: string | null;
    updated_at: string | null;
}

export interface CustomerFormData {
    name: string;
    code: string;
    customer_type: CustomerType;
    contact_person: string;
    email: string;
    phone: string;
    alternate_phone: string;
    tax_number: string;
    registration_number: string;
    billing_address_line_1: string;
    billing_address_line_2: string;
    billing_city: string;
    billing_state: string;
    billing_postal_code: string;
    billing_country_code: string;
    shipping_address_line_1: string;
    shipping_address_line_2: string;
    shipping_city: string;
    shipping_state: string;
    shipping_postal_code: string;
    shipping_country_code: string;
    payment_terms_days: number;
    credit_limit: string;
    notes: string;
    status: CustomerStatus;
}

export interface CustomerFilters {
    search: string;
    customer_type: '' | CustomerType;
    status: '' | CustomerStatus;
    sort: CustomerSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

export interface CustomerPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface CustomerPagination {
    data: CustomerRecord[];
    meta: CustomerPaginationMeta;
}