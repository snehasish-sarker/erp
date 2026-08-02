<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
    ref,
    watch,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    DocumentResetPolicy,
    DocumentSequenceBranch,
    DocumentSequenceFilters,
    DocumentSequenceOption,
    DocumentSequencePagination,
    DocumentSequenceRecord,
    DocumentSequenceStatus,
    DocumentTypeOption,
} from '@/Types/document-numbering';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    documentSequences: DocumentSequencePagination;
    filters: DocumentSequenceFilters;
    branchOptions: DocumentSequenceBranch[];
    documentTypeOptions: DocumentTypeOption[];
    resetPolicyOptions: DocumentSequenceOption<DocumentResetPolicy>[];
    statusOptions: DocumentSequenceOption<DocumentSequenceStatus>[];
}>();

const { can } = useAuthorization();

const filterForm = reactive<DocumentSequenceFilters>({
    search: props.filters.search,
    scope: props.filters.scope,
    branch_id: props.filters.branch_id,
    document_type: props.filters.document_type,
    reset_policy: props.filters.reset_policy,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const deletingSequenceId: Ref<number | null> = ref(null);

const hasActiveFilters: ComputedRef<boolean> = computed(
    (): boolean =>
        filterForm.search !== ''
        || filterForm.scope !== ''
        || filterForm.branch_id !== null
        || filterForm.document_type !== ''
        || filterForm.reset_policy !== ''
        || filterForm.status !== '',
);

watch(
    (): DocumentSequenceFilters['scope'] =>
        filterForm.scope,

    (
        scope: DocumentSequenceFilters['scope'],
    ): void => {
        if (scope === 'company') {
            filterForm.branch_id = null;
        }
    },
);

const navigate = (page = 1): void => {
    router.get(
        '/erp/document-numbering',
        {
            search: filterForm.search,
            scope: filterForm.scope,
            branch_id: filterForm.branch_id,
            document_type: filterForm.document_type,
            reset_policy: filterForm.reset_policy,
            status: filterForm.status,
            sort: filterForm.sort,
            direction: filterForm.direction,
            per_page: filterForm.per_page,
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyFilters = (): void => {
    navigate();
};

const resetFilters = (): void => {
    filterForm.search = '';
    filterForm.scope = '';
    filterForm.branch_id = null;
    filterForm.document_type = '';
    filterForm.reset_policy = '';
    filterForm.status = '';
    filterForm.sort = 'name';
    filterForm.direction = 'asc';
    filterForm.per_page = 25;

    navigate();
};

const sortBy = (
    column: DocumentSequenceFilters['sort'],
): void => {
    if (filterForm.sort === column) {
        filterForm.direction =
            filterForm.direction === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        filterForm.sort = column;
        filterForm.direction = 'asc';
    }

    navigate();
};

const sortIndicator = (
    column: DocumentSequenceFilters['sort'],
): string => {
    if (filterForm.sort !== column) {
        return '';
    }

    return filterForm.direction === 'asc'
        ? '↑'
        : '↓';
};

const resetPolicyLabel = (
    resetPolicy: DocumentResetPolicy,
): string =>
    props.resetPolicyOptions.find(
        (
            option: DocumentSequenceOption<DocumentResetPolicy>,
        ): boolean =>
            option.value === resetPolicy,
    )?.label ?? resetPolicy;

const statusLabel = (
    status: DocumentSequenceStatus,
): string =>
    props.statusOptions.find(
        (
            option: DocumentSequenceOption<DocumentSequenceStatus>,
        ): boolean =>
            option.value === status,
    )?.label ?? status;

const statusBadgeClass = (
    status: DocumentSequenceStatus,
): string => {
    const classes: Record<
        DocumentSequenceStatus,
        string
    > = {
        active: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        inactive: 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400',
    };

    return classes[status];
};

const sequencePattern = (
    sequence: DocumentSequenceRecord,
): string => {
    const numericPlaceholder = '#'.repeat(
        Math.min(sequence.number_padding, 12),
    );

    return `${sequence.prefix ?? ''}${numericPlaceholder}${sequence.suffix ?? ''}`;
};

const deleteSequence = (
    sequence: DocumentSequenceRecord,
): void => {
    const confirmed = window.confirm(
        sequence.has_allocations
            ? `“${sequence.name}” has allocated numbers and cannot be deleted. Set it to inactive from the edit page instead.`
            : `Delete the document sequence “${sequence.name}”?`,
    );

    if (!confirmed || sequence.has_allocations) {
        return;
    }

    deletingSequenceId.value = sequence.id;

    router.delete(
        `/erp/document-numbering/${sequence.id}`,
        {
            preserveScroll: true,

            onFinish: (): void => {
                deletingSequenceId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Document Numbering" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Document Numbering
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Configure company and branch numbering
                    rules for ERP documents.
                </p>
            </div>

            <Link
                v-if="can('document_numbering.create')"
                href="/erp/document-numbering/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Add sequence
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <div class="sm:col-span-2">
                    <label
                        for="sequence-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="sequence-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Name, document type, prefix, suffix, or branch"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="sequence-scope"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Scope
                    </label>

                    <select
                        id="sequence-scope"
                        v-model="filterForm.scope"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All scopes
                        </option>

                        <option value="company">
                            Company-wide
                        </option>

                        <option value="branch">
                            Branch-specific
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="sequence-branch-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch
                    </label>

                    <select
                        id="sequence-branch-filter"
                        v-model.number="filterForm.branch_id"
                        :disabled="
                            filterForm.scope === 'company'
                        "
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="null">
                            All branches
                        </option>

                        <option
                            v-for="branch in branchOptions"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }} —
                            {{ branch.code }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="sequence-type-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Document type
                    </label>

                    <select
                        id="sequence-type-filter"
                        v-model="filterForm.document_type"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All document types
                        </option>

                        <option
                            v-for="option in documentTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="sequence-reset-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Reset policy
                    </label>

                    <select
                        id="sequence-reset-filter"
                        v-model="filterForm.reset_policy"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All reset policies
                        </option>

                        <option
                            v-for="option in resetPolicyOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="sequence-status-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="sequence-status-filter"
                        v-model="filterForm.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="sequence-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="sequence-per-page"
                        v-model.number="filterForm.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div
                    class="flex items-end gap-3 sm:col-span-2 xl:col-span-4"
                >
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white transition hover:bg-brand-600"
                    >
                        Apply filters
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    @click="sortBy('name')"
                                >
                                    Sequence
                                    {{ sortIndicator('name') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'document_type',
                                        )
                                    "
                                >
                                    Document type
                                    {{
                                        sortIndicator(
                                            'document_type',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Scope
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Pattern and next number
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'current_number',
                                        )
                                    "
                                >
                                    Current
                                    {{
                                        sortIndicator(
                                            'current_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Reset
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    @click="sortBy('status')"
                                >
                                    Status
                                    {{ sortIndicator('status') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="
                                sequence
                                in documentSequences.data
                            "
                            :key="sequence.id"
                        >
                            <td class="px-5 py-4 align-top">
                                <p
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ sequence.name }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        sequence
                                            .allocations_count
                                    }}
                                    allocated
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 align-top text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    sequence
                                        .document_type_label
                                }}
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300"
                                >
                                    {{
                                        sequence.branch === null
                                            ? 'Company-wide'
                                            : sequence
                                                .branch.code
                                    }}
                                </span>

                                <p
                                    v-if="
                                        sequence.branch
                                        !== null
                                    "
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        sequence
                                            .branch.name
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <p
                                    class="font-mono text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        sequencePattern(
                                            sequence,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 break-all font-mono text-sm font-semibold text-gray-800 dark:text-white/90"
                                >
                                    {{ sequence.preview }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 align-top text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    sequence
                                        .current_number
                                        .toLocaleString()
                                }}
                            </td>

                            <td
                                class="px-5 py-4 align-top text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    resetPolicyLabel(
                                        sequence.reset_policy,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusBadgeClass(
                                            sequence.status,
                                        )
                                    "
                                >
                                    {{
                                        statusLabel(
                                            sequence.status,
                                        )
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div
                                    class="flex justify-end gap-3"
                                >
                                    <Link
                                        v-if="
                                            can(
                                                'document_numbering.update',
                                            )
                                        "
                                        :href="`/erp/document-numbering/${sequence.id}/edit`"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="
                                            can(
                                                'document_numbering.delete',
                                            )
                                        "
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            deletingSequenceId
                                            === sequence.id
                                        "
                                        @click="
                                            deleteSequence(
                                                sequence,
                                            )
                                        "
                                    >
                                        {{
                                            deletingSequenceId
                                                === sequence.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                documentSequences.data
                                    .length === 0
                            "
                        >
                            <td
                                colspan="8"
                                class="px-5 py-12 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No document sequences found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Create a sequence or adjust
                                    the filters.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{ documentSequences.meta.from ?? 0 }}–{{
                        documentSequences.meta.to ?? 0
                    }}
                    of
                    {{ documentSequences.meta.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            documentSequences.meta
                                .current_page <= 1
                        "
                        @click="
                            navigate(
                                documentSequences.meta
                                    .current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Page
                        {{
                            documentSequences.meta
                                .current_page
                        }}
                        of
                        {{
                            documentSequences.meta
                                .last_page
                        }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            documentSequences.meta
                                .current_page
                            >= documentSequences.meta
                                .last_page
                        "
                        @click="
                            navigate(
                                documentSequences.meta
                                    .current_page + 1,
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>