<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    watch,
} from 'vue';
import type { ComputedRef } from 'vue';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastData {
    type: ToastType;
    message: string;
    code?: string;
}

const page = usePage();
const isMounted = ref(false);
const isVisible = ref(false);
let dismissTimer: ReturnType<typeof setTimeout> | null = null;

const toast: ComputedRef<ToastData | undefined> = computed(
    (): ToastData | undefined => page.flash.toast,
);

const toastClasses: ComputedRef<string> = computed((): string => {
    switch (toast.value?.type) {
        case 'success':
            return [
                'border-success-500/30',
                'bg-success-50',
                'text-success-700',
                'dark:border-success-500/20',
                'dark:bg-success-500/15',
                'dark:text-success-400',
            ].join(' ');

        case 'error':
            return [
                'border-error-500/30',
                'bg-error-50',
                'text-error-700',
                'dark:border-error-500/20',
                'dark:bg-error-500/15',
                'dark:text-error-400',
            ].join(' ');

        case 'warning':
            return [
                'border-warning-500/30',
                'bg-warning-50',
                'text-warning-700',
                'dark:border-warning-500/20',
                'dark:bg-warning-500/15',
                'dark:text-warning-400',
            ].join(' ');

        default:
            return [
                'border-blue-light-500/30',
                'bg-blue-light-50',
                'text-blue-light-700',
                'dark:border-blue-light-500/20',
                'dark:bg-blue-light-500/15',
                'dark:text-blue-light-400',
            ].join(' ');
    }
});

const closeToast = (): void => {
    isVisible.value = false;

    if (dismissTimer !== null) {
        clearTimeout(dismissTimer);
        dismissTimer = null;
    }
};

const showToast = (): void => {
    if (toast.value === undefined) {
        closeToast();

        return;
    }

    if (dismissTimer !== null) {
        clearTimeout(dismissTimer);
    }

    isVisible.value = true;

    dismissTimer = setTimeout((): void => {
        isVisible.value = false;
        dismissTimer = null;
    }, 5000);
};

watch(
    toast,
    (): void => {
        if (isMounted.value) {
            showToast();
        }
    },
    {
        deep: true,
    },
);

onMounted((): void => {
    isMounted.value = true;
    showToast();
});

onUnmounted((): void => {
    if (dismissTimer !== null) {
        clearTimeout(dismissTimer);
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-x-4 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-4 opacity-0"
        >
            <div
                v-if="isMounted && isVisible && toast"
                class="fixed top-5 right-5 z-[100000] w-[calc(100%-2.5rem)] max-w-sm"
                role="alert"
                aria-live="polite"
            >
                <div
                    class="flex items-start gap-3 rounded-xl border p-4 shadow-theme-lg"
                    :class="toastClasses"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">
                            {{ toast.message }}
                        </p>

                        <p
                            v-if="toast.code"
                            class="mt-1 text-xs opacity-75"
                        >
                            Error code: {{ toast.code }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 rounded-md p-1 opacity-70 transition hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current"
                        aria-label="Close notification"
                        @click="closeToast"
                    >
                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 18 18"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path
                                d="M4.5 4.5L13.5 13.5M13.5 4.5L4.5 13.5"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>