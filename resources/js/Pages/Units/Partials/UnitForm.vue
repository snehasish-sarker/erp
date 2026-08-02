<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import { watch } from 'vue';
import type {
    UnitCategory,
    UnitFormData,
    UnitOption,
    UnitRecord,
    UnitStatus,
} from '@/Types/unit';

const props = defineProps<{
    mode: 'create' | 'edit';
    unit?: UnitRecord;
    categoryOptions:
        UnitOption<UnitCategory>[];
    statusOptions:
        UnitOption<UnitStatus>[];
}>();

const form = useForm<UnitFormData>({
    name: props.unit?.name ?? '',
    code: props.unit?.code ?? '',
    symbol: props.unit?.symbol ?? '',
    category: props.unit?.category ?? 'count',
    allow_decimal:
        props.unit?.allow_decimal ?? false,
    decimal_places:
        props.unit?.decimal_places ?? 0,
    status: props.unit?.status ?? 'active',
});

watch(
    (): boolean => form.allow_decimal,
    (allowDecimal: boolean): void => {
        if (!allowDecimal) {
            form.decimal_places = 0;
        }
    },
);

const submit = (): void => {
    form.name = form.name.trim();

    form.code = form.code
        .trim()
        .toUpperCase();

    form.symbol = form.symbol.trim();

    if (!form.allow_decimal) {
        form.decimal_places = 0;
    }

    if (
        props.mode === 'edit'
        && props.unit !== undefined
    ) {
        form.put(
            `/erp/units/${props.unit.id}`,
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        '/erp/units',
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
                Unit information
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Configure how this unit is displayed
                and whether fractional quantities are
                allowed.
            </p>
        </div>

        <div class="space-y-6 p-5 sm:p-6">
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label
                        for="unit-name"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Name
                        <span class="text-error-500">*</span>
                    </label>

                    <input
                        id="unit-name"
                        v-model="form.name"
                        type="text"
                        maxlength="100"
                        placeholder="Kilogram"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
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
                        for="unit-code"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Code
                        <span class="text-error-500">*</span>
                    </label>

                    <input
                        id="unit-code"
                        v-model="form.code"
                        type="text"
                        maxlength="30"
                        placeholder="KG"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        @blur="
                            form.code = form.code
                                .trim()
                                .toUpperCase()
                        "
                    >

                    <p
                        class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Codes remain reserved after deletion.
                    </p>

                    <p
                        v-if="form.errors.code"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <div>
                    <label
                        for="unit-symbol"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Symbol
                    </label>

                    <input
                        id="unit-symbol"
                        v-model="form.symbol"
                        type="text"
                        maxlength="20"
                        placeholder="kg"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >

                    <p
                        v-if="form.errors.symbol"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.symbol }}
                    </p>
                </div>

                <div>
                    <label
                        for="unit-category"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Category
                        <span class="text-error-500">*</span>
                    </label>

                    <select
                        id="unit-category"
                        v-model="form.category"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option
                            v-for="
                                option in categoryOptions
                            "
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <p
                        v-if="form.errors.category"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.category }}
                    </p>
                </div>

                <div>
                    <label
                        for="unit-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                        <span class="text-error-500">*</span>
                    </label>

                    <select
                        id="unit-status"
                        v-model="form.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option
                            v-for="
                                option in statusOptions
                            "
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

            <div
                class="rounded-xl border border-gray-200 p-4 dark:border-gray-800"
            >
                <label
                    class="flex cursor-pointer items-start gap-3"
                >
                    <input
                        v-model="form.allow_decimal"
                        type="checkbox"
                        class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                    >

                    <span>
                        <span
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Allow decimal quantities
                        </span>

                        <span
                            class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                        >
                            Enable this for units such as
                            kilograms, litres, metres, and
                            hours.
                        </span>
                    </span>
                </label>

                <p
                    v-if="form.errors.allow_decimal"
                    class="mt-2 text-sm text-error-500"
                >
                    {{ form.errors.allow_decimal }}
                </p>

                <div
                    v-if="form.allow_decimal"
                    class="mt-5 max-w-xs"
                >
                    <label
                        for="decimal-places"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Decimal places
                    </label>

                    <input
                        id="decimal-places"
                        v-model.number="
                            form.decimal_places
                        "
                        type="number"
                        min="0"
                        max="6"
                        step="1"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >

                    <p
                        v-if="
                            form.errors.decimal_places
                        "
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{
                            form.errors
                                .decimal_places
                        }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
        >
            <Link
                href="/erp/units"
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
                            ? 'Update unit'
                            : 'Create unit'
                }}
            </button>
        </div>
    </form>
</template>