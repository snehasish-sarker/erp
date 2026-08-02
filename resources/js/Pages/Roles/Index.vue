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
} from 'vue';
import type { ComputedRef } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    RoleFilters,
    RoleListRecord,
    RolePagination,
    RoleSort,
    RoleTypeFilter,
} from '@/Types/role';

defineOptions({
    layout: ErpLayout,
});

interface RoleFilterForm {
    search: string;
    type: RoleTypeFilter;
    sort: RoleSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

const props = defineProps<{
    roles: RolePagination;
    filters: RoleFilters;
}>();

const { can, canAny } = useAuthorization();

const filterForm = reactive<RoleFilterForm>({
    search: props.filters.search,
    type: props.filters.type,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const deletingRoleId = ref<number | null>(null);

const hasActiveFilters: ComputedRef<boolean> = computed(
    (): boolean =>
        filterForm.search !== ''
        || filterForm.type !== '',
);

const canOpenRole: ComputedRef<boolean> = computed(
    (): boolean => canAny([
        'roles.view',
        'roles.update',
        'roles.assign_permissions',
    ]),
);

const navigate = (page = 1): void => {
    router.get(
        '/erp/roles',
        {
            search: filterForm.search,
            type: filterForm.type,
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
    filterForm.type = '';
    filterForm.sort = 'name';
    filterForm.direction = 'asc';
    filterForm.per_page = 25;

    navigate();
};

const sortBy = (column: RoleSort): void => {
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
    column: RoleSort,
): string => {
    if (filterForm.sort !== column) {
        return '';
    }

    return filterForm.direction === 'asc'
        ? '↑'
        : '↓';
};

const formatDate = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-US',
        {
            dateStyle: 'medium',
        },
    ).format(new Date(value));
};

const deleteRole = (
    role: RoleListRecord,
): void => {
    const confirmed = window.confirm(
        `Delete the role “${role.name}”? The role must not be assigned to any users.`,
    );

    if (!confirmed) {
        return;
    }

    deletingRoleId.value = role.id;

    router.delete(
        `/erp/roles/${role.id}`,
        {
            preserveScroll: true,

            onError: (errors): void => {
                const firstError = Object.values(errors)[0];

                if (firstError !== undefined) {
                    window.alert(firstError);
                }
            },

            onFinish: (): void => {
                deletingRoleId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Roles" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Roles
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage tenant roles and the permissions assigned to
                    each role.
                </p>
            </div>

            <Link
                v-if="can('roles.create')"
                href="/erp/roles/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Add role
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-[minmax(240px,1fr)_180px_120px_auto]"
                @submit.prevent="applyFilters"
            >
                <div>
                    <label
                        for="role-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="role-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Search role name"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="role-type"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Role type
                    </label>

                    <select
                        id="role-type"
                        v-model="filterForm.type"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All roles
                        </option>

                        <option value="system">
                            System roles
                        </option>

                        <option value="custom">
                            Custom roles
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="role-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="role-per-page"
                        v-model.number="filterForm.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="inline-flex h-11 flex-1 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
                    >
                        Apply
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
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
                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('name')"
                                >
                                    Role
                                    {{ sortIndicator('name') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('users_count')"
                                >
                                    Users
                                    {{ sortIndicator('users_count') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="
                                        sortBy(
                                            'permissions_count',
                                        )
                                    "
                                >
                                    Permissions
                                    {{
                                        sortIndicator(
                                            'permissions_count',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('created_at')"
                                >
                                    Created
                                    {{
                                        sortIndicator(
                                            'created_at',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="role in roles.data"
                            :key="role.id"
                            class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="px-5 py-4 sm:px-6">
                                <div
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ role.name }}
                                </div>

                                <div class="mt-1">
                                    <span
                                        v-if="role.is_system"
                                        class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400"
                                    >
                                        System
                                    </span>

                                    <span
                                        v-else
                                        class="inline-flex rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400"
                                    >
                                        Custom
                                    </span>
                                </div>
                            </td>

                            <td
                                class="px-5 py-4 text-sm font-medium text-gray-700 dark:text-gray-300 sm:px-6"
                            >
                                {{ role.users_count }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm font-medium text-gray-700 dark:text-gray-300 sm:px-6"
                            >
                                {{ role.permissions_count }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400 sm:px-6"
                            >
                                {{ formatDate(role.created_at) }}
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        v-if="canOpenRole"
                                        :href="`/erp/roles/${role.id}/edit`"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        Manage
                                    </Link>

                                    <button
                                        v-if="
                                            can('roles.delete')
                                            && !role.is_system
                                        "
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-error-300 bg-white px-3 text-sm font-medium text-error-600 shadow-theme-xs transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-error-500/40 dark:bg-gray-800 dark:text-error-400"
                                        :disabled="
                                            deletingRoleId
                                            === role.id
                                        "
                                        @click="deleteRole(role)"
                                    >
                                        {{
                                            deletingRoleId
                                                === role.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="roles.data.length === 0">
                            <td
                                colspan="5"
                                class="px-5 py-14 text-center sm:px-6"
                            >
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    No roles found
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Adjust the filters or create a custom
                                    role.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ roles.meta.from ?? 0 }}–{{
                        roles.meta.to ?? 0
                    }}
                    of {{ roles.meta.total }} roles
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                        :disabled="roles.meta.current_page <= 1"
                        @click="
                            navigate(
                                roles.meta.current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-600 dark:text-gray-400"
                    >
                        Page {{ roles.meta.current_page }} of
                        {{ roles.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                        :disabled="
                            roles.meta.current_page
                            >= roles.meta.last_page
                        "
                        @click="
                            navigate(
                                roles.meta.current_page + 1,
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