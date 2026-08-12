<script setup lang="ts">
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/vue3';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformManualSubscriptionFormData,
    PlatformTenantShowProps,
    PlatformTenantStatus,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformTenantShowProps>();

const toDateTimeLocal = (value: string | null): string => {
    if (value === null) {
        return '';
    }

    const date = new Date(value);
    const local = new Date(
        date.getTime() - (date.getTimezoneOffset() * 60_000),
    );

    return local.toISOString().slice(0, 16);
};

const localNow = (): string => {
    const date = new Date();
    const local = new Date(
        date.getTime() - (date.getTimezoneOffset() * 60_000),
    );

    return local.toISOString().slice(0, 16);
};

const localDaysFromNow = (days: number): string => {
    const date = new Date();
    date.setDate(date.getDate() + days);

    const local = new Date(
        date.getTime() - (date.getTimezoneOffset() * 60_000),
    );

    return local.toISOString().slice(0, 16);
};

const packageForm = useForm<PlatformManualSubscriptionFormData>({
    saas_plan_id: props.subscription?.plan.id
        ?? props.planOptions[0]?.id
        ?? null,
    billing_cycle: props.subscription?.billing_cycle ?? 'monthly',
    status: props.subscription?.status ?? 'trial',
    starts_at: toDateTimeLocal(props.subscription?.starts_at ?? null)
        || localNow(),
    trial_ends_at: toDateTimeLocal(
        props.subscription?.trial_ends_at ?? null,
    ) || localDaysFromNow(14),
    current_period_starts_at: toDateTimeLocal(
        props.subscription?.current_period_starts_at ?? null,
    ),
    current_period_ends_at: toDateTimeLocal(
        props.subscription?.current_period_ends_at ?? null,
    ),
    past_due_at: toDateTimeLocal(
        props.subscription?.past_due_at ?? null,
    ),
    grace_ends_at: toDateTimeLocal(
        props.subscription?.grace_ends_at ?? null,
    ),
    ends_at: toDateTimeLocal(props.subscription?.ends_at ?? null),
});

const statusClass = (
    status: PlatformTenantStatus,
): string => {
    const classes: Record<PlatformTenantStatus, string> = {
        trial: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
        active: 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300',
        suspended: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        past_due: 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300',
        cancelled: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
        archived: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    };

    return classes[status];
};

const formatStatus = (status: string): string =>
    status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character: string): string =>
            character.toUpperCase(),
        );

const money = (
    minor: number | null,
    currencyCode: string,
    scale: number,
): string => {
    if (minor === null) {
        return 'Not configured';
    }

    const amount = minor / (10 ** scale);

    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: currencyCode,
        }).format(amount);
    } catch {
        return `${currencyCode} ${amount.toFixed(scale)}`;
    }
};

const formatDateTime = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const selectedPlan = () => props.planOptions.find(
    (plan): boolean => plan.id === packageForm.saas_plan_id,
);

