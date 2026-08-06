<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    CustomerCreditNoteDetail,
} from '@/Types/customer-credit-note';

const props = defineProps<{
    creditNote: CustomerCreditNoteDetail;
    company: {
        name: string;
        code: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
}>();

const title = computed((): string => {
    return props.creditNote.credit_note_number
        ?? `Credit Note Draft #${props.creditNote.id}`;
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(date);
};

const formatAmount = (value: string | number): string => {
    const parsed = typeof value === 'number'
        ? value
        : Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return String(value);
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(parsed);
};

const formatQuantity = (value: string | number): string => {
    const parsed = typeof value === 'number'
        ? value
        : Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return String(value);
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 6,
    }).format(parsed);
};

const printPage = (): void => {
    window.print();
};
</script>

<template>
    <Head :title="`Customer Credit Note ${title}`" />

    <div class="print-page">
        <div class="screen-actions">
            <button type="button" @click="printPage">
                Print / Save PDF
            </button>
        </div>

        <header class="header">
            <div>
                <h1>{{ company.name }}</h1>
                <p v-if="company.address">{{ company.address }}</p>
                <p>
                    <span v-if="company.phone">{{ company.phone }}</span>
                    <span v-if="company.phone && company.email"> · </span>
                    <span v-if="company.email">{{ company.email }}</span>
                </p>
            </div>

            <div class="document-title">
                <h2>CUSTOMER CREDIT NOTE</h2>
                <p>{{ title }}</p>
                <span :class="['status', creditNote.status]">
                    {{ creditNote.status_label }}
                </span>
            </div>
        </header>

        <section class="meta-grid">
            <div>
                <h3>Credit To</h3>
                <strong>{{ creditNote.customer_name }}</strong>
                <p>{{ creditNote.customer_code }}</p>
                <p v-if="creditNote.customer_contact_person">{{ creditNote.customer_contact_person }}</p>
                <p v-if="creditNote.customer_phone">{{ creditNote.customer_phone }}</p>
                <p v-if="creditNote.customer_email">{{ creditNote.customer_email }}</p>
                <p class="pre-line">{{ creditNote.billing_address ?? '—' }}</p>
                <p v-if="creditNote.customer_tax_number">
                    Tax Number: {{ creditNote.customer_tax_number }}
                </p>
            </div>

            <div>
                <h3>Document Information</h3>
                <dl>
                    <dt>Credit Note Date</dt>
                    <dd>{{ formatDate(creditNote.credit_note_date) }}</dd>
                    <dt>Posting Date</dt>
                    <dd>{{ formatDate(creditNote.posting_date) }}</dd>
                    <dt>Sales Invoice</dt>
                    <dd>{{ creditNote.sales_invoice_number }}</dd>
                    <dt>Sales Order</dt>
                    <dd>{{ creditNote.sales_order_number }}</dd>
                    <dt>Branch</dt>
                    <dd>{{ creditNote.branch?.name ?? '—' }}</dd>
                    <dt>Return Warehouse</dt>
                    <dd>{{ creditNote.warehouse?.name ?? 'No stock return' }}</dd>
                    <dt>Currency</dt>
                    <dd>{{ creditNote.currency_code }}</dd>
                    <dt>Reason</dt>
                    <dd>{{ creditNote.reason }}</dd>
                </dl>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product / Description</th>
                    <th>Type</th>
                    <th class="right">Quantity</th>
                    <th>Stock Return</th>
                    <th class="right">Subtotal</th>
                    <th class="right">Tax</th>
                    <th class="right">Credit Amount</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="line in creditNote.lines" :key="line.id">
                    <td>{{ line.line_number }}</td>
                    <td>
                        <strong>{{ line.product_name }}</strong>
                        <p>{{ line.product_sku }} · {{ line.unit_code }}</p>
                        <p v-if="line.description">{{ line.description }}</p>
                    </td>
                    <td class="capitalize">{{ line.line_type }}</td>
                    <td class="right">
                        {{ line.line_type === 'quantity' ? formatQuantity(line.credit_quantity) : '—' }}
                    </td>
                    <td>{{ line.return_to_stock ? 'Yes' : 'No' }}</td>
                    <td class="right">{{ formatAmount(line.subtotal) }}</td>
                    <td class="right">
                        {{ formatAmount(line.tax_amount) }}
                        <small>{{ formatQuantity(line.tax_rate) }}%</small>
                    </td>
                    <td class="right">{{ formatAmount(line.line_total) }}</td>
                </tr>
            </tbody>
        </table>

        <section class="summary-wrap">
            <div class="notes-column">
                <div v-if="creditNote.return_address">
                    <h3>Return Address</h3>
                    <p class="pre-line">{{ creditNote.return_address }}</p>
                </div>

                <div v-if="creditNote.notes">
                    <h3>Notes</h3>
                    <p class="pre-line">{{ creditNote.notes }}</p>
                </div>

                <div v-if="creditNote.automatic_allocation">
                    <h3>Automatic Invoice Settlement</h3>
                    <p>
                        {{ creditNote.currency_code }}
                        {{ formatAmount(creditNote.automatic_allocation.amount) }}
                        applied to {{ creditNote.sales_invoice_number }}.
                    </p>
                </div>
            </div>

            <dl class="summary">
                <dt>Gross</dt>
                <dd>{{ formatAmount(creditNote.gross_amount) }}</dd>
                <dt>Discount Reversal</dt>
                <dd>-{{ formatAmount(creditNote.discount_amount) }}</dd>
                <dt>Subtotal</dt>
                <dd>{{ formatAmount(creditNote.subtotal) }}</dd>
                <dt>Output Tax Reversal</dt>
                <dd>{{ formatAmount(creditNote.tax_amount) }}</dd>
                <dt class="total-label">Credit Total</dt>
                <dd class="total-value">
                    {{ creditNote.currency_code }} {{ formatAmount(creditNote.total_amount) }}
                </dd>
                <template v-if="creditNote.customer_open_item">
                    <dt>Applied Credit</dt>
                    <dd>{{ formatAmount(creditNote.customer_open_item.allocated_amount) }}</dd>
                    <dt class="balance-label">Unallocated Credit</dt>
                    <dd class="balance-value">
                        {{ creditNote.currency_code }}
                        {{ formatAmount(creditNote.customer_open_item.outstanding_amount) }}
                    </dd>
                </template>
            </dl>
        </section>

        <section v-if="Number.parseFloat(creditNote.returned_quantity) > 0" class="stock-summary">
            <strong>Physical Sales Return</strong>
            <p>
                Returned quantity: {{ formatQuantity(creditNote.returned_quantity) }}
                · Inventory value restored: {{ formatAmount(creditNote.inventory_return_value) }}
            </p>
        </section>

        <section v-if="creditNote.status === 'cancelled'" class="exception-note">
            <strong>CANCELLED</strong>
            <p>{{ creditNote.cancellation_reason ?? 'No reason recorded.' }}</p>
        </section>

        <section v-if="creditNote.status === 'reversed'" class="exception-note">
            <strong>REVERSED</strong>
            <p>Posting date: {{ formatDate(creditNote.reversal_posting_date) }}</p>
            <p>{{ creditNote.reversal_reason ?? 'No reason recorded.' }}</p>
        </section>

        <footer>
            <div>
                <span>Prepared By</span>
                <strong>{{ creditNote.created_by?.name ?? '—' }}</strong>
            </div>
            <div>
                <span>Approved By</span>
                <strong>{{ creditNote.approved_by?.name ?? '—' }}</strong>
            </div>
            <div>
                <span>Customer Acknowledgement</span>
                <strong>________________________</strong>
            </div>
        </footer>
    </div>
