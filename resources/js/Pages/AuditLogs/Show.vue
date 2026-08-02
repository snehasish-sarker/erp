<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    AuditJsonValue,
    AuditLogDetailRecord,
} from '@/Types/audit-log';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    auditLog: AuditLogDetailRecord;
    tenantTimezone: string;
}>();

const changedKeys: ComputedRef<string[]> = computed(
    (): string[] => {
        const oldKeys = Object.keys(
            props.auditLog.old_values ?? {},
        );

        const newKeys = Object.keys(
            props.auditLog.new_values ?? {},
        );

        return Array.from(
            new Set([
                ...oldKeys,
                ...newKeys,
            ]),
        ).sort();
    },
);

const formatDate = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-US',
        {
            dateStyle: 'full',
            timeStyle: 'long',
            timeZone: props.tenantTimezone,
        },
    ).format(new Date(value));
};

const formatEvent = (
    event: string,
): string => event
    .replace(/[._-]+/g, ' ')
    .replace(
        /\b\w/g,
        (character: string): string =>
            character.toUpperCase(),
    );

const eventBadgeClass = (
    event: string,
): string => {
    if (
        event.includes('delete')
        || event.includes('reverse')
        || event.includes('fail')
    ) {
        return 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400';
    }

    if (
        event.includes('create')
        || event.includes('restore')
        || event.includes('login')
        || event.includes('approve')
        || event.includes('post')
    ) {
        return 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400';
    }

    if (
        event.includes('update')
        || event.includes('password')
        || event.includes('role')
        || event.includes('permission')
    ) {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
    }

    return 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400';
};

const oldValue = (
    key: string,
): AuditJsonValue | undefined =>
    props.auditLog.old_values?.[key];

const newValue = (
    key: string,
): AuditJsonValue | undefined =>
    props.auditLog.new_values?.[key];

const formatValue = (
    value: AuditJsonValue | undefined,
): string => {
    if (value === undefined) {
        return '—';
    }

    if (value === null) {
        return 'null';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (
        typeof value === 'number'
        || typeof value === 'boolean'
    ) {
        return String(value);
    }

    return JSON.stringify(
        value,
        null,
        2,
    );
};

const formatJson = (
    value: Record<string, AuditJsonValue> | null,
): string => value === null
    ? 'No metadata recorded.'
    : JSON.stringify(
        value,
        null,
        2,
    );
</script>

<template>
    <Head :title="`Audit Log #${auditLog.id}`" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                    >
                        Audit Log #{{ auditLog.id }}
                    </h1>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="eventBadgeClass(auditLog.event)"
                    >
                        {{ formatEvent(auditLog.event) }}
                    </span>
                </div>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ formatDate(auditLog.created_at) }}
                    · {{ tenantTimezone }}
                </p>
            </div>

            <Link
                href="/erp/audit-logs"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
            >
                Back to audit logs
            </Link>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <h2
                    class="text-base font-semibold text-gray-800 dark:text-white/90"
                >
                    Actor
                </h2>

                <dl class="mt-4 space-y-4">
                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Name
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90"
                        >
                            {{ auditLog.actor_name ?? 'System' }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Email
                        </dt>

                        <dd
                            class="mt-1 break-all text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.actor_email ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            User ID
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.actor_user_id ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <h2
                    class="text-base font-semibold text-gray-800 dark:text-white/90"
                >
                    Subject
                </h2>

                <dl class="mt-4 space-y-4">
                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Label
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90"
                        >
                            {{
                                auditLog.subject_label
                                    ?? auditLog.subject_type_label
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Type
                        </dt>

                        <dd
                            class="mt-1 break-all text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.subject_type }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Subject ID
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.subject_id ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <h2
                    class="text-base font-semibold text-gray-800 dark:text-white/90"
                >
                    Request
                </h2>

                <dl class="mt-4 space-y-4">
                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Route
                        </dt>

                        <dd
                            class="mt-1 break-all text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.route_name ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Method and IP
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.http_method ?? '—' }}
                            · {{ auditLog.ip_address ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Request ID
                        </dt>

                        <dd
                            class="mt-1 break-all font-mono text-xs text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.request_id ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    Changed values
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Historical values captured before and after the event.
                </p>
            </div>

            <div
                v-if="changedKeys.length > 0"
                class="overflow-x-auto"
            >
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th
                                class="w-48 px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Field
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Before
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                After
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="key in changedKeys"
                            :key="key"
                        >
                            <td
                                class="px-5 py-4 align-top font-mono text-sm font-medium text-gray-800 dark:text-white/90 sm:px-6"
                            >
                                {{ key }}
                            </td>

                            <td
                                class="max-w-xl px-5 py-4 align-top sm:px-6"
                            >
                                <pre
                                    class="whitespace-pre-wrap break-words font-sans text-sm text-gray-600 dark:text-gray-400"
                                >{{ formatValue(oldValue(key)) }}</pre>
                            </td>

                            <td
                                class="max-w-xl px-5 py-4 align-top sm:px-6"
                            >
                                <pre
                                    class="whitespace-pre-wrap break-words font-sans text-sm text-gray-800 dark:text-gray-200"
                                >{{ formatValue(newValue(key)) }}</pre>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="px-5 py-10 text-center sm:px-6"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    This event did not record attribute-level changes.
                </p>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section
                class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div
                    class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
                >
                    <h2
                        class="text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        Metadata
                    </h2>
                </div>

                <pre
                    class="max-h-[500px] overflow-auto whitespace-pre-wrap break-words p-5 font-mono text-xs leading-6 text-gray-700 dark:text-gray-300 sm:p-6"
                >{{ formatJson(auditLog.metadata) }}</pre>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div
                    class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
                >
                    <h2
                        class="text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        HTTP context
                    </h2>
                </div>

                <dl class="space-y-5 p-5 sm:p-6">
                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            URL
                        </dt>

                        <dd
                            class="mt-1 break-all text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.url ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            User agent
                        </dt>

                        <dd
                            class="mt-1 break-words text-sm leading-6 text-gray-700 dark:text-gray-300"
                        >
                            {{ auditLog.user_agent ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
</template>