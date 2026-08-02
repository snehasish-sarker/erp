<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    ref,
    watch,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import type {
    DocumentNumberPreviewContext,
    DocumentResetPolicy,
    DocumentSequenceBranch,
    DocumentSequenceOption,
    DocumentSequenceRecord,
    DocumentSequenceStatus,
    DocumentTypeOption,
} from '@/Types/document-numbering';

interface DocumentSequenceFormInput {
    branch_id: number | null;
    name: string;
    document_type: string;
    prefix: string;
    suffix: string;
    current_number: number;
    number_padding: number;
    reset_policy: DocumentResetPolicy;
    fiscal_year_start_month: number | null;
    status: DocumentSequenceStatus;
}

type SequenceScope = 'company' | 'branch';

const props = defineProps<{
    mode: 'create' | 'edit';
    documentSequence?: DocumentSequenceRecord;
    branchOptions: DocumentSequenceBranch[];
    documentTypeOptions: DocumentTypeOption[];
    resetPolicyOptions: DocumentSequenceOption<DocumentResetPolicy>[];
    statusOptions: DocumentSequenceOption<DocumentSequenceStatus>[];
    previewContext: DocumentNumberPreviewContext;
}>();

const initialScope: SequenceScope =
    props.documentSequence === undefined
    || props.documentSequence.branch_id === null
        ? 'company'
        : 'branch';

const scope: Ref<SequenceScope> = ref(initialScope);

const form = useForm<DocumentSequenceFormInput>({
    branch_id: props.documentSequence?.branch_id ?? null,
    name: props.documentSequence?.name ?? '',

    document_type:
        props.documentSequence?.document_type ?? '',

    prefix: props.documentSequence?.prefix ?? '',
    suffix: props.documentSequence?.suffix ?? '',

    current_number:
        props.documentSequence?.current_number ?? 0,

    number_padding:
        props.documentSequence?.number_padding ?? 6,

    reset_policy:
        props.documentSequence?.reset_policy ?? 'never',

    fiscal_year_start_month:
        props.documentSequence
            ?.fiscal_year_start_month
        ?? 1,

    status: props.documentSequence?.status ?? 'active',
});

const title: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create Document Sequence'
        : 'Edit Document Sequence',
);

const description: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Configure a reusable sequence for future ERP documents.'
        : 'Update the selected numbering sequence and preview its next number.',
);

const submitLabel: ComputedRef<string> = computed(
    (): string => props.mode === 'create'
        ? 'Create sequence'
        : 'Save changes',
);

const hasBranches: ComputedRef<boolean> = computed(
    (): boolean => props.branchOptions.length > 0,
);

const identityLocked: ComputedRef<boolean> = computed(
    (): boolean =>
        props.documentSequence?.has_allocations === true,
);

const selectedBranch: ComputedRef<
    DocumentSequenceBranch | null
> = computed(
    (): DocumentSequenceBranch | null =>
        props.branchOptions.find(
            (
                branch: DocumentSequenceBranch,
            ): boolean =>
                branch.id === form.branch_id,
        ) ?? null,
);

const fiscalYearStart: ComputedRef<number> = computed(
    (): number =>
        form.fiscal_year_start_month ?? 1,
);

const fiscalStartYear: ComputedRef<number> = computed(
    (): number =>
        props.previewContext.current_month
            >= fiscalYearStart.value
            ? props.previewContext.current_year
            : props.previewContext.current_year - 1,
);

const currentResetKey: ComputedRef<string> = computed(
    (): string => {
        if (form.reset_policy === 'calendar_year') {
            return String(
                props.previewContext.current_year,
            );
        }

        if (form.reset_policy === 'fiscal_year') {
            return `${fiscalStartYear.value}-${fiscalStartYear.value + 1}`;
        }

        return 'never';
    },
);

const nextSequenceNumber: ComputedRef<number> = computed(
    (): number => {
        const resetIsDue =
            form.reset_policy !== 'never'
            && props.documentSequence?.last_reset_key
                !== null
            && props.documentSequence?.last_reset_key
                !== undefined
            && props.documentSequence.last_reset_key
                !== currentResetKey.value;

        return resetIsDue
            ? 1
            : form.current_number + 1;
    },
);

