export interface PlatformAdminSummary {
    id: number;
    name: string;
    email: string;
    last_login_at: string | null;
}

export interface PlatformDashboardMetrics {
    tenants_total: number;
    tenant_users_total: number;
    active_tenant_users: number;
    subscriptions_trial: number;
    subscriptions_active: number;
    subscriptions_past_due: number;
    subscriptions_suspended: number;
    subscriptions_cancelled: number;
    subscriptions_no_subscription: number;
    subscriptions_expiring_soon: number;
    subscriptions_expired: number;
    subscriptions_indefinite_active: number;
}

export interface PlatformDashboardPackageDistributionItem {
    plan_id: number | null;
    code: string | null;
    name: string;
    status: 'active' | 'inactive' | null;
    subscriptions_count: number;
    percentage: number;
}

export type PlatformDashboardSubscriptionAlertStatus =
    | 'expired'
    | 'past_due'
    | 'suspended'
    | 'expiring_soon';

export interface PlatformDashboardSubscriptionAlert {
    tenant: {
        id: number;
        name: string;
        code: string;
        status: PlatformTenantStatus;
    };
    plan: {
        id: number | null;
        name: string;
        code: string | null;
    };
    subscription_status: PlatformManualSubscriptionStatus;
    alert_status: PlatformDashboardSubscriptionAlertStatus;
    effective_expiry_at: string | null;
    days_remaining: number | null;
}

