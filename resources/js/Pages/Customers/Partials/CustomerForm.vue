<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    ref,
    watch,
} from 'vue';
import type { Ref } from 'vue';
import type {
    CustomerFormData,
    CustomerOption,
    CustomerRecord,
    CustomerStatus,
    CustomerType,
} from '@/Types/customer';

const props = defineProps<{
    mode: 'create' | 'edit';
    customer?: CustomerRecord;
    customerTypeOptions:
        CustomerOption<CustomerType>[];
    statusOptions:
        CustomerOption<CustomerStatus>[];
    canManageCreditLimit: boolean;
}>();

const form = useForm<CustomerFormData>({
    name: props.customer?.name ?? '',
    code: props.customer?.code ?? '',

    customer_type:
        props.customer?.customer_type
        ?? 'company',

    contact_person:
        props.customer?.contact_person ?? '',

    email:
        props.customer?.email ?? '',

    phone:
        props.customer?.phone ?? '',

    alternate_phone:
        props.customer?.alternate_phone ?? '',

    tax_number:
        props.customer?.tax_number ?? '',

    registration_number:
        props.customer?.registration_number ?? '',

    billing_address_line_1:
        props.customer?.billing_address_line_1
        ?? '',

    billing_address_line_2:
        props.customer?.billing_address_line_2
        ?? '',

    billing_city:
        props.customer?.billing_city ?? '',

    billing_state:
        props.customer?.billing_state ?? '',

    billing_postal_code:
        props.customer?.billing_postal_code ?? '',

    billing_country_code:
        props.customer?.billing_country_code ?? '',

    shipping_address_line_1:
        props.customer?.shipping_address_line_1
        ?? '',

    shipping_address_line_2:
        props.customer?.shipping_address_line_2
        ?? '',

    shipping_city:
        props.customer?.shipping_city ?? '',

    shipping_state:
        props.customer?.shipping_state ?? '',

    shipping_postal_code:
        props.customer?.shipping_postal_code ?? '',

    shipping_country_code:
        props.customer?.shipping_country_code ?? '',

    payment_terms_days:
        props.customer?.payment_terms_days ?? 0,

    credit_limit:
        props.customer?.credit_limit
        ?? '0.000000',

    notes:
        props.customer?.notes ?? '',

    status:
        props.customer?.status ?? 'active',
});

const useBillingAsShipping: Ref<boolean> =
    ref(false);

const inputClass =
    'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

const textareaClass =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

const normalizeUppercase = (
    value: string,
): string => value.trim().toUpperCase();

const copyBillingAddressToShipping = (): void => {
    form.shipping_address_line_1 =
        form.billing_address_line_1;

    form.shipping_address_line_2 =
        form.billing_address_line_2;

    form.shipping_city =
        form.billing_city;

    form.shipping_state =
        form.billing_state;

    form.shipping_postal_code =
        form.billing_postal_code;

    form.shipping_country_code =
        form.billing_country_code;
};

watch(
    useBillingAsShipping,
    (enabled: boolean): void => {
        if (enabled) {
            copyBillingAddressToShipping();
        }
    },
);

watch(
    (): [
        string,
        string,
        string,
        string,
        string,
        string,
    ] => [
        form.billing_address_line_1,
        form.billing_address_line_2,
        form.billing_city,
        form.billing_state,
        form.billing_postal_code,
        form.billing_country_code,
    ],
    (): void => {
        if (useBillingAsShipping.value) {
            copyBillingAddressToShipping();
        }
    },
);