const preview: ComputedRef<string> = computed(
    (): string => {
        const year = String(
            props.previewContext.current_year,
        );

        const branchCode =
            scope.value === 'branch'
                ? selectedBranch.value?.code
                    ?? props.previewContext.company_code
                : props.previewContext.company_code;

        const documentTypeToken =
            form.document_type === ''
                ? 'TYPE'
                : form.document_type
                    .replaceAll('_', '-')
                    .toUpperCase();

        const replacements: Record<string, string> = {
            '{YYYY}': year,
            '{YY}': year.slice(-2),

            '{FY}':
                `${fiscalStartYear.value}-${fiscalStartYear.value + 1}`,

            '{FY_SHORT}':
                `${String(fiscalStartYear.value).slice(-2)}-${String(fiscalStartYear.value + 1).slice(-2)}`,

            '{BRANCH}': branchCode,
            '{TYPE}': documentTypeToken,
        };

        const replaceTokens = (
            value: string,
        ): string =>
            Object.entries(replacements).reduce(
                (
                    result: string,
                    [
                        token,
                        replacement,
                    ]: [string, string],
                ): string =>
                    result.replaceAll(
                        token,
                        replacement,
                    ),
                value,
            );

        return replaceTokens(form.prefix)
            + String(nextSequenceNumber.value).padStart(
                form.number_padding,
                '0',
            )
            + replaceTokens(form.suffix);
    },
);

watch(
    scope,
    (newScope: SequenceScope): void => {
        if (newScope === 'company') {
            form.branch_id = null;
            form.clearErrors('branch_id');
        }
    },
);

watch(
    (): string => form.document_type,
    (
        documentType: string,
        previousType: string,
    ): void => {
        if (
            props.mode !== 'create'
            || previousType !== ''
            || form.prefix !== ''
        ) {
            return;
        }

        const option = props.documentTypeOptions.find(
            (
                typeOption: DocumentTypeOption,
            ): boolean =>
                typeOption.value === documentType,
        );

        form.prefix = option?.default_prefix ?? '';
    },
);

watch(
    (): DocumentResetPolicy =>
        form.reset_policy,
    (
        resetPolicy: DocumentResetPolicy,
    ): void => {
        if (resetPolicy !== 'fiscal_year') {
            form.fiscal_year_start_month = null;

            form.clearErrors(
                'fiscal_year_start_month',
            );

            return;
        }

        if (
            form.fiscal_year_start_month === null
        ) {
            form.fiscal_year_start_month = 1;
        }
    },
);

