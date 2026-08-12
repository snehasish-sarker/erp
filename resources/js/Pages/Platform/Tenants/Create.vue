<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformTenantCreateProps,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformTenantCreateProps>();

const form = useForm({
    name: '',
    code: '',
    status: props.defaults.status,
    currency_code: props.defaults.currency_code,
    timezone: props.defaults.timezone,
    email: '',
    phone: '',
    address: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
});

const submit = (): void => {
    form.post(
        route('platform.tenants.store'),
        {
            preserveScroll: true,
            onSuccess: (): void => {
                form.reset(
                    'admin_password',
                    'admin_password_confirmation',
                );
            },
        },
    );
};
</script>

<template>
    <Head title="Provision Tenant" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-medium text-brand-600 dark:text-brand-400">
                    Platform control plane
                </p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                    Provision Tenant
                </h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    Create a new company and its first Tenant Owner. A Main Branch,
                    Main Warehouse, default chart of accounts, and document numbering
                    sequences will be provisioned automatically.
                </p>
            </div>

            <Link
                :href="route('platform.tenants.index')"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Back to Tenants
            </Link>
        </div>

        <form
            class="space-y-6"
            @submit.prevent="submit"
        >
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-5">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Company
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        The company code is used on the normal ERP login screen.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Company name
                        </span>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            autocomplete="organization"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span v-if="form.errors.name" class="text-xs text-error-600">
                            {{ form.errors.name }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Company code
                        </span>
                        <input
                            v-model="form.code"
                            type="text"
                            required
                            maxlength="50"
                            placeholder="ACME-BD"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span class="block text-xs text-gray-500">
                            Letters, numbers, hyphens, and underscores only.
                        </span>
                        <span v-if="form.errors.code" class="text-xs text-error-600">
                            {{ form.errors.code }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Initial status
                        </span>
                        <select
                            v-model="form.status"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="trial">Trial</option>
                            <option value="active">Active</option>
                        </select>
                        <span v-if="form.errors.status" class="text-xs text-error-600">
                            {{ form.errors.status }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Currency code
                        </span>
                        <input
                            v-model="form.currency_code"
                            type="text"
                            required
                            maxlength="3"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span v-if="form.errors.currency_code" class="text-xs text-error-600">
                            {{ form.errors.currency_code }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Timezone
                        </span>
                        <input
                            v-model="form.timezone"
                            type="text"
                            required
                            placeholder="Asia/Dhaka"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span v-if="form.errors.timezone" class="text-xs text-error-600">
                            {{ form.errors.timezone }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Company email
                        </span>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span v-if="form.errors.email" class="text-xs text-error-600">
                            {{ form.errors.email }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Phone
                        </span>
                        <input
                            v-model="form.phone"
                            type="text"
                            maxlength="50"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span v-if="form.errors.phone" class="text-xs text-error-600">
                            {{ form.errors.phone }}
                        </span>
                    </label>

                    <label class="space-y-1.5 md:col-span-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Address
                        </span>
                        <textarea
                            v-model="form.address"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                        <span v-if="form.errors.address" class="text-xs text-error-600">
                            {{ form.errors.address }}
                        </span>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-5">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        First Tenant Owner
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        This account receives company-wide Tenant Owner access and can
                        create the tenant's remaining users and roles.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Administrator name
                        </span>
                        <input
                            v-model="form.admin_name"
                            type="text"
                            required
                            autocomplete="name"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span v-if="form.errors.admin_name" class="text-xs text-error-600">
                            {{ form.errors.admin_name }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Administrator email
                        </span>
                        <input
                            v-model="form.admin_email"
                            type="email"
                            required
                            autocomplete="email"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span v-if="form.errors.admin_email" class="text-xs text-error-600">
                            {{ form.errors.admin_email }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </span>
                        <input
                            v-model="form.admin_password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                        <span class="block text-xs text-gray-500">
                            Minimum 12 characters with uppercase, lowercase, number, and symbol.
                        </span>
                        <span v-if="form.errors.admin_password" class="text-xs text-error-600">
                            {{ form.errors.admin_password }}
                        </span>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Confirm password
                        </span>
                        <input
                            v-model="form.admin_password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                    </label>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link
                    :href="route('platform.tenants.index')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Cancel
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ form.processing ? 'Provisioning…' : 'Provision Tenant' }}
                </button>
            </div>
        </form>
    </div>
</template>
