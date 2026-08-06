<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    SalesInvoicePrintProps,
} from '@/Types/sales-invoice';

const props =
    defineProps<SalesInvoicePrintProps>();

const title = computed(
    (): string => {
        return props.salesInvoice
            .invoice_number
            ?? `Sales Invoice Draft #${props.salesInvoice.id}`;
    },
);

const formatDate = (
    value: string | null,
): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(
        `${value}T00:00:00`,
    );

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        },
    ).format(date);
};

const formatAmount = (
    value: string,
): string => {
    const amount =
        Number.parseFloat(value);

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

const formatQuantity = (
    value: string,
): string => {
    const quantity =
        Number.parseFloat(value);

    if (
        !Number.isFinite(quantity)
    ) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(quantity);
};

const printPage = (): void => {
    window.print();
};
</script>

<template>
    <Head
        :title="
            `Sales Invoice ${title}`
        "
    />

    <div class="print-page">
        <div class="screen-actions">
            <button
                type="button"
                @click="printPage"
            >
                Print / Save PDF
            </button>
        </div>

        <header class="header">
            <div>
                <h1>{{ company.name }}</h1>

                <p v-if="company.address">
                    {{ company.address }}
                </p>

                <p>
                    <span v-if="company.phone">
                        {{ company.phone }}
                    </span>

                    <span
                        v-if="
                            company.phone
                            && company.email
                        "
                    >
                        ·
                    </span>

                    <span v-if="company.email">
                        {{ company.email }}
                    </span>
                </p>
            </div>

            <div class="document-title">
                <h2>SALES INVOICE</h2>

                <p>{{ title }}</p>

                <span
                    :class="[
                        'status',
                        salesInvoice.status,
                    ]"
                >
                    {{
                        salesInvoice.status_label
                    }}
                </span>
            </div>
        </header>

        <section class="meta-grid">
            <div>
                <h3>Bill To</h3>

                <strong>
                    {{
                        salesInvoice.customer_name
                    }}
                </strong>

                <p>
                    {{
                        salesInvoice.customer_code
                    }}
                </p>

                <p
                    v-if="
                        salesInvoice.customer_contact_person
                    "
                >
                    {{
                        salesInvoice.customer_contact_person
                    }}
                </p>

                <p
                    v-if="
                        salesInvoice.customer_phone
                    "
                >
                    {{
                        salesInvoice.customer_phone
                    }}
                </p>

                <p
                    v-if="
                        salesInvoice.customer_email
                    "
                >
                    {{
                        salesInvoice.customer_email
                    }}
                </p>

                <p class="pre-line">
                    {{
                        salesInvoice.billing_address
                        ?? '—'
                    }}
                </p>

                <p
                    v-if="
                        salesInvoice.customer_tax_number
                    "
                >
                    Tax Number:
                    {{
                        salesInvoice.customer_tax_number
                    }}
                </p>
            </div>

            <div>
                <h3>Invoice Information</h3>

                <dl>
                    <dt>Invoice Date</dt>

                    <dd>
                        {{
                            formatDate(
                                salesInvoice.invoice_date,
                            )
                        }}
                    </dd>

                    <dt>Posting Date</dt>

                    <dd>
                        {{
                            formatDate(
                                salesInvoice.posting_date,
                            )
                        }}
                    </dd>

                    <dt>Due Date</dt>

                    <dd>
                        {{
                            formatDate(
                                salesInvoice.due_date,
                            )
                        }}
                    </dd>

                    <dt>Sales Order</dt>

                    <dd>
                        {{
                            salesInvoice.sales_order_number
                        }}
                    </dd>

                    <dt>Branch</dt>

                    <dd>
                        {{
                            salesInvoice.branch
                                ?.name
                            ?? '—'
                        }}
                    </dd>

                    <dt>Currency</dt>

                    <dd>
                        {{
                            salesInvoice.currency_code
                        }}
                    </dd>

                    <dt>Payment Terms</dt>

                    <dd>
                        {{
                            salesInvoice.payment_terms_days
                        }}
                        days
                    </dd>
                </dl>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>#</th>

                    <th>
                        Product / Description
                    </th>

                    <th>SKU</th>

                    <th>Unit</th>

                    <th class="right">
                        Quantity
                    </th>

                    <th class="right">
                        Unit Price
                    </th>

                    <th class="right">
                        Discount
                    </th>

                    <th class="right">
                        Tax
                    </th>

                    <th class="right">
                        Amount
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="line in salesInvoice.lines"
                    :key="line.id"
                >
                    <td>
                        {{ line.line_number }}
                    </td>

                    <td>
                        <strong>
                            {{
                                line.product_name
                            }}
                        </strong>

                        <p
                            v-if="
                                line.description
                            "
                        >
                            {{
                                line.description
                            }}
                        </p>
                    </td>

                    <td>
                        {{ line.product_sku }}
                    </td>

                    <td>
                        {{ line.unit_code }}
                    </td>

                    <td class="right">
                        {{
                            formatQuantity(
                                line.invoiced_quantity,
                            )
                        }}
                    </td>

                    <td class="right">
                        {{
                            formatAmount(
                                line.unit_price,
                            )
                        }}
                    </td>

                    <td class="right">
                        {{
                            formatAmount(
                                line.discount_amount,
                            )
                        }}
                    </td>

                    <td class="right">
                        {{
                            formatAmount(
                                line.tax_amount,
                            )
                        }}

                        <small>
                            {{
                                formatQuantity(
                                    line.tax_rate,
                                )
                            }}%
                        </small>
                    </td>

                    <td class="right">
                        {{
                            formatAmount(
                                line.line_total,
                            )
                        }}
                    </td>
                </tr>
            </tbody>
        </table>

        <section class="summary-wrap">
            <div class="notes-column">
                <div
                    v-if="
                        salesInvoice.shipping_address
                    "
                >
                    <h3>Ship To</h3>

                    <p class="pre-line">
                        {{
                            salesInvoice.shipping_address
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        salesInvoice.notes
                    "
                >
                    <h3>Notes</h3>

                    <p class="pre-line">
                        {{ salesInvoice.notes }}
                    </p>
                </div>
            </div>

            <dl class="summary">
                <dt>Subtotal</dt>

                <dd>
                    {{
                        formatAmount(
                            salesInvoice.subtotal,
                        )
                    }}
                </dd>

                <dt>Discount</dt>

                <dd>
                    -{{
                        formatAmount(
                            salesInvoice.discount_amount,
                        )
                    }}
                </dd>

                <dt>Output Tax</dt>

                <dd>
                    {{
                        formatAmount(
                            salesInvoice.tax_amount,
                        )
                    }}
                </dd>

                <dt>Shipping</dt>

                <dd>
                    {{
                        formatAmount(
                            salesInvoice.shipping_amount,
                        )
                    }}
                </dd>

                <dt>Other Charges</dt>

                <dd>
                    {{
                        formatAmount(
                            salesInvoice.other_charges,
                        )
                    }}
                </dd>

                <dt class="total-label">
                    Invoice Total
                </dt>

                <dd class="total-value">
                    {{
                        salesInvoice.currency_code
                    }}
                    {{
                        formatAmount(
                            salesInvoice.total_amount,
                        )
                    }}
                </dd>

                <template
                    v-if="
                        salesInvoice.open_item
                    "
                >
                    <dt>Amount Paid</dt>

                    <dd>
                        {{
                            formatAmount(
                                salesInvoice
                                    .open_item
                                    .allocated_amount,
                            )
                        }}
                    </dd>

                    <dt class="balance-label">
                        Balance Due
                    </dt>

                    <dd class="balance-value">
                        {{
                            salesInvoice.currency_code
                        }}
                        {{
                            formatAmount(
                                salesInvoice
                                    .open_item
                                    .outstanding_amount,
                            )
                        }}
                    </dd>
                </template>
            </dl>
        </section>

        <section
            v-if="
                salesInvoice.status
                    === 'reversed'
            "
            class="reversal-note"
        >
            <strong>REVERSED</strong>

            <p>
                Posting date:
                {{
                    formatDate(
                        salesInvoice.reversal_posting_date,
                    )
                }}
            </p>

            <p>
                {{
                    salesInvoice.reversal_reason
                    ?? 'No reason recorded.'
                }}
            </p>
        </section>

        <footer>
            <div>
                <span>Prepared By</span>

                <strong>
                    {{
                        salesInvoice.created_by
                            ?.name
                        ?? '—'
                    }}
                </strong>
            </div>

            <div>
                <span>Posted By</span>

                <strong>
                    {{
                        salesInvoice.posted_by
                            ?.name
                        ?? '—'
                    }}
                </strong>
            </div>

            <div>
                <span>
                    Customer Acceptance
                </span>

                <strong>
                    ________________________
                </strong>
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

.status.reversed {
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
    grid-template-columns: 110px 1fr;
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

.summary-wrap {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 270px;
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

.reversal-note {
    margin-top: 22px;
    border: 2px solid #b91c1c;
    padding: 12px;
    color: #991b1b;
    text-align: center;
}

.reversal-note p {
    margin: 5px 0 0;
    font-size: 12px;
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