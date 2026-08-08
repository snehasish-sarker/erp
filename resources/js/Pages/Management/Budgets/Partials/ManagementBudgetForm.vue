<script setup lang="ts">
import { computed } from 'vue';
import type {
    ManagementAccount,
    ManagementBranch,
    ManagementBudgetFormData,
    ManagementFiscalYear,
} from '@/Types/management';

interface BudgetFormState extends ManagementBudgetFormData {
    errors: Partial<Record<keyof ManagementBudgetFormData, string>>;
    processing: boolean;
}

const props = defineProps<{
    form: BudgetFormState;
    branches: ManagementBranch[];
    fiscalYears: ManagementFiscalYear[];
    accounts: ManagementAccount[];
    currencyCode: string;
    submitLabel: string;
}>();

const emit = defineEmits<{ submit: [] }>();
const months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];
const accountOptions = computed(() => props.accounts);

const addLine = (): void => {
    props.form.lines.push({
        account_id: null,
        month_number: 1,
        amount: '0.000000',
        notes: '',
    });
};

const removeLine = (index: number): void => {
    props.form.lines.splice(index, 1);
};

const addAccountYear = (account: ManagementAccount): void => {
    for (let month = 1; month <= 12; month += 1) {
        const exists = props.form.lines.some(
            (line) => line.account_id === account.id && line.month_number === month,
        );
        if (!exists) {
            props.form.lines.push({ account_id: account.id, month_number: month, amount: '0.000000', notes: '' });
        }
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Branch</span>
                <select v-model="form.branch_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    <option :value="null">Select branch</option>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} — {{ branch.name }}</option>
                </select>
                <span v-if="form.errors.branch_id" class="text-error-600">{{ form.errors.branch_id }}</span>
            </label>
            <label class="space-y-1 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Fiscal year</span>
                <select v-model="form.fiscal_year_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    <option :value="null">Select fiscal year</option>
                    <option v-for="year in fiscalYears" :key="year.id" :value="year.id">{{ year.name }} ({{ year.code }})</option>
                </select>
                <span v-if="form.errors.fiscal_year_id" class="text-error-600">{{ form.errors.fiscal_year_id }}</span>
            </label>
            <label class="space-y-1 text-sm md:col-span-2">
                <span class="font-medium text-gray-700 dark:text-gray-300">Budget name</span>
                <input v-model="form.name" type="text" maxlength="160" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <span v-if="form.errors.name" class="text-error-600">{{ form.errors.name }}</span>
            </label>
            <label class="space-y-1 text-sm md:col-span-2">
                <span class="font-medium text-gray-700 dark:text-gray-300">Notes</span>
                <textarea v-model="form.notes" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
            </label>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-800">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Monthly budget lines</h2>
                    <p class="text-xs text-gray-500">Amounts are entered in {{ currencyCode }}. One account/month combination is allowed.</p>
                </div>
                <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="addLine">Add line</button>
            </div>
            <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">Quick add 12 months</p>
                <div class="flex flex-wrap gap-2">
                    <button v-for="account in accountOptions" :key="account.id" type="button" class="rounded-full bg-gray-100 px-3 py-1 text-xs dark:bg-white/10" @click="addAccountYear(account)">
                        {{ account.code }} {{ account.name }}
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5">
                        <tr><th class="p-3">Account</th><th class="p-3">Month</th><th class="p-3">Amount</th><th class="p-3">Notes</th><th class="p-3"></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in form.lines" :key="index" class="border-t border-gray-100 dark:border-gray-800">
                            <td class="p-2">
                                <select v-model="line.account_id" class="min-w-64 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                    <option :value="null">Select account</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.code }} — {{ account.name }} ({{ account.account_type }})</option>
                                </select>
                            </td>
                            <td class="p-2"><select v-model="line.month_number" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option v-for="(month, i) in months" :key="month" :value="i + 1">{{ month }}</option></select></td>
                            <td class="p-2"><input v-model="line.amount" type="number" min="0" step="0.000001" class="w-40 rounded-lg border-gray-300 text-right dark:border-gray-700 dark:bg-gray-900" /></td>
                            <td class="p-2"><input v-model="line.notes" type="text" maxlength="500" class="min-w-48 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" /></td>
                            <td class="p-2 text-right"><button type="button" class="text-error-600" @click="removeLine(index)">Remove</button></td>
                        </tr>
                        <tr v-if="form.lines.length === 0"><td colspan="5" class="p-6 text-center text-gray-500">Add at least one budget line.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p v-if="form.errors.lines" class="text-sm text-error-600">{{ form.errors.lines }}</p>

        <div class="flex justify-end">
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{{ submitLabel }}</button>
        </div>
    </form>
</template>
