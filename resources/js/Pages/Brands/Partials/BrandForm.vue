<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import type {
    BrandFormData,
    BrandOption,
    BrandRecord,
    BrandStatus,
} from '@/Types/brand';

const props = defineProps<{
    mode: 'create' | 'edit';
    brand?: BrandRecord;
    statusOptions: BrandOption<BrandStatus>[];
}>();

const form = useForm<BrandFormData>({
    name: props.brand?.name ?? '',
    code: props.brand?.code ?? '',
    slug: props.brand?.slug ?? '',
    website_url: props.brand?.website_url ?? '',
    description: props.brand?.description ?? '',
    sort_order: props.brand?.sort_order ?? 0,
    status: props.brand?.status ?? 'active',
});

const normalizeSlug = (value: string): string =>
    value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

const submit = (): void => {
    form.name = form.name.trim();

    form.code = form.code
        .trim()
        .toUpperCase();

    form.slug = normalizeSlug(
        form.slug,
    );

    form.website_url =
        form.website_url.trim();

    form.description =
        form.description.trim();

    if (
        props.mode === 'edit'
        && props.brand !== undefined
    ) {
        form.put(
            `/erp/brands/${props.brand.id}`,
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        '/erp/brands',
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
                Brand information
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Configure the brand identifiers,
                website, display order, and
                operational status.
            </p>
        </div>

        <div class="space-y-6 p-5 sm:p-6">
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label
                        for="brand-name"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Name
                        <span class="text-error-500">*</span>
                    </label>

                    <input
                        id="brand-name"
                        v-model="form.name"
                        type="text"
                        maxlength="120"
                        placeholder="Samsung"
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
                        for="brand-code"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Code
                        <span class="text-error-500">*</span>
                    </label>

                    <input
                        id="brand-code"
                        v-model="form.code"
                        type="text"
                        maxlength="40"
                        placeholder="SAMSUNG"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        :class="form.errors.code
                            ? 'border-error-500'
                            : ''"
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
                        for="brand-slug"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Slug
                    </label>

                    <input
                        id="brand-slug"
                        v-model="form.slug"
                        type="text"
                        maxlength="160"
                        placeholder="samsung"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        :class="form.errors.slug
                            ? 'border-error-500'
                            : ''"
                        @blur="
                            form.slug = normalizeSlug(
                                form.slug,
                            )
                        "
                    >

                    <p
                        class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Leave blank to generate it from the name.
                        Slugs remain reserved after deletion.
                    </p>

                    <p
                        v-if="form.errors.slug"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.slug }}
                    </p>
                </div>

                <div>
                    <label
                        for="brand-website"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Website
                    </label>

                    <input
                        id="brand-website"
                        v-model="form.website_url"
                        type="url"
                        maxlength="2048"
                        placeholder="https://www.samsung.com"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        :class="form.errors.website_url
                            ? 'border-error-500'
                            : ''"
                    >

                    <p
                        class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Use a complete HTTP or HTTPS address.
                    </p>

                    <p
                        v-if="form.errors.website_url"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.website_url }}
                    </p>
                </div>

                <div>
                    <label
                        for="brand-sort-order"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Sort order
                        <span class="text-error-500">*</span>
                    </label>

                    <input
                        id="brand-sort-order"
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        max="4294967295"
                        step="1"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="form.errors.sort_order
                            ? 'border-error-500'
                            : ''"
                    >

                    <p
                        v-if="form.errors.sort_order"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.sort_order }}
                    </p>
                </div>

                <div>
                    <label
                        for="brand-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                        <span class="text-error-500">*</span>
                    </label>

                    <select
                        id="brand-status"
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

            <div>
                <label
                    for="brand-description"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Description
                </label>

                <textarea
                    id="brand-description"
                    v-model="form.description"
                    rows="5"
                    maxlength="2000"
                    placeholder="Optional internal description"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    :class="form.errors.description
                        ? 'border-error-500'
                        : ''"
                />

                <div
                    class="mt-1.5 flex items-center justify-between gap-4"
                >
                    <p
                        v-if="form.errors.description"
                        class="text-sm text-error-500"
                    >
                        {{ form.errors.description }}
                    </p>

                    <span
                        class="ml-auto text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ form.description.length }}/2000
                    </span>
                </div>
            </div>
        </div>

        <div
            class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
        >
            <Link
                href="/erp/brands"
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
                            ? 'Update brand'
                            : 'Create brand'
                }}
            </button>
        </div>
    </form>
</template>