export interface PlatformDashboardProps {
    platformAdmin: PlatformAdminSummary;
    metrics: PlatformDashboardMetrics;
    usageMetrics: PlatformSaasUsageMetrics;
    recentChanges30Days: number;
    packageDistribution: PlatformDashboardPackageDistributionItem[];
    subscriptionAlerts: PlatformDashboardSubscriptionAlert[];
    usageAlerts: PlatformSaasUsageRow[];
    recentActivity: PlatformSubscriptionHistoryRow[];
    expiringSoonDays: number;
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

export type PlatformSubscriptionQuickAction =
    | 'extend_trial_7'
    | 'extend_trial_14'
    | 'extend_trial_30'
    | 'extend_month'
    | 'extend_year'
    | 'renew_monthly'
    | 'renew_annual'
    | 'activate_indefinite';

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

export interface PlatformSubscriptionPlanOption {
    id: number;
    code: string;
    name: string;
    status: 'active' | 'inactive';
}

export interface PlatformSubscriptionTenantSummary {
    id: number;
    name: string;
    code: string;
    email: string | null;
    status: PlatformTenantStatus;
}

export interface PlatformSubscriptionDashboardRow {
    tenant: PlatformSubscriptionTenantSummary;
    subscription_id: number | null;
    subscription_status: PlatformManualSubscriptionStatus | null;
    billing_cycle: 'monthly' | 'annual' | null;
    starts_at: string | null;
    trial_ends_at: string | null;
    current_period_starts_at: string | null;
    current_period_ends_at: string | null;
    past_due_at: string | null;
    past_due_reason: 'trial_expired' | 'period_expired' | 'manual' | null;
    can_extend_trial: boolean;
    grace_ends_at: string | null;
    suspended_at: string | null;
    cancelled_at: string | null;
    ends_at: string | null;
    effective_expiry_at: string | null;
    days_remaining: number | null;
    is_expired: boolean;
    is_indefinite: boolean;
    access_active: boolean;
    plan: PlatformSubscriptionPlanOption | null;
}

export interface PlatformSubscriptionDashboardPage {
    data: PlatformSubscriptionDashboardRow[];
    meta: PlatformPaginationMeta;
}

export type PlatformSubscriptionExpiryFilter =
    | ''
    | 'expiring_soon'
    | 'expired'
    | 'indefinite';

export type PlatformSubscriptionStatusFilter =
    | ''
    | PlatformManualSubscriptionStatus
    | 'no_subscription';

export interface PlatformSubscriptionDashboardFilters {
    search: string;
    saas_plan_id: number | null;
    status: PlatformSubscriptionStatusFilter;
    expiry: PlatformSubscriptionExpiryFilter;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
}

export interface PlatformSubscriptionDashboardMetrics {
    tenants_total: number;
    trial: number;
    active: number;
    past_due: number;
    suspended: number;
    expiring_soon: number;
    expired: number;
    no_subscription: number;
}

export interface PlatformSubscriptionDashboardProps {
    subscriptionPage: PlatformSubscriptionDashboardPage;
    filters: PlatformSubscriptionDashboardFilters;
    planOptions: PlatformSubscriptionPlanOption[];
    metrics: PlatformSubscriptionDashboardMetrics;
    expiringSoonDays: number;
}

export type PlatformSaasUsageResourceKey =
    | 'users'
    | 'branches'
    | 'warehouses'
    | 'products'
    | 'storage';

export type PlatformSaasUsageCapacityStatus =
    | 'healthy'
    | 'near_limit'
    | 'at_limit'
    | 'over_limit'
    | 'unlimited'
    | 'not_included'
    | 'no_subscription';

export interface PlatformSaasUsageResource {
    key: PlatformSaasUsageResourceKey;
    label: string;
    usage: number;
    limit: number | null;
    remaining: number | null;
    percentage: number | null;
    unit: 'count' | 'MB';
    status: PlatformSaasUsageCapacityStatus;
}

export interface PlatformSaasUsageResources {
    users: PlatformSaasUsageResource;
    branches: PlatformSaasUsageResource;
    warehouses: PlatformSaasUsageResource;
    products: PlatformSaasUsageResource;
    storage: PlatformSaasUsageResource;
}

export interface PlatformSaasUsagePlanSummary {
    id: number;
    code: string;
    name: string;
    status: 'active' | 'inactive';
}

export interface PlatformSaasUsageTenantSummary {
    id: number;
    name: string;
    code: string;
    email: string | null;
    status: PlatformTenantStatus;
}

export interface PlatformSaasUsageRow {
    tenant: PlatformSaasUsageTenantSummary;
    subscription_status: PlatformManualSubscriptionStatus | null;
    plan: PlatformSaasUsagePlanSummary | null;
    overall_status:
        | 'healthy'
        | 'near_limit'
        | 'at_limit'
        | 'over_limit'
        | 'no_subscription';
    resources: PlatformSaasUsageResources;
}

export interface PlatformSaasUsagePage {
    data: PlatformSaasUsageRow[];
    meta: PlatformPaginationMeta;
}

export type PlatformSaasUsageResourceFilter =
    | 'all'
    | PlatformSaasUsageResourceKey;

export type PlatformSaasUsageCapacityFilter =
    | ''
    | 'healthy'
    | 'near_limit'
    | 'at_limit'
    | 'over_limit';

export interface PlatformSaasUsageFilters {
    search: string;
    saas_plan_id: number | null;
    tenant_status: '' | PlatformTenantStatus;
    subscription_status: '' | PlatformManualSubscriptionStatus | 'no_subscription';
    resource: PlatformSaasUsageResourceFilter;
    capacity: PlatformSaasUsageCapacityFilter;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
}

export interface PlatformSaasUsageMetrics {
    tenants_total: number;
    healthy: number;
    near_limit: number;
    at_limit: number;
    over_limit: number;
    no_subscription: number;
}

export interface PlatformSaasUsageProps {
    usagePage: PlatformSaasUsagePage;
    filters: PlatformSaasUsageFilters;
    planOptions: PlatformSaasUsagePlanSummary[];
    metrics: PlatformSaasUsageMetrics;
    nearLimitPercent: number;
}

export type PlatformSubscriptionHistoryEvent =
    | 'saas_plan_assigned'
    | 'saas_subscription_manually_updated'
    | 'saas_subscription_manually_activated'
    | 'saas_subscription_manually_suspended'
    | 'saas_subscription_quick_action_applied'
    | 'saas_trial_extended'
    | 'saas_subscription_past_due'
    | 'saas_subscription_suspended';

export type PlatformSubscriptionHistoryActorType =
    | 'platform_admin'
    | 'system';

export interface PlatformSubscriptionHistoryTenant {
    id: number;
    name: string;
    code: string;
}

export interface PlatformSubscriptionHistoryActor {
    type: PlatformSubscriptionHistoryActorType;
    name: string | null;
    email: string | null;
}

export interface PlatformSubscriptionHistoryChange {
    field: string;
    label: string;
    old_value: string | null;
    new_value: string | null;
}

export interface PlatformSubscriptionHistoryMetadataItem {
    label: string;
    value: string;
}

export interface PlatformSubscriptionHistoryRow {
    id: number;
    event: PlatformSubscriptionHistoryEvent;
    event_label: string;
    tenant: PlatformSubscriptionHistoryTenant;
    actor: PlatformSubscriptionHistoryActor;
    reason: string | null;
    changes: PlatformSubscriptionHistoryChange[];
    metadata: PlatformSubscriptionHistoryMetadataItem[];
    request_id: string | null;
    route_name: string | null;
    ip_address: string | null;
    created_at: string | null;
}

export interface PlatformSubscriptionHistoryPage {
    data: PlatformSubscriptionHistoryRow[];
    meta: PlatformPaginationMeta;
}

export interface PlatformSubscriptionHistoryFilters {
    search: string;
    tenant_id: number | null;
    event: '' | PlatformSubscriptionHistoryEvent;
    actor_type: '' | PlatformSubscriptionHistoryActorType;
    date_from: string;
    date_to: string;
    sort: 'created_at' | 'tenant' | 'event' | 'actor';
    direction: 'asc' | 'desc';
    per_page: number;
}

export interface PlatformSubscriptionHistoryEventOption {
    value: PlatformSubscriptionHistoryEvent;
    label: string;
}

export interface PlatformSubscriptionHistoryMetrics {
    total_events: number;
    manual_actions: number;
    lifecycle_actions: number;
    trial_extensions: number;
    last_30_days: number;
}

export interface PlatformSubscriptionHistoryProps {
    historyPage: PlatformSubscriptionHistoryPage;
    filters: PlatformSubscriptionHistoryFilters;
    eventOptions: PlatformSubscriptionHistoryEventOption[];
    metrics: PlatformSubscriptionHistoryMetrics;
    selectedTenant: PlatformSubscriptionHistoryTenant | null;
}
