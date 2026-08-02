<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import type {
    SupplierFormData,
    SupplierOption,
    SupplierRecord,
    SupplierStatus,
    SupplierType,
} from '@/Types/supplier';

const props = defineProps<{
    mode: 'create' | 'edit';
    supplier?: SupplierRecord;
    supplierTypeOptions:
        SupplierOption<SupplierType>[];
    statusOptions:
        SupplierOption<SupplierStatus>[];
}>();

const form = useForm<SupplierFormData>({
    name: props.supplier?.name ?? '',
    code: props.supplier?.code ?? '',
    supplier_type:
        props.supplier?.supplier_type
        ?? 'company',
    contact_person:
        props.supplier?.contact_person ?? '',
    email: props.supplier?.email ?? '',
    phone: props.supplier?.phone ?? '',
    alternate_phone:
        props.supplier?.alternate_phone ?? '',
    tax_number:
        props.supplier?.tax_number ?? '',
    registration_number:
        props.supplier?.registration_number ?? '',
    address_line_1:
        props.supplier?.address_line_1 ?? '',
    address_line_2:
        props.supplier?.address_line_2 ?? '',
    city: props.supplier?.city ?? '',
    state: props.supplier?.state ?? '',
    postal_code:
        props.supplier?.postal_code ?? '',
    country_code:
        props.supplier?.country_code ?? '',
    payment_terms_days:
        props.supplier?.payment_terms_days ?? 0,
    notes: props.supplier?.notes ?? '',
    status: props.supplier?.status ?? 'active',
});

const inputClass =
    'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

const textareaClass =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

const normalizeUppercase = (
    value: string,
): string => value.trim().toUpperCase();

const submit = (): void => {
    form.name = form.name.trim();
    form.code = normalizeUppercase(form.code);
    form.contact_person =
        form.contact_person.trim();
    form.email = form.email
        .trim()
        .toLowerCase();
    form.phone = form.phone.trim();
    form.alternate_phone =
        form.alternate_phone.trim();
    form.tax_number =
        normalizeUppercase(form.tax_number);
    form.registration_number =
        normalizeUppercase(
            form.registration_number,
        );
    form.address_line_1 =
        form.address_line_1.trim();
    form.address_line_2 =
        form.address_line_2.trim();
    form.city = form.city.trim();
    form.state = form.state.trim();
    form.postal_code =
        form.postal_code.trim();
    form.country_code =
        normalizeUppercase(
            form.country_code,
        );
    form.notes = form.notes.trim();

    if (
        props.mode === 'edit'
        && props.supplier !== undefined
    ) {
        form.put(
            `/erp/suppliers/${props.supplier.id}`,
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        '/erp/suppliers',
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
                Supplier information
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Configure supplier identity, contact,
                statutory, address, and payment details.
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
                        Supplier codes and statutory identifiers
                        remain reserved after deletion.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="supplier-name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Supplier name
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="supplier-name"
                            v-model="form.name"
                            type="text"
                            maxlength="160"
                            placeholder="ABC Trading Limited"
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
                            for="supplier-code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Supplier code
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="supplier-code"
                            v-model="form.code"
                            type="text"
                            maxlength="60"
                            placeholder="SUP-0001"
                            :class="[
                                inputClass,
                                'uppercase',
                                form.errors.code
                                    ? 'border-error-500'
                                    : '',
                            ]"
                            @blur="
                                form.code = normalizeUppercase(
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
                            for="supplier-type"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Supplier type
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="supplier-type"
                            v-model="form.supplier_type"
                            :class="[
                                inputClass,
                                form.errors.supplier_type
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >
                            <option
                                v-for="option in supplierTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.supplier_type"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.supplier_type }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="supplier-status"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Status
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="supplier-status"
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
                            for="supplier-contact-person"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Contact person
                        </label>

                        <input
                            id="supplier-contact-person"
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
                            for="supplier-email"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Email
                        </label>

                        <input
                            id="supplier-email"
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
                            for="supplier-phone"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Phone
                        </label>

                        <input
                            id="supplier-phone"
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
                            for="supplier-alternate-phone"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Alternate phone
                        </label>

                        <input
                            id="supplier-alternate-phone"
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
                            for="supplier-tax-number"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Tax number
                        </label>

                        <input
                            id="supplier-tax-number"
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
                            for="supplier-registration-number"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Registration number
                        </label>

                        <input
                            id="supplier-registration-number"
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
                            for="supplier-payment-terms"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Payment terms
                            <span class="text-error-500">*</span>
                        </label>

                        <div class="relative">
                            <input
                                id="supplier-payment-terms"
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
                </div>
            </section>

            <section
                class="space-y-5 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <div>
                    <h3
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Address
                    </h3>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label
                            for="supplier-address-1"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Address line 1
                        </label>

                        <input
                            id="supplier-address-1"
                            v-model="form.address_line_1"
                            type="text"
                            maxlength="255"
                            placeholder="Street, building, or area"
                            :class="[
                                inputClass,
                                form.errors.address_line_1
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.address_line_1"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.address_line_1 }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label
                            for="supplier-address-2"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Address line 2
                        </label>

                        <input
                            id="supplier-address-2"
                            v-model="form.address_line_2"
                            type="text"
                            maxlength="255"
                            placeholder="Additional address details"
                            :class="[
                                inputClass,
                                form.errors.address_line_2
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.address_line_2"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.address_line_2 }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="supplier-city"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            City
                        </label>

                        <input
                            id="supplier-city"
                            v-model="form.city"
                            type="text"
                            maxlength="100"
                            placeholder="Dhaka"
                            :class="[
                                inputClass,
                                form.errors.city
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.city"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.city }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="supplier-state"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            State or region
                        </label>

                        <input
                            id="supplier-state"
                            v-model="form.state"
                            type="text"
                            maxlength="100"
                            placeholder="Dhaka Division"
                            :class="[
                                inputClass,
                                form.errors.state
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.state"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.state }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="supplier-postal-code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Postal code
                        </label>

                        <input
                            id="supplier-postal-code"
                            v-model="form.postal_code"
                            type="text"
                            maxlength="30"
                            placeholder="1205"
                            :class="[
                                inputClass,
                                form.errors.postal_code
                                    ? 'border-error-500'
                                    : '',
                            ]"
                        >

                        <p
                            v-if="form.errors.postal_code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.postal_code }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="supplier-country-code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Country code
                        </label>

                        <input
                            id="supplier-country-code"
                            v-model="form.country_code"
                            type="text"
                            maxlength="2"
                            placeholder="BD"
                            :class="[
                                inputClass,
                                'uppercase',
                                form.errors.country_code
                                    ? 'border-error-500'
                                    : '',
                            ]"
                            @blur="
                                form.country_code =
                                    normalizeUppercase(
                                        form.country_code,
                                    )
                            "
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use a two-letter ISO country code.
                        </p>

                        <p
                            v-if="form.errors.country_code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.country_code }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="space-y-3 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <label
                    for="supplier-notes"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Notes
                </label>

                <textarea
                    id="supplier-notes"
                    v-model="form.notes"
                    rows="6"
                    maxlength="4000"
                    placeholder="Optional internal supplier notes"
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
                href="/erp/suppliers"
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
                            ? 'Update supplier'
                            : 'Create supplier'
                }}
            </button>
        </div>
    </form>
</template>