const submit = (): void => {
    if (
        scope.value === 'branch'
        && form.branch_id === null
    ) {
        form.setError(
            'branch_id',
            'Select the branch that will use this sequence.',
        );

        return;
    }

    form.name = form.name.trim();
    form.prefix = form.prefix.trim();
    form.suffix = form.suffix.trim();

    if (props.mode === 'create') {
        form.post('/erp/document-numbering');

        return;
    }

    if (props.documentSequence === undefined) {
        return;
    }

    form.put(
        `/erp/document-numbering/${props.documentSequence.id}`,
    );
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
            v-if="identityLocked"
            class="rounded-2xl border border-warning-200 bg-warning-25 p-5 dark:border-warning-500/20 dark:bg-warning-500/10"
        >
            <p
                class="text-sm font-semibold text-warning-800 dark:text-warning-300"
            >
                Sequence identity is protected
            </p>

            <p
                class="mt-1 text-sm text-warning-700 dark:text-warning-400"
            >
                This sequence has allocated numbers. Its scope,
                document type, and current number can no longer
                be changed.
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
                    Sequence configuration
                </h2>
            </div>

            <div class="space-y-6 p-5 sm:p-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="sequence-name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Sequence name
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="sequence-name"
                            v-model="form.name"
                            type="text"
                            maxlength="120"
                            placeholder="Purchase Order Numbering"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="
                                form.errors.name
                                    ? 'border-error-500'
                                    : ''
                            "
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
                            for="document-type"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Document type
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="document-type"
                            v-model="form.document_type"
                            :disabled="identityLocked"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="
                                form.errors.document_type
                                    ? 'border-error-500'
                                    : ''
                            "
                        >
                            <option value="" disabled>
                                Select a document type
                            </option>

                            <option
                                v-for="option in documentTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.document_type"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.document_type }}
                        </p>
                    </div>
                </div>

                <div class="space-y-3">
                    <p
                        class="text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Sequence scope
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                            :class="
                                scope === 'company'
                                    ? 'border-brand-500 bg-brand-50/50 dark:border-brand-500 dark:bg-brand-500/10'
                                    : ''
                            "
                        >
                            <input
                                v-model="scope"
                                type="radio"
                                value="company"
                                :disabled="identityLocked"
                                class="mt-1"
                            >

                            <span>
                                <span
                                    class="block text-sm font-semibold text-gray-800 dark:text-white/90"
                                >
                                    Company-wide
                                </span>

                                <span
                                    class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Used when no branch-specific
                                    override exists.
                                </span>
                            </span>
                        </label>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                            :class="
                                scope === 'branch'
                                    ? 'border-brand-500 bg-brand-50/50 dark:border-brand-500 dark:bg-brand-500/10'
                                    : ''
                            "
                        >
                            <input
                                v-model="scope"
                                type="radio"
                                value="branch"
                                :disabled="
                                    identityLocked
                                    || !hasBranches
                                "
                                class="mt-1"
                            >

                            <span>
                                <span
                                    class="block text-sm font-semibold text-gray-800 dark:text-white/90"
                                >
                                    Branch-specific
                                </span>

                                <span
                                    class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Overrides the company sequence
                                    for one branch.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <div v-if="scope === 'branch'">
                    <label
                        for="sequence-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch
                        <span class="text-error-500">*</span>
                    </label>

                    <select
                        id="sequence-branch"
                        v-model.number="form.branch_id"
                        :disabled="identityLocked"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="
                            form.errors.branch_id
                                ? 'border-error-500'
                                : ''
                        "
                    >
                        <option :value="null" disabled>
                            Select a branch
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
                        v-if="form.errors.branch_id"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ form.errors.branch_id }}
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="sequence-prefix"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Prefix
                        </label>

                        <input
                            id="sequence-prefix"
                            v-model="form.prefix"
                            type="text"
                            maxlength="60"
                            placeholder="PO-{YYYY}-"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="
                                form.errors.prefix
                                    ? 'border-error-500'
                                    : ''
                            "
                        >

                        <p
                            v-if="form.errors.prefix"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.prefix }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="sequence-suffix"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Suffix
                        </label>

                        <input
                            id="sequence-suffix"
                            v-model="form.suffix"
                            type="text"
                            maxlength="60"
                            placeholder="-{BRANCH}"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="
                                form.errors.suffix
                                    ? 'border-error-500'
                                    : ''
                            "
                        >

                        <p
                            v-if="form.errors.suffix"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.suffix }}
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    >
                        Supported tokens
                    </p>

                    <p
                        class="mt-2 text-sm text-gray-600 dark:text-gray-300"
                    >
                        {YYYY}, {YY}, {FY}, {FY_SHORT},
                        {BRANCH}, {TYPE}
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div>
                        <label
                            for="current-number"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Current number
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="current-number"
                            v-model.number="form.current_number"
                            type="number"
                            min="0"
                            max="999999999999"
                            :disabled="identityLocked"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="
                                form.errors.current_number
                                    ? 'border-error-500'
                                    : ''
                            "
                        >

                        <p
                            v-if="form.errors.current_number"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.current_number }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="number-padding"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Number padding
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="number-padding"
                            v-model.number="form.number_padding"
                            type="number"
                            min="3"
                            max="12"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="
                                form.errors.number_padding
                                    ? 'border-error-500'
                                    : ''
                            "
                        >

                        <p
                            v-if="form.errors.number_padding"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.number_padding }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="sequence-status"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Status
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="sequence-status"
                            v-model="form.status"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="
                                form.errors.status
                                    ? 'border-error-500'
                                    : ''
                            "
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

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="reset-policy"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Reset policy
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="reset-policy"
                            v-model="form.reset_policy"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="
                                form.errors.reset_policy
                                    ? 'border-error-500'
                                    : ''
                            "
                        >
                            <option
                                v-for="option in resetPolicyOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.reset_policy"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.reset_policy }}
                        </p>
                    </div>

                    <div
                        v-if="
                            form.reset_policy
                            === 'fiscal_year'
                        "
                    >
                        <label
                            for="fiscal-start-month"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Fiscal year start month
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="fiscal-start-month"
                            v-model.number="
                                form.fiscal_year_start_month
                            "
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="
                                form.errors
                                    .fiscal_year_start_month
                                    ? 'border-error-500'
                                    : ''
                            "
                        >
                            <option :value="1">January</option>
                            <option :value="2">February</option>
                            <option :value="3">March</option>
                            <option :value="4">April</option>
                            <option :value="5">May</option>
                            <option :value="6">June</option>
                            <option :value="7">July</option>
                            <option :value="8">August</option>
                            <option :value="9">September</option>
                            <option :value="10">October</option>
                            <option :value="11">November</option>
                            <option :value="12">December</option>
                        </select>

                        <p
                            v-if="
                                form.errors
                                    .fiscal_year_start_month
                            "
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{
                                form.errors
                                    .fiscal_year_start_month
                            }}
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-brand-200 bg-brand-50 p-5 dark:border-brand-500/20 dark:bg-brand-500/10"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300"
                    >
                        Next number preview
                    </p>

                    <p
                        class="mt-2 break-all font-mono text-xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ preview }}
                    </p>

                    <p
                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Preview uses
                        {{ previewContext.timezone }}.
                        Final numbers are allocated only
                        by the backend.
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
            >
                <Link
                    href="/erp/document-numbering"
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