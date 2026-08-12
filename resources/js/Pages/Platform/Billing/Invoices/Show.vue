<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformSaasInvoiceShowProps,
    PlatformSaasInvoiceStatus,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformSaasInvoiceShowProps>();

const paymentForm = useForm<{
    amount: string;
    reference: string;
    notes: string;
}>({
    amount: props.invoice.balance_due_minor === 0
        ? ''
        : String(
            props.invoice.balance_due_minor
                / (10 ** props.invoice.currency_scale),
        ),
    reference: '',
    notes: '',
});

const money = (minor: number): string => {
    const amount = minor / (10 ** props.invoice.currency_scale);

    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: props.invoice.currency_code,
        }).format(amount);
    } catch {
        return `${props.invoice.currency_code} ${amount.toFixed(props.invoice.currency_scale)}`;
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

const statusClass = (status: PlatformSaasInvoiceStatus): string => {
    const classes: Record<PlatformSaasInvoiceStatus, string> = {
        open: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        paid: 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300',
        void: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        uncollectible: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
    };

    return classes[status];
};

const recordPayment = (): void => {
    if (props.invoice.balance_due_minor <= 0) {
        return;
    }

    if (!window.confirm(`Record manual payment for ${props.invoice.invoice_number}?`)) {
        return;
    }

    paymentForm.post(
        route(
            'platform.billing.invoices.manual-payment',
            props.invoice.id,
        ),
        {
            preserveScroll: true,
            onSuccess: (): void => {
                paymentForm.reference = '';
                paymentForm.notes = '';
            },
        },
    );
};
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number}`" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <Link
                    :href="route('platform.billing.invoices.index')"
                    class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                >
                    ← Billing
                </Link>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ invoice.invoice_number }}
                    </h1>
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                        :class="statusClass(invoice.status)"
                    >
                        {{ invoice.status }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ invoice.tenant.name }} · {{ invoice.plan.name }} · {{ invoice.billing_cycle }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500">Total</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ money(invoice.total_minor) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500">Paid</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ money(invoice.amount_paid_minor) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500">Balance</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ money(invoice.balance_due_minor) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500">Due</p>
                <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ formatDateTime(invoice.due_at) }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice details</h2>
            <dl class="mt-5 grid gap-4 text-sm md:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-gray-500">Tenant</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white"><Link :href="route('platform.tenants.show', invoice.tenant.id)" class="hover:text-brand-600">{{ invoice.tenant.name }}</Link></dd></div>
                <div><dt class="text-gray-500">Issued</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ formatDateTime(invoice.issued_at) }}</dd></div>
                <div><dt class="text-gray-500">Period starts</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ formatDateTime(invoice.period_starts_at) }}</dd></div>
                <div><dt class="text-gray-500">Period ends</dt><dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ formatDateTime(invoice.period_ends_at) }}</dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 p-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lines</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Description</th>
                            <th class="px-5 py-3 text-right">Qty</th>
                            <th class="px-5 py-3 text-right">Unit price</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="line in invoice.lines" :key="line.id" class="text-sm">
                            <td class="px-5 py-4 text-gray-800 dark:text-gray-200">{{ line.description }}</td>
                            <td class="px-5 py-4 text-right text-gray-600 dark:text-gray-400">{{ line.quantity }}</td>
                            <td class="px-5 py-4 text-right text-gray-700 dark:text-gray-300">{{ money(line.unit_amount_minor) }}</td>
                            <td class="px-5 py-4 text-right font-medium text-gray-900 dark:text-white">{{ money(line.line_total_minor) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-if="invoice.balance_due_minor > 0 && invoice.status === 'open'"
            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Record manual payment</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Use this only to record bank transfer, cash, or other offline settlement. Recording a payment does not activate, renew, extend, suspend, or cancel the tenant package; subscription access remains controlled manually by Super Admin.
            </p>

            <form class="mt-5 grid gap-4 md:grid-cols-3" @submit.prevent="recordPayment">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Amount</label>
                    <input v-model="paymentForm.amount" type="number" min="0.01" step="any" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white">
                    <p v-if="paymentForm.errors.amount" class="mt-1 text-sm text-error-500">{{ paymentForm.errors.amount }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reference</label>
                    <input v-model="paymentForm.reference" type="text" maxlength="150" placeholder="Bank/payment reference" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                    <input v-model="paymentForm.notes" type="text" maxlength="1000" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white">
                </div>
                <div class="md:col-span-3">
                    <button type="submit" :disabled="paymentForm.processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60">
                        {{ paymentForm.processing ? 'Recording...' : 'Record payment' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 p-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payments</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <div v-for="payment in invoice.payments" :key="payment.id" class="grid gap-2 px-5 py-4 text-sm md:grid-cols-4">
                    <div><span class="text-gray-500">Provider</span><p class="font-medium text-gray-900 dark:text-white">{{ payment.provider }}</p></div>
                    <div><span class="text-gray-500">Reference</span><p class="font-medium text-gray-900 dark:text-white">{{ payment.provider_payment_id ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Amount</span><p class="font-medium text-gray-900 dark:text-white">{{ money(payment.amount_minor) }}</p></div>
                    <div><span class="text-gray-500">Paid at</span><p class="font-medium text-gray-900 dark:text-white">{{ formatDateTime(payment.paid_at) }}</p></div>
                </div>
                <p v-if="invoice.payments.length === 0" class="px-5 py-8 text-center text-sm text-gray-500">No payments recorded.</p>
            </div>
        </section>
    </div>
</template>
