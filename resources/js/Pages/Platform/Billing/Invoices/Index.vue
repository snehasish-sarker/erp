<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformSaasInvoiceIndexProps,
    PlatformSaasInvoiceStatus,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformSaasInvoiceIndexProps>();

const applyFilters = (event: Event): void => {
    const form = new FormData(event.currentTarget as HTMLFormElement);

    router.get(
        route('platform.billing.invoices.index'),
        {
            search: String(form.get('search') ?? ''),
            status: String(form.get('status') ?? ''),
            sort: props.filters.sort,
            direction: props.filters.direction,
            per_page: props.filters.per_page,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const statusClass = (status: PlatformSaasInvoiceStatus): string => {
    const classes: Record<PlatformSaasInvoiceStatus, string> = {
        open: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        paid: 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300',
        void: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        uncollectible: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
    };

    return classes[status];
};

const money = (
    minor: number,
    currencyCode: string,
    scale: number,
): string => {
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

const formatDate = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
    }).format(new Date(value));
};
</script>

<template>
    <Head title="SaaS Billing" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                SaaS Billing
            </h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Platform invoices and payment status across all tenants.
            </p>
        </div>

        <form
            class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:grid-cols-[minmax(0,1fr)_200px_auto] dark:border-gray-800 dark:bg-white/[0.03]"
            @submit.prevent="applyFilters"
        >
            <input
                name="search"
                :value="filters.search"
                type="search"
                placeholder="Invoice, tenant, or company code"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
            >

            <select
                name="status"
                :value="filters.status"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
                <option value="">All statuses</option>
                <option value="open">Open</option>
                <option value="paid">Paid</option>
                <option value="void">Void</option>
                <option value="uncollectible">Uncollectible</option>
            </select>

            <button
                type="submit"
                class="h-11 rounded-lg bg-brand-600 px-4 text-sm font-medium text-white transition hover:bg-brand-700"
            >
                Apply
            </button>
        </form>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Invoice</th>
                            <th class="px-5 py-3">Tenant</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">Issued / Due</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="invoice in invoicePage.data"
                            :key="invoice.id"
                            class="text-sm"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    :href="route('platform.billing.invoices.show', invoice.id)"
                                    class="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{ invoice.invoice_number }}
                                </Link>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ invoice.billing_cycle }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">
                                <Link
                                    :href="route('platform.tenants.show', invoice.tenant.id)"
                                    class="font-medium hover:text-brand-600"
                                >
                                    {{ invoice.tenant.name }}
                                </Link>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ invoice.tenant.code }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">
                                {{ invoice.plan.name }}
                            </td>
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-400">
                                <div>{{ formatDate(invoice.issued_at) }}</div>
                                <div class="mt-1 text-xs">Due {{ formatDate(invoice.due_at) }}</div>
                            </td>
                            <td class="px-5 py-4 text-right font-medium text-gray-900 dark:text-white">
                                {{ money(invoice.total_minor, invoice.currency_code, invoice.currency_scale) }}
                            </td>
                            <td class="px-5 py-4 text-right font-medium text-gray-900 dark:text-white">
                                {{ money(invoice.balance_due_minor, invoice.currency_code, invoice.currency_scale) }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                                    :class="statusClass(invoice.status)"
                                >
                                    {{ invoice.status }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="invoicePage.data.length === 0">
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">
                                No SaaS invoices found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                <p class="text-sm text-gray-500">
                    Showing {{ invoicePage.meta.from ?? 0 }}–{{ invoicePage.meta.to ?? 0 }} of {{ invoicePage.meta.total }}
                </p>
                <div class="flex gap-2">
                    <Link
                        v-if="invoicePage.meta.previous_page_url"
                        :href="invoicePage.meta.previous_page_url"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    >
                        Previous
                    </Link>
                    <Link
                        v-if="invoicePage.meta.next_page_url"
                        :href="invoicePage.meta.next_page_url"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    >
                        Next
                    </Link>
                </div>
            </div>
        </section>
    </div>
</template>
