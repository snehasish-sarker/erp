export interface PlatformAdminSummary {
    id: number;
    name: string;
    email: string;
    last_login_at: string | null;
}

export interface PlatformDashboardMetrics {
    tenants_total: number;
    tenants_trial: number;
    tenants_active: number;
    tenants_suspended: number;
    tenants_past_due: number;
    tenant_users_total: number;
}

export interface PlatformDashboardProps {
    platformAdmin: PlatformAdminSummary;
    metrics: PlatformDashboardMetrics;
}

export type PlatformTenantStatus =
    | 'trial'
    | 'active'
    | 'suspended'
    | 'past_due'
    | 'cancelled'
    | 'archived';

export interface PlatformTenantSummary {
    id: number;
    name: string;
    code: string;
    slug: string;
    status: PlatformTenantStatus;
    email: string | null;
    currency_code: string;
    timezone: string;
    users_count: number;
    branches_count: number;
    warehouses_count: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface PlatformTenantDetails extends PlatformTenantSummary {
    phone: string | null;
    address: string | null;
    active_users_count: number;
    active_branches_count: number;
    active_warehouses_count: number;
    can_activate: boolean;
    can_suspend: boolean;
}

export interface PlatformTenantStatusOption {
    value: PlatformTenantStatus;
    label: string;
}

export interface PlatformPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    previous_page_url: string | null;
    next_page_url: string | null;
}

export interface PlatformTenantPage {
    data: PlatformTenantSummary[];
    meta: PlatformPaginationMeta;
}

export interface PlatformTenantFilters {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
}

export interface PlatformTenantIndexProps {
    tenants: PlatformTenantPage;
    filters: PlatformTenantFilters;
    statusOptions: PlatformTenantStatusOption[];
}

export interface PlatformTenantCreateDefaults {
    status: 'trial' | 'active';
    currency_code: string;
    timezone: string;
}

export interface PlatformTenantCreateProps {
    defaults: PlatformTenantCreateDefaults;
}

export interface PlatformTenantPlanOption {
    id: number;
    code: string;
    name: string;
    billing_currency_code: string;
    currency_scale: number;
    monthly_price_minor: number | null;
    annual_price_minor: number | null;
}

export interface PlatformTenantSubscriptionSummary {
    id: number;
    status: 'trial' | 'active' | 'past_due' | 'suspended' | 'cancelled';
    billing_cycle: 'monthly' | 'annual';
    billing_currency_code: string | null;
    starts_at: string | null;
    trial_ends_at: string | null;
    current_period_starts_at: string | null;
    current_period_ends_at: string | null;
    past_due_at: string | null;
    past_due_reason: 'trial_expired' | 'period_expired' | 'manual' | null;
    grace_ends_at: string | null;
    suspended_at: string | null;
    suspension_reason: 'manual' | 'grace_expired' | null;
    cancelled_at: string | null;
    ends_at: string | null;
    can_extend_trial: boolean;
    plan: PlatformTenantPlanOption;
}



export type PlatformManualSubscriptionStatus =
    | 'trial'
    | 'active'
    | 'past_due'
    | 'suspended'
    | 'cancelled';

export interface PlatformManualSubscriptionFormData {
    saas_plan_id: number | null;
    billing_cycle: 'monthly' | 'annual';
    status: PlatformManualSubscriptionStatus;
    starts_at: string;
    trial_ends_at: string;
    current_period_starts_at: string;
    current_period_ends_at: string;
    past_due_at: string;
    grace_ends_at: string;
    ends_at: string;
}

export interface PlatformTenantShowProps {
    tenant: PlatformTenantDetails;
    subscription: PlatformTenantSubscriptionSummary | null;
    planOptions: PlatformTenantPlanOption[];
}

export type PlatformSaasFeatureValueType = 'boolean' | 'limit';

export interface PlatformSaasFeatureOption {
    key: string;
    name: string;
    description: string | null;
    value_type: PlatformSaasFeatureValueType;
    unit: string | null;
}