const activate = (): void => {
    if (!props.tenant.can_activate) {
        return;
    }

    if (!window.confirm(`Activate ${props.tenant.name}?`)) {
        return;
    }

    router.patch(
        route('platform.tenants.activate', props.tenant.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const suspend = (): void => {
    if (!props.tenant.can_suspend) {
        return;
    }

    if (
        !window.confirm(
            `Suspend ${props.tenant.name}? Tenant ERP access will be blocked.`,
        )
    ) {
        return;
    }

    router.patch(
        route('platform.tenants.suspend', props.tenant.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const savePackageAllocation = (): void => {
    if (packageForm.saas_plan_id === null) {
        return;
    }

    const plan = selectedPlan();
    const planName = plan?.name ?? 'selected package';

    if (
        !window.confirm(
            `Apply ${planName} to ${props.tenant.name} with status ${formatStatus(packageForm.status)}?`,
        )
    ) {
        return;
    }

    packageForm.patch(
        route(
            'platform.tenants.subscription.update',
            props.tenant.id,
        ),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="`Tenant - ${tenant.name}`" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <Link
                    :href="route('platform.tenants.index')"
                    class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                >
                    ← Tenant Management
                </Link>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ tenant.name }}
                    </h1>
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="statusClass(tenant.status)"
                    >
                        {{ formatStatus(tenant.status) }}
                    </span>
                </div>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ tenant.code }} · {{ tenant.slug }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-if="tenant.can_activate"
                    type="button"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700"
                    @click="activate"
                >
                    Activate tenant
                </button>

                <button
                    v-if="tenant.can_suspend"
                    type="button"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-700"
                    @click="suspend"
                >
                    Suspend tenant
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Users</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ tenant.active_users_count }} / {{ tenant.users_count }} active
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Branches</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ tenant.active_branches_count }} / {{ tenant.branches_count }} active
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Warehouses</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ tenant.active_warehouses_count }} / {{ tenant.warehouses_count }} active
                </p>
            </div>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Manual package allocation
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Super Admin controls the package, lifecycle status and dates manually. No payment gateway is required.
                    </p>
                </div>

                <span
                    v-if="subscription"
                    class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                >
                    {{ formatStatus(subscription.status) }}
                </span>
            </div>

            <div v-if="subscription" class="mt-5 rounded-xl bg-gray-50 p-4 dark:bg-gray-900/60">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Current package</p>
                        <p class="mt-1 font-semibold text-gray-900 dark:text-white">
                            {{ subscription.plan.name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Cycle</p>
                        <p class="mt-1 font-semibold text-gray-900 dark:text-white">
                            {{ formatStatus(subscription.billing_cycle) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Period ends</p>
                        <p class="mt-1 font-semibold text-gray-900 dark:text-white">
                            {{ formatDateTime(subscription.current_period_ends_at) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Grace ends</p>
                        <p class="mt-1 font-semibold text-gray-900 dark:text-white">
                            {{ formatDateTime(subscription.grace_ends_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <form class="mt-6 space-y-6" @submit.prevent="savePackageAllocation">
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Package
                        </label>
                        <select
                            v-model="packageForm.saas_plan_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option
                                v-for="plan in planOptions"
                                :key="plan.id"
                                :value="plan.id"
                            >
                                {{ plan.name }} ({{ plan.code }})
                            </option>
                        </select>
                        <p v-if="packageForm.errors.saas_plan_id" class="mt-1 text-sm text-error-500">
                            {{ packageForm.errors.saas_plan_id }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Billing cycle
                        </label>
                        <select
                            v-model="packageForm.billing_cycle"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="monthly">Monthly</option>
                            <option value="annual">Annual</option>
                        </select>
                        <p v-if="packageForm.errors.billing_cycle" class="mt-1 text-sm text-error-500">
                            {{ packageForm.errors.billing_cycle }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Subscription status
                        </label>
                        <select
                            v-model="packageForm.status"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="trial">Trial</option>
                            <option value="active">Active</option>
                            <option value="past_due">Past Due</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <p v-if="packageForm.errors.status" class="mt-1 text-sm text-error-500">
                            {{ packageForm.errors.status }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="selectedPlan()"
                    class="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800"
                >
                    <p class="font-medium text-gray-900 dark:text-white">
                        {{ selectedPlan()?.name }} pricing
                    </p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">
                        Monthly:
                        {{ money(selectedPlan()?.monthly_price_minor ?? null, selectedPlan()?.billing_currency_code ?? 'BDT', selectedPlan()?.currency_scale ?? 2) }}
                        · Annual:
                        {{ money(selectedPlan()?.annual_price_minor ?? null, selectedPlan()?.billing_currency_code ?? 'BDT', selectedPlan()?.currency_scale ?? 2) }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        These prices are package reference values only. Allocation and renewal remain manual.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Subscription starts
                        </label>
                        <input
                            v-model="packageForm.starts_at"
                            type="datetime-local"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                        <p v-if="packageForm.errors.starts_at" class="mt-1 text-sm text-error-500">
                            {{ packageForm.errors.starts_at }}
                        </p>
                    </div>

                    <div v-if="packageForm.status === 'trial'">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Trial ends
                        </label>
                        <input
                            v-model="packageForm.trial_ends_at"
                            type="datetime-local"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                        <p v-if="packageForm.errors.trial_ends_at" class="mt-1 text-sm text-error-500">
                            {{ packageForm.errors.trial_ends_at }}
                        </p>
                    </div>

                    <template v-if="packageForm.status !== 'trial'">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Current period starts
                            </label>
                            <input
                                v-model="packageForm.current_period_starts_at"
                                type="datetime-local"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            />
                            <p v-if="packageForm.errors.current_period_starts_at" class="mt-1 text-sm text-error-500">
                                {{ packageForm.errors.current_period_starts_at }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Current period ends
                            </label>
                            <input
                                v-model="packageForm.current_period_ends_at"
                                type="datetime-local"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            />
                            <p v-if="packageForm.errors.current_period_ends_at" class="mt-1 text-sm text-error-500">
                                {{ packageForm.errors.current_period_ends_at }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Leave blank for an indefinite active period.
                            </p>
                        </div>
                    </template>

                    <template v-if="packageForm.status === 'past_due'">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Past due since
                            </label>
                            <input
                                v-model="packageForm.past_due_at"
                                type="datetime-local"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            />
                            <p v-if="packageForm.errors.past_due_at" class="mt-1 text-sm text-error-500">
                                {{ packageForm.errors.past_due_at }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Grace ends
                            </label>
                            <input
                                v-model="packageForm.grace_ends_at"
                                type="datetime-local"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            />
                            <p v-if="packageForm.errors.grace_ends_at" class="mt-1 text-sm text-error-500">
                                {{ packageForm.errors.grace_ends_at }}
                            </p>
                        </div>
                    </template>

                    <div v-if="packageForm.status === 'cancelled'">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Subscription ends
                        </label>
                        <input
                            v-model="packageForm.ends_at"
                            type="datetime-local"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                        <p v-if="packageForm.errors.ends_at" class="mt-1 text-sm text-error-500">
                            {{ packageForm.errors.ends_at }}
                        </p>
                    </div>
                </div>

                <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-200">
                    <strong>Manual control:</strong>
                    setting status to Active immediately enables the tenant and its package entitlements. Trial/Past Due/Suspended/Cancelled statuses immediately synchronize tenant access with the existing lifecycle rules.
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="packageForm.processing || packageForm.saas_plan_id === null"
                        class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ packageForm.processing ? 'Saving...' : 'Save package allocation' }}
                    </button>
                </div>
            </form>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Company details
                </h2>

                <dl class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Email</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ tenant.email ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Phone</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ tenant.phone ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Currency</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ tenant.currency_code }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Timezone</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ tenant.timezone }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-gray-500">Address</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            {{ tenant.address ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Subscription lifecycle
                </h2>

                <dl v-if="subscription" class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Status</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            {{ formatStatus(subscription.status) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Starts</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ formatDateTime(subscription.starts_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Trial ends</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ formatDateTime(subscription.trial_ends_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Current period</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ formatDateTime(subscription.current_period_starts_at) }}
                            → {{ formatDateTime(subscription.current_period_ends_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Past due since</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ formatDateTime(subscription.past_due_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Grace ends</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ formatDateTime(subscription.grace_ends_at) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Ends</dt>
                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                            {{ formatDateTime(subscription.ends_at) }}
                        </dd>
                    </div>
                </dl>

                <p v-else class="mt-4 text-sm text-amber-600 dark:text-amber-400">
                    No package subscription is currently assigned.
                </p>
            </section>
        </div>
    </div>
</template>
