<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';

defineOptions({
    layout: ErpLayout,
});

const form = useForm<{
    name: string;
}>({
    name: '',
});

const submit = (): void => {
    form.post('/erp/roles');
};
</script>

<template>
    <Head title="Create Role" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Create Role
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Create a custom tenant role. Permissions can be assigned
                after the role is created.
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
                    Role information
                </h2>
            </div>

            <div class="space-y-6 p-5 sm:p-6">
                <div>
                    <label
                        for="role-name"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Role name
                        <span class="text-error-500">*</span>
                    </label>

                    <input
                        id="role-name"
                        v-model="form.name"
                        type="text"
                        autocomplete="off"
                        placeholder="Regional Sales Supervisor"
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

                <div
                    class="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/20 dark:bg-brand-500/10"
                >
                    <p
                        class="text-sm leading-6 text-brand-700 dark:text-brand-300"
                    >
                        Seeded roles such as Tenant Owner, Accountant, and
                        Warehouse Manager are reserved system names. Use a
                        unique name for this custom role.
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
            >
                <Link
                    href="/erp/roles"
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
                            ? 'Creating...'
                            : 'Create role'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>