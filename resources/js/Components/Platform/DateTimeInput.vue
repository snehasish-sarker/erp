<script setup lang="ts">
import {
    ref,
    watch,
} from 'vue';

interface Props {
    modelValue: string;
    label: string;
    error?: string;
    hint?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    error: '',
    hint: '',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const datePart = ref('');
const timePart = ref('');

const splitValue = (value: string): { date: string; time: string } => {
    if (value === '') {
        return {
            date: '',
            time: '',
        };
    }

    const [date = '', rawTime = ''] = value.split('T');

    return {
        date,
        time: rawTime.slice(0, 5),
    };
};

watch(
    () => props.modelValue,
    (value): void => {
        const parsed = splitValue(value);

        if (datePart.value !== parsed.date) {
            datePart.value = parsed.date;
        }

        if (timePart.value !== parsed.time) {
            timePart.value = parsed.time;
        }
    },
    { immediate: true },
);

watch(
    [datePart, timePart],
    ([date, time]): void => {
        if (date === '' || time === '') {
            return;
        }

        const nextValue = `${date}T${time}`;

        if (nextValue !== props.modelValue) {
            emit('update:modelValue', nextValue);
        }
    },
);

const clearValue = (): void => {
    datePart.value = '';
    timePart.value = '';

    if (props.modelValue !== '') {
        emit('update:modelValue', '');
    }
};
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between gap-3">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ label }}
            </label>

            <button
                v-if="modelValue !== '' || datePart !== '' || timePart !== ''"
                type="button"
                :disabled="disabled"
                class="text-xs font-medium text-gray-500 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-60 dark:text-gray-400 dark:hover:text-gray-200"
                @click="clearValue"
            >
                Clear
            </button>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)_9rem] gap-2">
            <div>
                <span class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                    Date
                </span>
                <input
                    v-model="datePart"
                    type="date"
                    :disabled="disabled"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
            </div>

            <div>
                <span class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                    Time
                </span>
                <input
                    v-model="timePart"
                    type="time"
                    step="60"
                    :disabled="disabled"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
            </div>
        </div>

        <p
            v-if="error"
            class="mt-1 text-sm text-error-500"
        >
            {{ error }}
        </p>

        <p
            v-if="hint"
            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
        >
            {{ hint }}
        </p>
    </div>
</template>
