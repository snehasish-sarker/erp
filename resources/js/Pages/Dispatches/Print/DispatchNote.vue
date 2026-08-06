<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    CustomerDispatchPrintProps,
} from '@/Types/dispatch';

const props =
    defineProps<CustomerDispatchPrintProps>();

const title = computed(
    (): string =>
        props.dispatch.dispatch_number
        ?? `Dispatch Draft #${props.dispatch.id}`,
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

const formatQuantity = (
    value: string,
): string => {
    const number =
        Number.parseFloat(value);

    if (!Number.isFinite(number)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(number);
};

const printPage = (): void => {
    window.print();
};
</script>

<template>
    <Head
        :title="
            `Delivery Note ${title}`
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
                <h2>DELIVERY NOTE</h2>

                <p>{{ title }}</p>

                <span
                    :class="[
                        'status',
                        dispatch.status,
                    ]"
                >
                    {{
                        dispatch.status_label
                    }}
                </span>
            </div>
        </header>

        <section class="meta-grid">
            <div>
                <h3>Deliver To</h3>

                <strong>
                    {{ dispatch.customer_name }}
                </strong>

                <p>
                    {{ dispatch.customer_code }}
                </p>

                <p
                    v-if="
                        dispatch.customer_contact_person
                    "
                >
                    {{
                        dispatch.customer_contact_person
                    }}
                </p>

                <p
                    v-if="
                        dispatch.customer_phone
                    "
                >
                    {{
                        dispatch.customer_phone
                    }}
                </p>

                <p class="pre-line">
                    {{
                        dispatch.shipping_address
                        ?? '—'
                    }}
                </p>
            </div>

            <div>
                <h3>Dispatch Information</h3>

                <dl>
                    <dt>Dispatch Date</dt>

                    <dd>
                        {{
                            formatDate(
                                dispatch.dispatch_date,
                            )
                        }}
                    </dd>

                    <dt>Sales Order</dt>

                    <dd>
                        {{
                            dispatch.sales_order_number
                        }}
                    </dd>

                    <dt>Branch</dt>

                    <dd>
                        {{
                            dispatch.branch
                                ?.name
                            ?? '—'
                        }}
                    </dd>

                    <dt>Warehouse</dt>

                    <dd>
                        {{
                            dispatch.warehouse
                                ?.name
                            ?? '—'
                        }}
                    </dd>

                    <dt>Carrier</dt>

                    <dd>
                        {{
                            dispatch.carrier_name
                            ?? '—'
                        }}
                    </dd>

                    <dt>Vehicle</dt>

                    <dd>
                        {{
                            dispatch.vehicle_number
                            ?? '—'
                        }}
                    </dd>

                    <dt>Tracking</dt>

                    <dd>
                        {{
                            dispatch.tracking_number
                            ?? '—'
                        }}
                    </dd>
                </dl>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Unit</th>
                    <th class="right">
                        Quantity
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="line in dispatch.lines"
                    :key="line.id"
                >
                    <td>
                        {{ line.line_number }}
                    </td>

                    <td>
                        <strong>
                            {{ line.product_name }}
                        </strong>

                        <p
                            v-if="line.description"
                        >
                            {{ line.description }}
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
                                line.dispatched_quantity,
                            )
                        }}
                    </td>
                </tr>
            </tbody>
        </table>

        <section
            v-if="
                dispatch.delivery_instructions
            "
            class="notes"
        >
            <h3>Delivery Instructions</h3>

            <p class="pre-line">
                {{
                    dispatch.delivery_instructions
                }}
            </p>
        </section>

        <section
            v-if="dispatch.notes"
            class="notes internal-note"
        >
            <h3>Internal Note</h3>

            <p class="pre-line">
                {{ dispatch.notes }}
            </p>
        </section>

        <footer>
            <div>
                <span>Prepared By</span>

                <strong>
                    {{
                        dispatch.created_by
                            ?.name
                        ?? '—'
                    }}
                </strong>
            </div>

            <div>
                <span>Posted By</span>

                <strong>
                    {{
                        dispatch.posted_by
                            ?.name
                        ?? '—'
                    }}
                </strong>
            </div>

            <div>
                <span>Received By</span>

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
    padding: 16mm;
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
.notes h3 {
    margin: 0 0 8px;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #4b5563;
}

.meta-grid p {
    margin: 4px 0;
    font-size: 13px;
}

.meta-grid dl {
    display: grid;
    grid-template-columns: 110px 1fr;
    gap: 6px 12px;
    margin: 0;
    font-size: 13px;
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
    font-size: 13px;
}

th,
td {
    border: 1px solid #d1d5db;
    padding: 9px;
    vertical-align: top;
}

th {
    background: #f3f4f6;
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

td p {
    margin: 4px 0 0;
    color: #4b5563;
}

.right {
    text-align: right;
}

.notes {
    margin-top: 22px;
    border: 1px solid #d1d5db;
    padding: 12px;
}

.internal-note {
    border-style: dashed;
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
    color: #6b7280;
    margin-bottom: 5px;
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