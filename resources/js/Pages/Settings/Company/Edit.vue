<script setup lang="ts">
import {
    Head,
    useForm,
} from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';

defineOptions({
    layout: ErpLayout,
});

interface CompanySettings {
    id: number;
    name: string;
    code: string;
    slug: string;
    status: string;
    currency_code: string;
    timezone: string;
    email: string | null;
    phone: string | null;
    address: string | null;
}

interface CompanySettingsForm {
    name: string;
    currency_code: string;
    timezone: string;
    email: string;
    phone: string;
    address: string;
}

const props = defineProps<{
    company: CompanySettings;
    timezoneOptions: string[];
}>();

const form = useForm<CompanySettingsForm>({
    name: props.company.name,
    currency_code: props.company.currency_code,
    timezone: props.company.timezone,
    email: props.company.email ?? '',
    phone: props.company.phone ?? '',
    address: props.company.address ?? '',
});

const submit = (): void => {
    form.currency_code = form.currency_code.toUpperCase();

    form.patch('/erp/settings', {
        preserveScroll: true,
        onSuccess: (): void => {
            form.defaults();
        },
    });
};
</script>

<template>
    <Head title="Company Settings" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Company Settings
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage the organisation details used throughout this tenant.
            </p>
        </div>

        <div
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
        >
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
                        Organisation profile
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        These details appear on ERP screens and future business
                        documents.
                    </p>
                </div>

                <div class="space-y-6 p-5 sm:p-6">
                    <div>
                        <label
                            for="name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Company name
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            autocomplete="organization"
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

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label
                                for="email"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                            >
                                Company email
                            </label>

                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                placeholder="company@example.com"
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

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label
                                for="currency_code"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                            >
                                Base currency
                                <span class="text-error-500">*</span>
                            </label>

                            <input
                                id="currency_code"
                                v-model="form.currency_code"
                                type="text"
                                maxlength="3"
                                placeholder="BDT"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                                :class="form.errors.currency_code
                                    ? 'border-error-500'
                                    : ''"
                            >

                            <p
                                class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Use a three-letter ISO 4217 code, such as BDT
                                or USD.
                            </p>

                            <p
                                v-if="form.errors.currency_code"
                                class="mt-1.5 text-sm text-error-500"
                            >
                                {{ form.errors.currency_code }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="timezone"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                            >
                                Timezone
                                <span class="text-error-500">*</span>
                            </label>

                            <input
                                id="timezone"
                                v-model="form.timezone"
                                type="text"
                                list="timezone-options"
                                placeholder="Asia/Dhaka"
                                autocomplete="off"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                                :class="form.errors.timezone
                                    ? 'border-error-500'
                                    : ''"
                            >

                            <datalist id="timezone-options">
                                <option
                                    v-for="timezone in timezoneOptions"
                                    :key="timezone"
                                    :value="timezone"
                                />
                            </datalist>

                            <p
                                v-if="form.errors.timezone"
                                class="mt-1.5 text-sm text-error-500"
                            >
                                {{ form.errors.timezone }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label
                            for="address"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Company address
                        </label>

                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="4"
                            autocomplete="street-address"
                            placeholder="Enter the registered or primary business address"
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
                    class="flex items-center justify-end gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
                >
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing || !form.isDirty"
                    >
                        <svg
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />

                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4Z"
                            />
                        </svg>

                        {{
                            form.processing
                                ? 'Saving...'
                                : 'Save changes'
                        }}
                    </button>
                </div>
            </form>

            <aside class="space-y-6">
                <div
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <h2
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Tenant identity
                    </h2>

                    <dl class="mt-4 space-y-4">
                        <div>
                            <dt
                                class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                            >
                                Company code
                            </dt>

                            <dd
                                class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ company.code }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                            >
                                Slug
                            </dt>

                            <dd
                                class="mt-1 break-all text-sm font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ company.slug }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                            >
                                Account status
                            </dt>

                            <dd class="mt-1">
                                <span
                                    class="inline-flex rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium capitalize text-success-700 dark:bg-success-500/15 dark:text-success-400"
                                >
                                    {{
                                        company.status.replace(
                                            '_',
                                            ' ',
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="rounded-2xl border border-warning-200 bg-warning-25 p-5 dark:border-warning-500/20 dark:bg-warning-500/10"
                >
                    <h2
                        class="text-sm font-semibold text-warning-800 dark:text-warning-300"
                    >
                        Protected identifiers
                    </h2>

                    <p
                        class="mt-2 text-sm leading-6 text-warning-700 dark:text-warning-400"
                    >
                        The company code, slug, and subscription status are
                        not editable here because they control tenant identity
                        and access.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>