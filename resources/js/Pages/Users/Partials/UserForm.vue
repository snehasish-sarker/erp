<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type {
    ManagedUserRecord,
    UserBranchOption,
    UserRoleOption,
    UserStatus,
    UserStatusOption,
} from '@/Types/user';

interface UserFormInput {
    branch_id: number | '';
    name: string;
    email: string;
    status: UserStatus;
    password: string;
    password_confirmation: string;
    role_ids: number[];
}

const props = defineProps<{
    mode: 'create' | 'edit';
    managedUser?: ManagedUserRecord;
    branchOptions: UserBranchOption[];
    roleOptions: UserRoleOption[];
    statusOptions: UserStatusOption[];
}>();

const form = useForm<UserFormInput>({
    branch_id: props.managedUser?.branch_id ?? '',
    name: props.managedUser?.name ?? '',
    email: props.managedUser?.email ?? '',
    status: props.managedUser?.status ?? 'active',
    password: '',
    password_confirmation: '',

    role_ids: props.managedUser?.roles.map(
        (role: UserRoleOption): number => role.id,
    ) ?? [],
});

const title: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create User'
        : 'Edit User',
);

const description: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create a tenant user and assign their branch and roles.'
        : 'Update the user profile, branch assignment, and roles.',
);

const submitLabel: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create user'
        : 'Save changes',
);

const currentStatusLabel: ComputedRef<string> = computed(
    (): string =>
        props.managedUser?.status.replace('_', ' ') ?? '',
);

const submit = (): void => {
    form.transform((data: UserFormInput) => {
        const sharedData = {
            branch_id: data.branch_id === ''
                ? null
                : data.branch_id,
            name: data.name,
            email: data.email,
            role_ids: data.role_ids,
        };

        if (props.mode === 'edit') {
            return sharedData;
        }

        return {
            ...sharedData,
            status: data.status,
            password: data.password,
            password_confirmation: data.password_confirmation,
        };
    });

    if (props.mode === 'create') {
        form.post('/erp/users');

        return;
    }

    if (props.managedUser === undefined) {
        return;
    }

    form.put(`/erp/users/${props.managedUser.id}`);
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
            v-if="managedUser?.is_current_user"
            class="rounded-2xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/20 dark:bg-brand-500/10"
        >
            <p class="text-sm text-brand-700 dark:text-brand-300">
                You are editing your own account. Status changes and
                administrator password resets are unavailable for your
                own account.
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
                    User information
                </h2>
            </div>

            <div class="space-y-6 p-5 sm:p-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Full name
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            placeholder="Enter the user’s full name"
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
                            for="email"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Email address
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="user@example.com"
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
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="branch_id"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Assigned branch
                        </label>

                        <select
                            id="branch_id"
                            v-model.number="form.branch_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.branch_id
                                ? 'border-error-500'
                                : ''"
                        >
                            <option value="">
                                Tenant-wide access / no branch
                            </option>

                            <option
                                v-for="branch in branchOptions"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }} — {{ branch.code }}

                                <template
                                    v-if="branch.status !== 'active'"
                                >
                                    ({{ branch.status }})
                                </template>
                            </option>
                        </select>

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Branch assignment is organisational metadata.
                            Module permissions still control access.
                        </p>

                        <p
                            v-if="form.errors.branch_id"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.branch_id }}
                        </p>
                    </div>

                    <div v-if="mode === 'create'">
                        <label
                            for="status"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Initial status
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

                    <div v-else>
                        <span
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Current status
                        </span>

                        <div
                            class="flex h-11 items-center rounded-lg border border-gray-200 bg-gray-50 px-4 text-sm font-medium capitalize text-gray-700 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-300"
                        >
                            {{ currentStatusLabel }}
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-3">
                        <span
                            class="block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Roles
                            <span class="text-error-500">*</span>
                        </span>

                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Select at least one tenant role. Effective
                            permissions come from the selected roles.
                        </p>
                    </div>

                    <div
                        class="grid gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800 sm:grid-cols-2 xl:grid-cols-3"
                        :class="form.errors.role_ids
                            ? 'border-error-500'
                            : ''"
                    >
                        <label
                            v-for="role in roleOptions"
                            :key="role.id"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03]"
                        >
                            <input
                                v-model="form.role_ids"
                                type="checkbox"
                                :value="role.id"
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                            >

                            <span
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ role.name }}
                            </span>
                        </label>
                    </div>

                    <p
                        v-if="form.errors.role_ids"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.role_ids }}
                    </p>
                </div>

                <div
                    v-if="mode === 'create'"
                    class="grid gap-6 border-t border-gray-200 pt-6 dark:border-gray-800 md:grid-cols-2"
                >
                    <div>
                        <label
                            for="password"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Password
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.password
                                ? 'border-error-500'
                                : ''"
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Use at least 12 characters with upper- and
                            lowercase letters and a number.
                        </p>

                        <p
                            v-if="form.errors.password"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="password_confirmation"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Confirm password
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        >
                    </div>
                </div>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
            >
                <Link
                    href="/erp/users"
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