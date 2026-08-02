<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type {
    BranchRecord,
    BranchStatus,
    BranchStatusOption,
} from '@/Types/branch';

interface BranchFormInput {
    name: string;
    code: string;
    status: BranchStatus;
    email: string;
    phone: string;
    address: string;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    branch?: BranchRecord;
    statusOptions: BranchStatusOption[];
}>();

const form = useForm<BranchFormInput>({
    name: props.branch?.name ?? '',
    code: props.branch?.code ?? '',
    status: props.branch?.status ?? 'active',
    email: props.branch?.email ?? '',
    phone: props.branch?.phone ?? '',
    address: props.branch?.address ?? '',
});

const title: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create Branch'
        : 'Edit Branch',
);

const description: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Add an operating branch for this company.'
        : 'Update the selected branch details.',
);

const submitLabel: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create branch'
        : 'Save changes',
);

const submit = (): void => {
    form.code = form.code.trim().toUpperCase();

    if (props.mode === 'create') {
        form.post('/erp/branches');

        return;
    }

    if (props.branch === undefined) {
        return;
    }

    form.put(`/erp/branches/${props.branch.id}`);
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
                    Branch information
                </h2>
            </div>

            <div class="space-y-6 p-5 sm:p-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Branch name
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            autocomplete="organization"
                            placeholder="Dhaka Central Branch"
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
                            Branch code
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            maxlength="50"
                            autocomplete="off"
                            placeholder="DHK-CENTRAL"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="form.errors.code
                                ? 'border-error-500'
                                : ''"
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use letters, numbers, hyphens, or underscores only.
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

                    <div>
                        <label
                            for="phone"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Phone number
                        </label>

                        <input
                            id="phone"
                            v-model="form.phone"
                            type="text"
                            autocomplete="tel"
                            placeholder="+880 1XXXXXXXXX"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="form.errors.phone
                                ? 'border-error-500'
                                : ''"
                        >

                        <p
                            v-if="form.errors.phone"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        for="email"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch email
                    </label>

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        placeholder="branch@example.com"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        :class="form.errors.email
                            ? 'border-error-500'
                            : ''"
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
                        for="address"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Address
                    </label>

                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="4"
                        autocomplete="street-address"
                        placeholder="Enter the complete branch address"
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
                    href="/erp/branches"
                    class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
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
                            : submitLabel
                    }}
                </button>
            </div>
        </form>
    </div>
</template>