export interface PlatformSaasPlanSummary {
    id: number;
    code: string;
    name: string;
    description: string | null;
    billing_currency_code: string;
    currency_scale: number;
    monthly_price_minor: number | null;
    annual_price_minor: number | null;
    status: 'active' | 'inactive';
    is_default: boolean;
    sort_order: number;
    subscriptions_count: number;
    enabled_features_count: number;
}

export interface PlatformSaasPlanEntitlementDetails {
    feature_key: string;
    enabled: boolean;
    limit_value: number | null;
}

export interface PlatformSaasPlanDetails {
    id: number;
    code: string;
    name: string;
    description: string | null;
    billing_currency_code: string;
    currency_scale: number;
    monthly_price: string;
    annual_price: string;
    monthly_price_minor: number | null;
    annual_price_minor: number | null;
    status: 'active' | 'inactive';
    is_default: boolean;
    sort_order: number;
    entitlements: PlatformSaasPlanEntitlementDetails[];
}

export interface PlatformSaasPlanIndexProps {
    plans: PlatformSaasPlanSummary[];
}

export interface PlatformSaasPlanFormProps {
    features: PlatformSaasFeatureOption[];
    plan?: PlatformSaasPlanDetails;
}

export interface PlatformSaasPlanEntitlementFormData {
    feature_key: string;
    enabled: boolean;
    limit_value: string;
}

export interface PlatformSaasPlanFormData {
    code: string;
    name: string;
    description: string;
    billing_currency_code: string;
    currency_scale: number;
    monthly_price: string;
    annual_price: string;
    status: 'active' | 'inactive';
    is_default: boolean;
    sort_order: number;
    entitlements: PlatformSaasPlanEntitlementFormData[];
}


export type PlatformSaasInvoiceStatus =
    | 'open'
    | 'paid'
    | 'void'
    | 'uncollectible';

export interface PlatformSaasInvoiceTenantSummary {
    id: number;
    name: string;
    code: string;
}

export interface PlatformSaasInvoicePlanSummary {
    id: number;
    name: string;
    code: string;
}

export interface PlatformSaasInvoiceSummary {
    id: number;
    invoice_number: string;
    status: PlatformSaasInvoiceStatus;
    billing_cycle: 'monthly' | 'annual';
    currency_code: string;
    currency_scale: number;
    total_minor: number;
    amount_paid_minor: number;
    balance_due_minor: number;
    issued_at: string | null;
    due_at: string | null;
    tenant: PlatformSaasInvoiceTenantSummary;
    plan: PlatformSaasInvoicePlanSummary;
}

export interface PlatformSaasInvoiceLine {
    id: number;
    description: string;
    quantity: number;
    unit_amount_minor: number;
    line_total_minor: number;
}

export interface PlatformSaasPaymentSummary {
    id: number;
    provider: string;
    provider_payment_id: string | null;
    status: 'pending' | 'succeeded' | 'failed' | 'refunded' | 'cancelled';
    amount_minor: number;
    currency_code: string;
    currency_scale: number;
    paid_at: string | null;
    recorded_by: {
        id: number;
        name: string;
    } | null;
}

export interface PlatformSaasInvoiceDetails extends PlatformSaasInvoiceSummary {
    period_starts_at: string | null;
    period_ends_at: string | null;
    paid_at: string | null;
    subtotal_minor: number;
    discount_minor: number;
    tax_minor: number;
    notes: string | null;
    lines: PlatformSaasInvoiceLine[];
    payments: PlatformSaasPaymentSummary[];
}

export interface PlatformSaasInvoicePage {
    data: PlatformSaasInvoiceSummary[];
    meta: PlatformPaginationMeta;
}

export interface PlatformSaasInvoiceFilters {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
}

export interface PlatformSaasInvoiceIndexProps {
    invoicePage: PlatformSaasInvoicePage;
    filters: PlatformSaasInvoiceFilters;
}

export interface PlatformSaasInvoiceShowProps {
    invoice: PlatformSaasInvoiceDetails;
}