const submit = (): void => {
    form.name = form.name.trim();

    form.code = normalizeUppercase(
        form.code,
    );

    form.contact_person =
        form.contact_person.trim();

    form.email = form.email
        .trim()
        .toLowerCase();

    form.phone = form.phone.trim();

    form.alternate_phone =
        form.alternate_phone.trim();

    form.tax_number =
        normalizeUppercase(
            form.tax_number,
        );

    form.registration_number =
        normalizeUppercase(
            form.registration_number,
        );

    form.billing_address_line_1 =
        form.billing_address_line_1.trim();

    form.billing_address_line_2 =
        form.billing_address_line_2.trim();

    form.billing_city =
        form.billing_city.trim();

    form.billing_state =
        form.billing_state.trim();

    form.billing_postal_code =
        form.billing_postal_code.trim();

    form.billing_country_code =
        normalizeUppercase(
            form.billing_country_code,
        );

    form.shipping_address_line_1 =
        form.shipping_address_line_1.trim();

    form.shipping_address_line_2 =
        form.shipping_address_line_2.trim();

    form.shipping_city =
        form.shipping_city.trim();

    form.shipping_state =
        form.shipping_state.trim();

    form.shipping_postal_code =
        form.shipping_postal_code.trim();

    form.shipping_country_code =
        normalizeUppercase(
            form.shipping_country_code,
        );

    form.credit_limit =
        form.credit_limit.trim();

    form.notes = form.notes.trim();

    if (
        props.mode === 'edit'
        && props.customer !== undefined
    ) {
        form.put(
            `/erp/customers/${props.customer.id}`,
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        '/erp/customers',
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <form
        class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        @submit.prevent="submit"
    >
        <div
            class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
        >
            <h2
                class="text-lg font-semibold text-gray-800 dark:text-white/90"
            >
                Customer information
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Configure customer identity, contact,
                address, payment, and credit details.
            </p>
        </div>

        <div class="space-y-8 p-5 sm:p-6">
            <section class="space-y-5">
                <div>
                    <h3
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Identity
                    </h3>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Customer codes and statutory identifiers
                        remain reserved after deletion.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="customer-name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Customer name
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="customer-name"
                            v-model="form.name"
                            type="text"
                            maxlength="160"
                            placeholder="XYZ Retail Limited"
                            :class="[
                                inputClass,
                                form.errors.name
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.name"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Customer code
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="customer-code"
                            v-model="form.code"
                            type="text"
                            maxlength="60"
                            placeholder="CUS-0001"
                            :class="[
                                inputClass,
                                'uppercase',
                                form.errors.code
                                    ? 'border-error-500'
                                    : '',
                            ]"
                            @blur="
                                form.code =
                                    normalizeUppercase(
                                        form.code,
                                    )
                            "
                        >

                        <p
                            v-if="form.errors.code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-type"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Customer type
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="customer-type"
                            v-model="form.customer_type"
                            :class="[
                                inputClass,
                                form.errors.customer_type
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >
                            <option
                                v-for="option in customerTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.customer_type"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.customer_type }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-status"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Status
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="customer-status"
                            v-model="form.status"
                            :class="[
                                inputClass,
                                form.errors.status
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.status"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.status }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="space-y-5 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <div>
                    <h3
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Contact details
                    </h3>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="customer-contact-person"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Contact person
                        </label>

                        <input
                            id="customer-contact-person"
                            v-model="form.contact_person"
                            type="text"
                            maxlength="120"
                            placeholder="Contact person's name"
                            :class="[
                                inputClass,
                                form.errors.contact_person
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.contact_person"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.contact_person }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-email"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Email
                        </label>

                        <input
                            id="customer-email"
                            v-model="form.email"
                            type="email"
                            maxlength="255"
                            placeholder="accounts@example.com"
                            :class="[
                                inputClass,
                                form.errors.email
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.email"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-phone"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Phone
                        </label>

                        <input
                            id="customer-phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="40"
                            placeholder="+880 1XXXXXXXXX"
                            :class="[
                                inputClass,
                                form.errors.phone
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.phone"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-alternate-phone"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Alternate phone
                        </label>

                        <input
                            id="customer-alternate-phone"
                            v-model="form.alternate_phone"
                            type="tel"
                            maxlength="40"
                            placeholder="Optional alternate number"
                            :class="[
                                inputClass,
                                form.errors.alternate_phone
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.alternate_phone"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.alternate_phone }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="space-y-5 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <div>
                    <h3
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Statutory and commercial details
                    </h3>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="customer-tax-number"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Tax number
                        </label>

                        <input
                            id="customer-tax-number"
                            v-model="form.tax_number"
                            type="text"
                            maxlength="100"
                            placeholder="TIN, VAT, or tax ID"
                            :class="[
                                inputClass,
                                'uppercase',
                                form.errors.tax_number
                                    ? 'border-error-500'
                                    : '',
                            ]"
                            @blur="
                                form.tax_number =
                                    normalizeUppercase(
                                        form.tax_number,
                                    )
                            "
                        >

                        <p
                            v-if="form.errors.tax_number"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.tax_number }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-registration-number"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Registration number
                        </label>

                        <input
                            id="customer-registration-number"
                            v-model="form.registration_number"
                            type="text"
                            maxlength="100"
                            placeholder="Trade or company registration"
                            :class="[
                                inputClass,
                                'uppercase',
                                form.errors.registration_number
                                    ? 'border-error-500'
                                    : '',
                            ]"
                            @blur="
                                form.registration_number =
                                    normalizeUppercase(
                                        form.registration_number,
                                    )
                            "
                        >

                        <p
                            v-if="form.errors.registration_number"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.registration_number }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-payment-terms"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Payment terms
                            <span class="text-error-500">*</span>
                        </label>

                        <div class="relative">
                            <input
                                id="customer-payment-terms"
                                v-model.number="form.payment_terms_days"
                                type="number"
                                min="0"
                                max="3650"
                                step="1"
                                :class="[
                                    inputClass,
                                    'pr-16',
                                    form.errors.payment_terms_days
                                        ? 'border-error-500'
                                        : '',
                                ]"
                            >

                            <span
                                class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                days
                            </span>
                        </div>

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use 0 for immediate payment.
                        </p>

                        <p
                            v-if="form.errors.payment_terms_days"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.payment_terms_days }}
                        </p>
                    </div>

                    <div v-if="canManageCreditLimit">
                        <label
                            for="customer-credit-limit"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Credit limit
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="customer-credit-limit"
                            v-model="form.credit_limit"
                            type="number"
                            min="0"
                            max="99999999999999.999999"
                            step="0.000001"
                            :class="[
                                inputClass,
                                form.errors.credit_limit
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use 0 when no customer credit is allowed.
                            Customer balances will be calculated from
                            receivable-ledger entries.
                        </p>

                        <p
                            v-if="form.errors.credit_limit"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.credit_limit }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="space-y-5 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <div>
                    <h3
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Billing address
                    </h3>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label
                            for="customer-billing-address-1"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Address line 1
                        </label>

                        <input
                            id="customer-billing-address-1"
                            v-model="form.billing_address_line_1"
                            type="text"
                            maxlength="255"
                            placeholder="Street, building, or area"
                            :class="[
                                inputClass,
                                form.errors.billing_address_line_1
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.billing_address_line_1"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors.billing_address_line_1
                            }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label
                            for="customer-billing-address-2"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Address line 2
                        </label>

                        <input
                            id="customer-billing-address-2"
                            v-model="form.billing_address_line_2"
                            type="text"
                            maxlength="255"
                            placeholder="Additional address details"
                            :class="[
                                inputClass,
                                form.errors.billing_address_line_2
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.billing_address_line_2"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors.billing_address_line_2
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-billing-city"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            City
                        </label>

                        <input
                            id="customer-billing-city"
                            v-model="form.billing_city"
                            type="text"
                            maxlength="100"
                            placeholder="Dhaka"
                            :class="[
                                inputClass,
                                form.errors.billing_city
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.billing_city"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.billing_city }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-billing-state"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            State or region
                        </label>

                        <input
                            id="customer-billing-state"
                            v-model="form.billing_state"
                            type="text"
                            maxlength="100"
                            placeholder="Dhaka Division"
                            :class="[
                                inputClass,
                                form.errors.billing_state
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.billing_state"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.billing_state }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-billing-postal-code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Postal code
                        </label>

                        <input
                            id="customer-billing-postal-code"
                            v-model="form.billing_postal_code"
                            type="text"
                            maxlength="30"
                            placeholder="1205"
                            :class="[
                                inputClass,
                                form.errors.billing_postal_code
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.billing_postal_code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors.billing_postal_code
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-billing-country"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Country code
                        </label>

                        <input
                            id="customer-billing-country"
                            v-model="form.billing_country_code"
                            type="text"
                            maxlength="2"
                            placeholder="BD"
                            :class="[
                                inputClass,
                                'uppercase',
                                form.errors.billing_country_code
                                    ? 'border-error-500'
                                    : '',
                            ]"
                            @blur="
                                form.billing_country_code =
                                    normalizeUppercase(
                                        form.billing_country_code,
                                    )
                            "
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use a two-letter ISO country code.
                        </p>

                        <p
                            v-if="form.errors.billing_country_code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors.billing_country_code
                            }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="space-y-5 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h3
                            class="text-base font-semibold text-gray-800 dark:text-white/90"
                        >
                            Shipping address
                        </h3>

                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            Used as the default delivery address
                            on future sales documents.
                        </p>
                    </div>

                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        <input
                            v-model="useBillingAsShipping"
                            type="checkbox"
                            class="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                        >

                        Same as billing address
                    </label>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label
                            for="customer-shipping-address-1"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Address line 1
                        </label>

                        <input
                            id="customer-shipping-address-1"
                            v-model="form.shipping_address_line_1"
                            type="text"
                            maxlength="255"
                            placeholder="Street, building, or area"
                            :disabled="useBillingAsShipping"
                            :class="[
                                inputClass,
                                useBillingAsShipping
                                    ? 'cursor-not-allowed bg-gray-50 opacity-70 dark:bg-white/[0.02]'
                                    : '',
                                form.errors.shipping_address_line_1
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.shipping_address_line_1"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors.shipping_address_line_1
                            }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label
                            for="customer-shipping-address-2"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Address line 2
                        </label>

                        <input
                            id="customer-shipping-address-2"
                            v-model="form.shipping_address_line_2"
                            type="text"
                            maxlength="255"
                            placeholder="Additional address details"
                            :disabled="useBillingAsShipping"
                            :class="[
                                inputClass,
                                useBillingAsShipping
                                    ? 'cursor-not-allowed bg-gray-50 opacity-70 dark:bg-white/[0.02]'
                                    : '',
                                form.errors.shipping_address_line_2
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.shipping_address_line_2"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors.shipping_address_line_2
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-shipping-city"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            City
                        </label>

                        <input
                            id="customer-shipping-city"
                            v-model="form.shipping_city"
                            type="text"
                            maxlength="100"
                            placeholder="Dhaka"
                            :disabled="useBillingAsShipping"
                            :class="[
                                inputClass,
                                useBillingAsShipping
                                    ? 'cursor-not-allowed bg-gray-50 opacity-70 dark:bg-white/[0.02]'
                                    : '',
                                form.errors.shipping_city
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.shipping_city"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.shipping_city }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-shipping-state"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            State or region
                        </label>

                        <input
                            id="customer-shipping-state"
                            v-model="form.shipping_state"
                            type="text"
                            maxlength="100"
                            placeholder="Dhaka Division"
                            :disabled="useBillingAsShipping"
                            :class="[
                                inputClass,
                                useBillingAsShipping
                                    ? 'cursor-not-allowed bg-gray-50 opacity-70 dark:bg-white/[0.02]'
                                    : '',
                                form.errors.shipping_state
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.shipping_state"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.shipping_state }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-shipping-postal-code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Postal code
                        </label>

                        <input
                            id="customer-shipping-postal-code"
                            v-model="form.shipping_postal_code"
                            type="text"
                            maxlength="30"
                            placeholder="1205"
                            :disabled="useBillingAsShipping"
                            :class="[
                                inputClass,
                                useBillingAsShipping
                                    ? 'cursor-not-allowed bg-gray-50 opacity-70 dark:bg-white/[0.02]'
                                    : '',
                                form.errors.shipping_postal_code
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.shipping_postal_code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors.shipping_postal_code
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="customer-shipping-country"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Country code
                        </label>

                        <input
                            id="customer-shipping-country"
                            v-model="form.shipping_country_code"
                            type="text"
                            maxlength="2"
                            placeholder="BD"
                            :disabled="useBillingAsShipping"
                            :class="[
                                inputClass,
                                'uppercase',
                                useBillingAsShipping
                                    ? 'cursor-not-allowed bg-gray-50 opacity-70 dark:bg-white/[0.02]'
                                    : '',
                                form.errors.shipping_country_code
                                    ? 'border-error-500'
                                    : '',
                            ]"
                            @blur="
                                form.shipping_country_code =
                                    normalizeUppercase(
                                        form.shipping_country_code,
                                    )
                            "
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use a two-letter ISO country code.
                        </p>

                        <p
                            v-if="form.errors.shipping_country_code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors.shipping_country_code
                            }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="space-y-3 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <label
                    for="customer-notes"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Notes
                </label>

                <textarea
                    id="customer-notes"
                    v-model="form.notes"
                    rows="6"
                    maxlength="4000"
                    placeholder="Optional internal customer notes"
                    :class="[
                        textareaClass,
                        form.errors.notes
                            ? 'border-error-500'
                            : '',
                    ]"
                />

                <div
                    class="flex items-center justify-between gap-4"
                >
                    <p
                        v-if="form.errors.notes"
                        class="text-sm text-error-500"
                    >
                        {{ form.errors.notes }}
                    </p>

                    <span
                        class="ml-auto text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ form.notes.length }}/4000
                    </span>
                </div>
            </section>
        </div>

        <div
            class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
        >
            <Link
                href="/erp/customers"
                class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
            >
                Cancel
            </Link>

            <button
                type="submit"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : mode === 'edit'
                            ? 'Update customer'
                            : 'Create customer'
                }}
            </button>
        </div>
    </form>
</template>