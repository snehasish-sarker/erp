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
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    SupplierFilters,
    SupplierOption,
    SupplierPagination,
    SupplierRecord,
    SupplierSort,
    SupplierStatus,
    SupplierType,
} from '@/Types/supplier';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    suppliers: SupplierPagination;
    filters: SupplierFilters;
    supplierTypeOptions:
        SupplierOption<SupplierType>[];
    statusOptions:
        SupplierOption<SupplierStatus>[];
}>();

const { can } = useAuthorization();

const filters = reactive<SupplierFilters>({
    search: props.filters.search,
    supplier_type:
        props.filters.supplier_type,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const deletingSupplierId: Ref<number | null> =
    ref(null);

const hasActiveFilters: ComputedRef<boolean> =
    computed(
        (): boolean =>
            filters.search !== ''
            || filters.supplier_type !== ''
            || filters.status !== '',
    );

const navigate = (page = 1): void => {
    router.get(
        '/erp/suppliers',
        {
            search: filters.search,
            supplier_type:
                filters.supplier_type,
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
    filters.supplier_type = '';
    filters.status = '';
    filters.sort = 'name';
    filters.direction = 'asc';
    filters.per_page = 25;

    navigate();
};

const sortBy = (
    column: SupplierSort,
): void => {
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
    column: SupplierSort,
): string => {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc'
        ? '↑'
        : '↓';
};

const deleteSupplier = (
    supplier: SupplierRecord,
): void => {
    const confirmed = window.confirm(
        `Delete supplier “${supplier.name} (${supplier.code})”? Its code and statutory identifiers will remain reserved.`,
    );

    if (!confirmed) {
        return;
    }

    deletingSupplierId.value = supplier.id;

    router.delete(
        `/erp/suppliers/${supplier.id}`,
        {
            preserveScroll: true,

            onFinish: (): void => {
                deletingSupplierId.value = null;
            },
        },
    );
};

const statusBadgeClass = (
    status: SupplierStatus,
): string =>
    status === 'active'
        ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
        : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400';

const typeBadgeClass = (
    supplierType: SupplierType,
): string => {
    if (supplierType === 'company') {
        return 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400';
    }

    if (supplierType === 'individual') {
        return 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400';
    }

    if (supplierType === 'government') {
        return 'bg-purple-50 text-purple-700 dark:bg-purple-500/15 dark:text-purple-400';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
};

const formatLocation = (
    supplier: SupplierRecord,
): string => {
    const parts = [
        supplier.city,
        supplier.state,
        supplier.country_code,
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
    <Head title="Suppliers" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Suppliers
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage tenant-wide supplier identity,
                    contacts, and payment terms.
                </p>
            </div>

            <Link
                v-if="can('suppliers.create')"
                href="/erp/suppliers/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Create supplier
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
                        for="supplier-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="supplier-search"
                        v-model="filters.search"
                        type="search"
                        maxlength="160"
                        placeholder="Name, code, contact, phone, tax, or registration"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="supplier-type-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Supplier type
                    </label>

                    <select
                        id="supplier-type-filter"
                        v-model="filters.supplier_type"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All types
                        </option>

                        <option
                            v-for="option in supplierTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-status-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="supplier-status-filter"
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
                        for="supplier-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="supplier-per-page"
                        v-model.number="filters.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
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
                                    Supplier
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
                                    @click="sortBy('supplier_type')"
                                >
                                    Type
                                    {{ sortIndicator('supplier_type') }}
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
                                Location
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    @click="sortBy('payment_terms_days')"
                                >
                                    Payment terms
                                    {{
                                        sortIndicator(
                                            'payment_terms_days',
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
                                    {{ sortIndicator('created_at') }}
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
                            v-for="supplier in suppliers.data"
                            :key="supplier.id"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ supplier.name }}
                                </p>

                                <p
                                    v-if="supplier.contact_person"
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Contact:
                                    {{ supplier.contact_person }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded bg-gray-100 px-2 py-1 font-mono text-sm text-gray-700 dark:bg-white/10 dark:text-gray-300"
                                >
                                    {{ supplier.code }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        typeBadgeClass(
                                            supplier.supplier_type,
                                        )
                                    "
                                >
                                    {{
                                        supplier.supplier_type_label
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <a
                                    v-if="supplier.email"
                                    :href="`mailto:${supplier.email}`"
                                    class="block max-w-56 truncate text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{ supplier.email }}
                                </a>

                                <p
                                    v-if="supplier.phone"
                                    class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ supplier.phone }}
                                </p>

                                <span
                                    v-if="
                                        !supplier.email
                                        && !supplier.phone
                                    "
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    —
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ formatLocation(supplier) }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    paymentTermsLabel(
                                        supplier.payment_terms_days,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusBadgeClass(
                                            supplier.status,
                                        )
                                    "
                                >
                                    {{
                                        supplier.status === 'active'
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
                                        supplier.created_at,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <div
                                    class="flex justify-end gap-3"
                                >
                                    <Link
                                        v-if="can('suppliers.update')"
                                        :href="`/erp/suppliers/${supplier.id}/edit`"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="can('suppliers.delete')"
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            deletingSupplierId
                                            === supplier.id
                                        "
                                        @click="
                                            deleteSupplier(
                                                supplier,
                                            )
                                        "
                                    >
                                        {{
                                            deletingSupplierId
                                                === supplier.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                suppliers.data.length === 0
                            "
                        >
                            <td
                                colspan="9"
                                class="px-5 py-12 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No suppliers found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Create a supplier or adjust
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
                    {{ suppliers.meta.from ?? 0 }}–{{
                        suppliers.meta.to ?? 0
                    }}
                    of {{ suppliers.meta.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            suppliers.meta.current_page <= 1
                        "
                        @click="
                            navigate(
                                suppliers.meta.current_page
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
                        {{ suppliers.meta.current_page }}
                        of {{ suppliers.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            suppliers.meta.current_page
                            >= suppliers.meta.last_page
                        "
                        @click="
                            navigate(
                                suppliers.meta.current_page
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