<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineOptions({
    layout: GuestLayout,
});

interface LoginForm {
    tenant_code: string;
    email: string;
    password: string;
    remember: boolean;
}

const form = useForm<LoginForm>({
    tenant_code: '',
    email: '',
    password: '',
    remember: false,
});

const submit = (): void => {
    form.post('/login', {
        preserveScroll: true,
        onFinish: (): void => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Sign in" />

    <div
        class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark sm:p-8"
    >
        <div class="mb-8 text-center">
            <Link
                href="/login"
                class="inline-flex justify-center"
            >
                <img
                    src="/images/logo/logo.svg"
                    alt="ERP"
                    class="h-10 w-auto dark:hidden"
                >

                <img
                    src="/images/logo/logo-dark.svg"
                    alt="ERP"
                    class="hidden h-10 w-auto dark:block"
                >
            </Link>

            <h1
                class="mt-6 text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Sign in to ERP
            </h1>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Enter your company code and account credentials.
            </p>
        </div>

        <form
            class="space-y-5"
            @submit.prevent="submit"
        >
            <div>
                <label
                    for="tenant_code"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Company code
                </label>

                <input
                    id="tenant_code"
                    v-model="form.tenant_code"
                    type="text"
                    autocomplete="organization"
                    placeholder="ERP-DEMO"
                    autofocus
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    :class="form.errors.tenant_code
                        ? 'border-error-500'
                        : ''"
                >

                <p
                    v-if="form.errors.tenant_code"
                    class="mt-1.5 text-sm text-error-500"
                >
                    {{ form.errors.tenant_code }}
                </p>
            </div>

            <div>
                <label
                    for="email"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Email address
                </label>

                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    placeholder="admin@erp.test"
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
                    for="password"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Password
                </label>

                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    :class="form.errors.password
                        ? 'border-error-500'
                        : ''"
                >

                <p
                    v-if="form.errors.password"
                    class="mt-1.5 text-sm text-error-500"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex cursor-pointer items-center gap-3">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                >

                <span class="text-sm text-gray-700 dark:text-gray-400">
                    Keep me signed in
                </span>
            </label>

            <button
                type="submit"
                class="flex h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing"
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

                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </button>
        </form>
    </div>
</template>