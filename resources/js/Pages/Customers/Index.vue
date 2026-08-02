<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
    ref,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    CustomerFilters,
    CustomerOption,
    CustomerPagination,
    CustomerRecord,
    CustomerSort,
    CustomerStatus,
    CustomerType,
} from '@/Types/customer';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    customers: CustomerPagination;
    filters: CustomerFilters;
    customerTypeOptions:
        CustomerOption<CustomerType>[];
    statusOptions:
        CustomerOption<CustomerStatus>[];
    canManageCreditLimit: boolean;
}>();

const { can } = useAuthorization();

const filters = reactive<CustomerFilters>({
    search: props.filters.search,
    customer_type:
        props.filters.customer_type,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const deletingCustomerId: Ref<number | null> =
    ref(null);

const hasActiveFilters: ComputedRef<boolean> =
    computed(
        (): boolean =>
            filters.search !== ''
            || filters.customer_type !== ''
            || filters.status !== '',
    );

const navigate = (page = 1): void => {
    router.get(
        '/erp/customers',
        {
            search: filters.search,
            customer_type:
                filters.customer_type,
            status: filters.status,
            sort: filters.sort,
            direction: filters.direction,
            per_page: filters.per_page,
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyFilters = (): void => {
    filters.search = filters.search.trim();

    navigate();
};

const resetFilters = (): void => {
    filters.search = '';
    filters.customer_type = '';
    filters.status = '';
    filters.sort = 'name';
    filters.direction = 'asc';
    filters.per_page = 25;

    navigate();
};

const sortBy = (
    column: CustomerSort,
): void => {
    if (
        column === 'credit_limit'
        && !props.canManageCreditLimit
    ) {
        return;
    }

    if (filters.sort === column) {
        filters.direction =
            filters.direction === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        filters.sort = column;
        filters.direction = 'asc';
    }

    navigate();
};

const sortIndicator = (
    column: CustomerSort,
): string => {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc'
        ? '↑'
        : '↓';
};

const deleteCustomer = (
    customer: CustomerRecord,
): void => {
    const confirmed = window.confirm(
        `Delete customer “${customer.name} (${customer.code})”? Its code and statutory identifiers will remain reserved.`,
    );

    if (!confirmed) {
        return;
    }

    deletingCustomerId.value = customer.id;

    router.delete(
        `/erp/customers/${customer.id}`,
        {
            preserveScroll: true,

            onFinish: (): void => {
                deletingCustomerId.value = null;
            },
        },
    );
};

const statusBadgeClass = (
    status: CustomerStatus,
): string =>
    status === 'active'
        ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
        : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400';

const typeBadgeClass = (
    customerType: CustomerType,
): string => {
    if (customerType === 'company') {
        return 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400';
    }

    if (customerType === 'individual') {
        return 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400';
    }

    if (customerType === 'government') {
        return 'bg-purple-50 text-purple-700 dark:bg-purple-500/15 dark:text-purple-400';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
};

const formatBillingLocation = (
    customer: CustomerRecord,
): string => {
    const parts = [
        customer.billing_city,
        customer.billing_state,
        customer.billing_country_code,
    ].filter(
        (value: string | null): value is string =>
            value !== null
            && value !== '',
    );

    return parts.length > 0
        ? parts.join(', ')
        : '—';
};

const paymentTermsLabel = (
    days: number,
): string => {
    if (days === 0) {
        return 'Immediate';
    }

    return `${days} ${days === 1 ? 'day' : 'days'}`;
};

const formatAmount = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(amount);
};

const formatDate = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        },
    ).format(new Date(value));
};
</script>

<template>
    <Head title="Customers" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Customers
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage tenant-wide customer identity,
                    contacts, payment terms, and credit controls.
                </p>
            </div>

            <Link
                v-if="can('customers.create')"
                href="/erp/customers/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Create customer
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 lg:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <div class="sm:col-span-2">
                    <label
                        for="customer-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="customer-search"
                        v-model="filters.search"
                        type="search"
                        maxlength="160"
                        placeholder="Name, code, contact, phone, tax, registration, or location"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="customer-type-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Customer type
                    </label>

                    <select
                        id="customer-type-filter"
                        v-model="filters.customer_type"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All types
                        </option>

                        <option
                            v-for="option in customerTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="customer-status-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="customer-status-filter"
                        v-model="filters.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="customer-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="customer-per-page"
                        v-model.number="filters.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">
                            10
                        </option>

                        <option :value="25">
                            25
                        </option>

                        <option :value="50">
                            50
                        </option>

                        <option :value="100">
                            100
                        </option>
                    </select>
                </div>

                <div
                    class="flex flex-wrap items-end gap-3 sm:col-span-2 lg:col-span-3"
                >
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white transition hover:bg-brand-600"
                    >
                        Apply filters
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="sortBy('name')"
                                >
                                    Customer
                                    {{ sortIndicator('name') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="sortBy('code')"
                                >
                                    Code
                                    {{ sortIndicator('code') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="sortBy('customer_type')"
                                >
                                    Type
                                    {{
                                        sortIndicator(
                                            'customer_type',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Contact
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Billing location
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="
                                        sortBy(
                                            'payment_terms_days',
                                        )
                                    "
                                >
                                    Payment terms
                                    {{
                                        sortIndicator(
                                            'payment_terms_days',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                v-if="canManageCreditLimit"
                                class="px-5 py-3 text-right"
                            >
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="
                                        sortBy('credit_limit')
                                    "
                                >
                                    Credit limit
                                    {{
                                        sortIndicator(
                                            'credit_limit',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="sortBy('status')"
                                >
                                    Status
                                    {{ sortIndicator('status') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="sortBy('created_at')"
                                >
                                    Created
                                    {{
                                        sortIndicator(
                                            'created_at',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ customer.name }}
                                </p>

                                <p
                                    v-if="customer.contact_person"
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Contact:
                                    {{ customer.contact_person }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded bg-gray-100 px-2 py-1 font-mono text-sm text-gray-700 dark:bg-white/10 dark:text-gray-300"
                                >
                                    {{ customer.code }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        typeBadgeClass(
                                            customer.customer_type,
                                        )
                                    "
                                >
                                    {{
                                        customer.customer_type_label
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <a
                                    v-if="customer.email"
                                    :href="`mailto:${customer.email}`"
                                    class="block max-w-56 truncate text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{ customer.email }}
                                </a>

                                <p
                                    v-if="customer.phone"
                                    class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ customer.phone }}
                                </p>

                                <span
                                    v-if="
                                        !customer.email
                                        && !customer.phone
                                    "
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    —
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatBillingLocation(
                                        customer,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    paymentTermsLabel(
                                        customer.payment_terms_days,
                                    )
                                }}
                            </td>

                            <td
                                v-if="canManageCreditLimit"
                                class="px-5 py-4 text-right font-mono text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatAmount(
                                        customer.credit_limit,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusBadgeClass(
                                            customer.status,
                                        )
                                    "
                                >
                                    {{
                                        customer.status
                                            === 'active'
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDate(
                                        customer.created_at,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <div
                                    class="flex justify-end gap-3"
                                >
                                    <Link
                                        v-if="can('customers.update')"
                                        :href="`/erp/customers/${customer.id}/edit`"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="can('customers.delete')"
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            deletingCustomerId
                                            === customer.id
                                        "
                                        @click="
                                            deleteCustomer(
                                                customer,
                                            )
                                        "
                                    >
                                        {{
                                            deletingCustomerId
                                                === customer.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                customers.data.length === 0
                            "
                        >
                            <td
                                :colspan="
                                    canManageCreditLimit
                                        ? 10
                                        : 9
                                "
                                class="px-5 py-12 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No customers found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Create a customer or adjust
                                    the current filters.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{ customers.meta.from ?? 0 }}–{{
                        customers.meta.to ?? 0
                    }}
                    of {{ customers.meta.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            customers.meta.current_page <= 1
                        "
                        @click="
                            navigate(
                                customers.meta.current_page
                                    - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Page
                        {{ customers.meta.current_page }}
                        of {{ customers.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            customers.meta.current_page
                            >= customers.meta.last_page
                        "
                        @click="
                            navigate(
                                customers.meta.current_page
                                    + 1,
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>