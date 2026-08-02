<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    WarehouseBranch,
    WarehouseRecord,
    WarehouseStatus,
    WarehouseStatusOption,
    WarehouseType,
    WarehouseTypeOption,
} from '@/Types/warehouse';

interface WarehouseFormInput {
    branch_id: number | '';
    name: string;
    code: string;
    type: WarehouseType;
    status: WarehouseStatus;
    is_default: boolean;
    address: string;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    warehouse?: WarehouseRecord;
    branchOptions: WarehouseBranch[];
    typeOptions: WarehouseTypeOption[];
    statusOptions: WarehouseStatusOption[];
}>();

const { can } = useAuthorization();

const form = useForm<WarehouseFormInput>({
    branch_id: props.warehouse?.branch_id ?? '',
    name: props.warehouse?.name ?? '',
    code: props.warehouse?.code ?? '',
    type: props.warehouse?.type ?? 'general',
    status: props.warehouse?.status ?? 'active',
    is_default: props.warehouse?.is_default ?? false,
    address: props.warehouse?.address ?? '',
});

const hasBranches: ComputedRef<boolean> = computed(
    (): boolean => props.branchOptions.length > 0,
);

const title: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create Warehouse'
        : 'Edit Warehouse',
);

const description: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Add a warehouse under one of your company branches.'
        : 'Update the selected warehouse configuration.',
);

const submitLabel: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create warehouse'
        : 'Save changes',
);

const submit = (): void => {
    if (!hasBranches.value) {
        return;
    }

    form.code = form.code.trim().toUpperCase();

    if (props.mode === 'create') {
        form.post('/erp/warehouses');

        return;
    }

    if (props.warehouse === undefined) {
        return;
    }

    form.put(`/erp/warehouses/${props.warehouse.id}`);
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                {{ title }}
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ description }}
            </p>
        </div>

        <div
            v-if="!hasBranches"
            class="rounded-2xl border border-warning-200 bg-warning-25 p-5 dark:border-warning-500/20 dark:bg-warning-500/10"
        >
            <h2
                class="text-sm font-semibold text-warning-800 dark:text-warning-300"
            >
                A branch is required
            </h2>

            <p
                class="mt-2 text-sm text-warning-700 dark:text-warning-400"
            >
                Create at least one branch before adding a warehouse.
            </p>

            <Link
                v-if="can('branches.create')"
                href="/erp/branches/create"
                class="mt-4 inline-flex h-10 items-center justify-center rounded-lg bg-warning-500 px-4 text-sm font-medium text-white transition hover:bg-warning-600"
            >
                Create branch
            </Link>
        </div>

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
                    Warehouse information
                </h2>
            </div>

            <div class="space-y-6 p-5 sm:p-6">
                <div>
                    <label
                        for="branch_id"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch
                        <span class="text-error-500">*</span>
                    </label>

                    <select
                        id="branch_id"
                        v-model.number="form.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="form.errors.branch_id
                            ? 'border-error-500'
                            : ''"
                        :disabled="!hasBranches"
                    >
                        <option value="" disabled>
                            Select a branch
                        </option>

                        <option
                            v-for="branch in branchOptions"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }} — {{ branch.code }}
                            <template v-if="branch.status !== 'active'">
                                ({{ branch.status }})
                            </template>
                        </option>
                    </select>

                    <p
                        v-if="form.errors.branch_id"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.branch_id }}
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Warehouse name
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Dhaka Main Warehouse"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="form.errors.name
                                ? 'border-error-500'
                                : ''"
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
                            for="code"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Warehouse code
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            maxlength="50"
                            placeholder="DHK-MAIN"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="form.errors.code
                                ? 'border-error-500'
                                : ''"
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use letters, numbers, hyphens, or underscores.
                        </p>

                        <p
                            v-if="form.errors.code"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.code }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="type"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Warehouse type
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="type"
                            v-model="form.type"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.type
                                ? 'border-error-500'
                                : ''"
                        >
                            <option
                                v-for="option in typeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.type"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="status"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Status
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="status"
                            v-model="form.status"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.status
                                ? 'border-error-500'
                                : ''"
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

                <div
                    class="rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                >
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            v-model="form.is_default"
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                        >

                        <span>
                            <span
                                class="block text-sm font-medium text-gray-800 dark:text-white/90"
                            >
                                Default warehouse for this branch
                            </span>

                            <span
                                class="mt-1 block text-sm text-gray-500 dark:text-gray-400"
                            >
                                Selecting this will remove the default flag
                                from the branch’s current default warehouse.
                                Only an active warehouse can be default.
                            </span>
                        </span>
                    </label>

                    <p
                        v-if="form.errors.is_default"
                        class="mt-2 text-sm text-error-500"
                    >
                        {{ form.errors.is_default }}
                    </p>
                </div>

                <div>
                    <label
                        for="address"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Warehouse address
                    </label>

                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="4"
                        placeholder="Enter the warehouse address"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        :class="form.errors.address
                            ? 'border-error-500'
                            : ''"
                    />

                    <p
                        v-if="form.errors.address"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.address }}
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
            >
                <Link
                    href="/erp/warehouses"
                    class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                >
                    Cancel
                </Link>

                <button
                    type="submit"
                    class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing || !hasBranches"
                >
                    {{
                        form.processing
                            ? 'Saving...'
                            : submitLabel
                    }}
                </button>
            </div>
        </form>
    </div>
</template>