</template>

<style scoped>
:global(body) {
    margin: 0;
    background: #f3f4f6;
    color: #111827;
    font-family: Arial, Helvetica, sans-serif;
}

.print-page {
    width: 210mm;
    min-height: 297mm;
    margin: 18px auto;
    padding: 14mm;
    background: white;
    box-sizing: border-box;
}

.screen-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 16px;
}

.screen-actions button {
    border: 0;
    border-radius: 8px;
    background: #465fff;
    color: white;
    padding: 10px 16px;
    cursor: pointer;
}

.header {
    display: flex;
    justify-content: space-between;
    gap: 30px;
    border-bottom: 2px solid #111827;
    padding-bottom: 16px;
}

.header h1,
.header h2,
.header p {
    margin: 0 0 6px;
}

.document-title {
    text-align: right;
}

.status {
    display: inline-block;
    margin-top: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e5e7eb;
    font-size: 12px;
    text-transform: uppercase;
}

.status.posted {
    background: #dcfce7;
    color: #166534;
}

.status.approved {
    background: #dbeafe;
    color: #1d4ed8;
}

.status.submitted {
    background: #fef3c7;
    color: #92400e;
}

.status.reversed,
.status.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin: 24px 0;
}

.meta-grid h3,
.notes-column h3 {
    margin: 0 0 8px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #4b5563;
}

.meta-grid p,
.notes-column p {
    margin: 4px 0;
    font-size: 12px;
    line-height: 1.5;
}

.meta-grid dl {
    display: grid;
    grid-template-columns: 115px 1fr;
    gap: 6px 12px;
    margin: 0;
    font-size: 12px;
}

.meta-grid dt {
    color: #6b7280;
}

.meta-grid dd {
    margin: 0;
    font-weight: 600;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

th,
td {
    border: 1px solid #d1d5db;
    padding: 7px;
    vertical-align: top;
}

th {
    background: #f3f4f6;
    text-align: left;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

td p {
    margin: 4px 0 0;
    color: #4b5563;
}

td small {
    display: block;
    color: #6b7280;
}

.right {
    text-align: right;
}

.capitalize {
    text-transform: capitalize;
}

.summary-wrap {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 280px;
    gap: 24px;
    margin-top: 22px;
}

.notes-column > div + div {
    margin-top: 18px;
}

.summary {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px 16px;
    margin: 0;
    font-size: 12px;
}

.summary dt {
    color: #4b5563;
}

.summary dd {
    margin: 0;
    text-align: right;
    font-weight: 600;
}

.summary .total-label,
.summary .total-value,
.summary .balance-label,
.summary .balance-value {
    border-top: 1px solid #111827;
    padding-top: 9px;
    font-size: 14px;
    color: #111827;
}

.stock-summary {
    margin-top: 22px;
    border: 1px solid #86efac;
    background: #f0fdf4;
    padding: 12px;
    color: #166534;
}

.stock-summary p,
.exception-note p {
    margin: 5px 0 0;
    font-size: 12px;
}

.exception-note {
    margin-top: 22px;
    border: 2px solid #b91c1c;
    padding: 12px;
    color: #991b1b;
    text-align: center;
}

.pre-line {
    white-space: pre-line;
}

footer {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 55px;
}

footer div {
    border-top: 1px solid #111827;
    padding-top: 8px;
    text-align: center;
}

footer span,
footer strong {
    display: block;
    font-size: 12px;
}

footer span {
    margin-bottom: 5px;
    color: #6b7280;
}

@media print {
    @page {
        size: A4;
        margin: 0;
    }

    :global(body) {
        background: white;
    }

    .print-page {
        margin: 0;
        box-shadow: none;
    }

    .screen-actions {
        display: none;
    }
}
</style>
