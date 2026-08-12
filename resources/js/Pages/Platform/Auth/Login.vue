<script setup lang="ts">
import {
    Head,
    useForm,
} from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineOptions({
    layout: GuestLayout,
});

interface PlatformLoginForm {
    email: string;
    password: string;
    remember: boolean;
}

const form = useForm<PlatformLoginForm>({
    email: '',
    password: '',
    remember: false,
});

const submit = (): void => {
    form.post(
        route('platform.login.store'),
        {
            preserveScroll: true,
            onFinish: (): void => {
                form.reset('password');
            },
        },
    );
};
</script>

<template>
    <Head title="Super Admin Sign In" />

    <div
        class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark sm:p-8"
    >
        <div class="mb-8 text-center">
            <div
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"
            >
                <span class="text-lg font-bold">SA</span>
            </div>

            <h1
                class="mt-5 text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Super Admin Console
            </h1>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Platform administrators only. Tenant company credentials do not work here.
            </p>
        </div>

        <form
            class="space-y-5"
            @submit.prevent="submit"
        >
            <div>
                <label
                    for="platform_email"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Email address
                </label>

                <input
                    id="platform_email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    autofocus
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    :class="form.errors.email ? 'border-error-500' : ''"
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
                    for="platform_password"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Password
                </label>

                <input
                    id="platform_password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    :class="form.errors.password ? 'border-error-500' : ''"
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
                {{ form.processing ? 'Signing in...' : 'Sign in to platform' }}
            </button>
        </form>
    </div>
</template>
