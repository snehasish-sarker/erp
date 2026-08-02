<script setup lang="ts">
import {
    Head,
    Link,
    router,
    useForm,
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
    ManagedUserRecord,
    UserBranchOption,
    UserFilters,
    UserPagination,
    UserRoleOption,
    UserStatus,
    UserStatusOption,
} from '@/Types/user';

defineOptions({
    layout: ErpLayout,
});

interface UserFilterForm {
    search: string;
    branch_id: number | '';
    role_id: number | '';
    status: '' | UserStatus;
    sort: UserFilters['sort'];
    direction: UserFilters['direction'];
    per_page: UserFilters['per_page'];
}

const props = defineProps<{
    users: UserPagination;
    filters: UserFilters;
    branchOptions: UserBranchOption[];
    roleOptions: UserRoleOption[];
    statusOptions: UserStatusOption[];
}>();

const { can } = useAuthorization();

const filterForm = reactive<UserFilterForm>({
    search: props.filters.search,
    branch_id: props.filters.branch_id ?? '',
    role_id: props.filters.role_id ?? '',
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const statusUser = ref<ManagedUserRecord | null>(null);
const passwordUser = ref<ManagedUserRecord | null>(null);
const deletingUserId = ref<number | null>(null);

const statusForm = useForm<{
    status: UserStatus;
}>({
    status: 'active',
});

const passwordForm = useForm<{
    password: string;
    password_confirmation: string;
}>({
    password: '',
    password_confirmation: '',
});

const hasActiveFilters: ComputedRef<boolean> = computed(
    (): boolean => filterForm.search !== ''
        || filterForm.branch_id !== ''
        || filterForm.role_id !== ''
        || filterForm.status !== '',
);

const navigate = (page = 1): void => {
    router.get(
        '/erp/users',
        {
            search: filterForm.search,
            branch_id: filterForm.branch_id,
            role_id: filterForm.role_id,
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
    filterForm.branch_id = '';
    filterForm.role_id = '';
    filterForm.status = '';
    filterForm.sort = 'name';
    filterForm.direction = 'asc';
    filterForm.per_page = 25;

    navigate();
};

const sortBy = (column: UserFilters['sort']): void => {
    if (filterForm.sort === column) {
        filterForm.direction = filterForm.direction === 'asc'
            ? 'desc'
            : 'asc';
    } else {
        filterForm.sort = column;
        filterForm.direction = 'asc';
    }

    navigate();
};

const sortIndicator = (
    column: UserFilters['sort'],
): string => {
    if (filterForm.sort !== column) {
        return '';
    }

    return filterForm.direction === 'asc'
        ? '↑'
        : '↓';
};

const statusBadgeClass = (
    status: UserStatus,
): string => {
    const classes: Record<UserStatus, string> = {
        active: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        inactive: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        suspended: 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
        archived: 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400',
    };

    return classes[status];
};

const statusLabel = (
    status: UserStatus,
): string => props.statusOptions.find(
    (option: UserStatusOption): boolean =>
        option.value === status,
)?.label ?? status;

const openStatusModal = (
    user: ManagedUserRecord,
): void => {
    statusUser.value = user;
    statusForm.status = user.status;
    statusForm.clearErrors();
};

const closeStatusModal = (): void => {
    if (statusForm.processing) {
        return;
    }

    statusUser.value = null;
    statusForm.reset();
    statusForm.clearErrors();
};

const submitStatus = (): void => {
    if (statusUser.value === null) {
        return;
    }

    statusForm.patch(
        `/erp/users/${statusUser.value.id}/status`,
        {
            preserveScroll: true,
            onSuccess: closeStatusModal,
        },
    );
};

const openPasswordModal = (
    user: ManagedUserRecord,
): void => {
    passwordUser.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
};

const closePasswordModal = (): void => {
    if (passwordForm.processing) {
        return;
    }

    passwordUser.value = null;
    passwordForm.reset();
    passwordForm.clearErrors();
};

const submitPassword = (): void => {
    if (passwordUser.value === null) {
        return;
    }

    passwordForm.patch(
        `/erp/users/${passwordUser.value.id}/password`,
        {
            preserveScroll: true,
            onSuccess: closePasswordModal,
        },
    );
};

const deleteUser = (
    user: ManagedUserRecord,
): void => {
    const confirmed = window.confirm(
        `Delete the user “${user.name}”? Their account will be archived and existing sessions will be signed out.`,
    );

    if (!confirmed) {
        return;
    }

    deletingUserId.value = user.id;

    router.delete(
        `/erp/users/${user.id}`,
        {
            preserveScroll: true,

            onError: (errors): void => {
                const firstError = Object.values(errors)[0];

                if (firstError !== undefined) {
                    window.alert(firstError);
                }
            },

            onFinish: (): void => {
                deletingUserId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Users" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Users
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage tenant accounts, branch assignments, roles,
                    statuses, and password resets.
                </p>
            </div>

            <Link
                v-if="can('users.create')"
                href="/erp/users/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Add user
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-[minmax(220px,1fr)_170px_170px_150px_110px_auto]"
                @submit.prevent="applyFilters"
            >
                <div>
                    <label
                        for="user-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="user-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Name or email"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="user-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch
                    </label>

                    <select
                        id="user-branch"
                        v-model.number="filterForm.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All branches
                        </option>

                        <option
                            v-for="branch in branchOptions"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="user-role"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Role
                    </label>

                    <select
                        id="user-role"
                        v-model.number="filterForm.role_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All roles
                        </option>

                        <option
                            v-for="role in roleOptions"
                            :key="role.id"
                            :value="role.id"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="user-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="user-status"
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
                        for="user-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="user-per-page"
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
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
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
                                    User {{ sortIndicator('name') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Branch
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Roles
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('status')"
                                >
                                    Status {{ sortIndicator('status') }}
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
                            v-for="user in users.data"
                            :key="user.id"
                            class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-400"
                                    >
                                        {{
                                            user.name
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </div>

                                    <div>
                                        <div
                                            class="flex items-center gap-2 font-medium text-gray-800 dark:text-white/90"
                                        >
                                            {{ user.name }}

                                            <span
                                                v-if="user.is_current_user"
                                                class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400"
                                            >
                                                You
                                            </span>
                                        </div>

                                        <div
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ user.email }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <template v-if="user.branch">
                                    <div
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ user.branch.name }}
                                    </div>

                                    <div
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ user.branch.code }}
                                    </div>
                                </template>

                                <span
                                    v-else
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tenant-wide
                                </span>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div
                                    class="flex max-w-sm flex-wrap gap-1.5"
                                >
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300"
                                    >
                                        {{ role.name }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusBadgeClass(user.status)"
                                >
                                    {{ statusLabel(user.status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div
                                    class="flex flex-wrap justify-end gap-2"
                                >
                                    <Link
                                        v-if="can('users.update')"
                                        :href="`/erp/users/${user.id}/edit`"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="
                                            can('users.change_status')
                                                && !user.is_current_user
                                        "
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                                        @click="openStatusModal(user)"
                                    >
                                        Status
                                    </button>

                                    <button
                                        v-if="
                                            can('users.reset_password')
                                                && !user.is_current_user
                                        "
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                                        @click="openPasswordModal(user)"
                                    >
                                        Reset password
                                    </button>

                                    <button
                                        v-if="
                                            can('users.delete')
                                                && !user.is_current_user
                                        "
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-error-300 bg-white px-3 text-sm font-medium text-error-600 shadow-theme-xs transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-error-500/40 dark:bg-gray-800 dark:text-error-400 dark:hover:bg-error-500/10"
                                        :disabled="
                                            deletingUserId === user.id
                                        "
                                        @click="deleteUser(user)"
                                    >
                                        {{
                                            deletingUserId === user.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="users.data.length === 0">
                            <td
                                colspan="5"
                                class="px-5 py-14 text-center sm:px-6"
                            >
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    No users found
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Adjust the filters or create the first
                                    user.
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
                    Showing {{ users.meta.from ?? 0 }}–{{
                        users.meta.to ?? 0
                    }}
                    of {{ users.meta.total }} users
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        :disabled="users.meta.current_page <= 1"
                        @click="
                            navigate(
                                users.meta.current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-600 dark:text-gray-400"
                    >
                        Page {{ users.meta.current_page }} of
                        {{ users.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        :disabled="
                            users.meta.current_page
                                >= users.meta.last_page
                        "
                        @click="
                            navigate(
                                users.meta.current_page + 1,
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="statusUser"
        class="fixed inset-0 z-[99999] flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
        @click.self="closeStatusModal"
    >
        <form
            class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900"
            @submit.prevent="submitStatus"
        >
            <div
                class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"
            >
                <h2
                    class="text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    Change user status
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Update the account status for {{ statusUser.name }}.
                </p>
            </div>

            <div class="p-5">
                <label
                    for="status-modal-value"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Status
                </label>

                <select
                    id="status-modal-value"
                    v-model="statusForm.status"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    :class="statusForm.errors.status
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
                    v-if="statusForm.errors.status"
                    class="mt-1.5 text-sm text-error-500"
                >
                    {{ statusForm.errors.status }}
                </p>

                <p
                    class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400"
                >
                    Non-active users cannot sign in. Their existing
                    sessions will be removed immediately.
                </p>
            </div>

            <div
                class="flex justify-end gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800"
            >
                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                    :disabled="statusForm.processing"
                    @click="closeStatusModal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-60"
                    :disabled="statusForm.processing"
                >
                    {{
                        statusForm.processing
                            ? 'Saving...'
                            : 'Save status'
                    }}
                </button>
            </div>
        </form>
    </div>

    <div
        v-if="passwordUser"
        class="fixed inset-0 z-[99999] flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
        @click.self="closePasswordModal"
    >
        <form
            class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900"
            @submit.prevent="submitPassword"
        >
            <div
                class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"
            >
                <h2
                    class="text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    Reset password
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Set a new password for {{ passwordUser.name }}.
                </p>
            </div>

            <div class="space-y-5 p-5">
                <div>
                    <label
                        for="reset-password"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        New password
                    </label>

                    <input
                        id="reset-password"
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="passwordForm.errors.password
                            ? 'border-error-500'
                            : ''"
                    >

                    <p
                        v-if="passwordForm.errors.password"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ passwordForm.errors.password }}
                    </p>
                </div>

                <div>
                    <label
                        for="reset-password-confirmation"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Confirm password
                    </label>

                    <input
                        id="reset-password-confirmation"
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                </div>

                <p
                    class="text-xs leading-5 text-gray-500 dark:text-gray-400"
                >
                    Use at least 12 characters with upper- and lowercase
                    letters and a number. Existing sessions will be
                    signed out.
                </p>
            </div>

            <div
                class="flex justify-end gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800"
            >
                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                    :disabled="passwordForm.processing"
                    @click="closePasswordModal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-60"
                    :disabled="passwordForm.processing"
                >
                    {{
                        passwordForm.processing
                            ? 'Resetting...'
                            : 'Reset password'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>