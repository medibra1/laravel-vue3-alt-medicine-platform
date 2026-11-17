<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppModulePicker from '@/Components/App/AppModulePicker.vue';
import type { AppModule } from '@/types';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Center {
    id: number;
    name: string;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: 'super_admin' | 'admin' | 'manager' | 'practitioner' | null;
    center_id: number | null;
    center: Center | null;
    center_ids: number[];
    centers: Center[];
    is_active: boolean;
    invitation_pending: boolean;
    modules: AppModule[] | null;
    can_manage_modules: boolean;
    created_at: string;
}

const props = defineProps<{
    users: {
        data: UserRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    centers: Center[];
    allModules: AppModule[];
}>();

const { t } = useI18n();

const isSuperAdmin = computed(() => Boolean((usePage().props.auth as { is_super_admin?: boolean }).is_super_admin));

const roleOptions = computed(() =>
    isSuperAdmin.value
        ? [
              { value: 'manager', label: t('shell.roles.manager') },
              { value: 'admin', label: t('shell.roles.admin') },
          ]
        : [{ value: 'manager', label: t('shell.roles.manager') }],
);

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? '-created_at');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.users.index'),
        { filter: { ...search }, sort: currentSort.value, ...extra },
        { preserveState: true, replace: true },
    );
}

function onPage(page: number) {
    reload({ page });
}

function onSort(event: AppDataTableSortEvent) {
    if (!event.sortField) {
        return;
    }

    currentSort.value =
        event.sortOrder === -1 ? `-${event.sortField}` : `${event.sortField}`;
    reload();
}

const columns = computed<AppDataTableColumn[]>(() => [
    { field: 'name', header: t('common.name'), sortable: true },
    { field: 'email', header: t('common.email'), sortable: true },
    { field: 'role', header: t('users.role') },
    { field: 'centers', header: t('common.centers') },
    { field: 'modules', header: t('users.modules') },
    { field: 'status', header: t('common.status') },
    { field: 'actions', header: '' },
]);

function statusLabel(user: UserRow): string {
    if (!user.is_active) {
        return t('users.status.disabled');
    }

    return user.invitation_pending ? t('users.status.pending') : t('users.status.active');
}

function statusColor(user: UserRow): string {
    if (!user.is_active) {
        return 'error';
    }

    return user.invitation_pending ? 'warning' : 'success';
}

const isCreating = ref(false);
const editingUser = ref<UserRow | null>(null);

const createForm = useForm({
    name: '',
    email: '',
    role: 'manager' as 'manager' | 'admin',
    // A manager can manage several centers at once (extended
    // 2026-08-26 from the original single-center design).
    center_ids: [] as number[],
    creation_mode: 'invite' as 'direct' | 'invite',
    password: '',
    password_confirmation: '',
    // null = every module of the center.
    modules: null as AppModule[] | null,
});

function openCreate() {
    createForm.reset();
    createForm.role = 'manager';
    createForm.creation_mode = 'invite';
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.users.store'), {
        onSuccess: () => {
            isCreating.value = false;
            // Inertia re-bases reset() on the last successful submit.
            createForm.reset();
        },
    });
}

const editForm = useForm({
    name: '',
    email: '',
    role: 'manager' as 'manager',
    center_ids: [] as number[],
    modules: null as AppModule[] | null,
});

function openEdit(user: UserRow) {
    editingUser.value = user;
    editForm.reset();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = 'manager';
    editForm.center_ids = [...user.center_ids];
    editForm.modules = user.modules ? [...user.modules] : null;
}

function submitEdit() {
    if (!editingUser.value) {
        return;
    }

    editForm.put(
        route('admin.users.update', editingUser.value.id),
        { onSuccess: () => (editingUser.value = null) },
    );
}

function destroy(user: UserRow) {
    if (!confirm(t('users.confirmDeactivate', { name: user.name }))) {
        return;
    }

    router.delete(route('admin.users.destroy', user.id));
}

// Modules of a practitioner account (managers edit theirs in the edit dialog).
const modulesUser = ref<UserRow | null>(null);
const modulesForm = useForm({ modules: null as AppModule[] | null });

function openModules(user: UserRow) {
    modulesUser.value = user;
    modulesForm.modules = user.modules ? [...user.modules] : null;
}

function submitModules() {
    if (!modulesUser.value) {
        return;
    }

    modulesForm.put(route('admin.users.modules.update', modulesUser.value.id), {
        onSuccess: () => (modulesUser.value = null),
    });
}
</script>

<template>
    <Head :title="t('users.title')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="t('users.title')" :breadcrumbs="[{ label: t('shell.nav.dashboard'), href: route('dashboard') }, { label: t('users.title') }]">
            <template #actions>
                <AppButton :label="t('users.new')" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        :label="t('users.searchLabel')"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton :label="t('common.filter')" severity="secondary" @click="reload()" />
                </v-card-text>
            </AppCard>

            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="users.data"
                    :columns="columns"
                    :rows="users.per_page"
                    :total-records="users.total"
                    :page="users.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-role="{ item }">
                        <v-chip size="small" variant="tonal">{{ item.role ? t(`shell.roles.${item.role}`) : '—' }}</v-chip>
                    </template>
                    <template #column-centers="{ item }">
                        <div v-if="item.role === 'manager' || item.role === 'practitioner'" class="d-flex flex-wrap ga-1">
                            <v-chip v-for="center in item.centers" :key="center.id" size="small" variant="tonal">
                                {{ center.name }}
                            </v-chip>
                            <span v-if="!item.centers.length">—</span>
                        </div>
                        <span v-else>—</span>
                    </template>
                    <template #column-modules="{ item }">
                        <span v-if="item.role !== 'manager' && item.role !== 'practitioner'" class="text-medium-emphasis">—</span>
                        <v-chip v-else-if="!item.modules" size="small" variant="tonal">{{ t('users.allModules') }}</v-chip>
                        <div v-else class="d-flex flex-wrap ga-1">
                            <v-chip v-for="module in item.modules" :key="module" size="small" color="primary" variant="tonal">
                                {{ t(`modules.items.${module}.name`) }}
                            </v-chip>
                        </div>
                    </template>
                    <template #column-status="{ item }">
                        <v-chip size="small" :color="statusColor(item)" variant="tonal">{{ statusLabel(item) }}</v-chip>
                    </template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <AppButton
                                v-if="item.role === 'manager'"
                                :label="t('common.edit')"
                                severity="secondary"
                                size="small"
                                @click="openEdit(item)"
                            />
                            <AppButton
                                v-else-if="item.can_manage_modules && item.role === 'practitioner'"
                                :label="t('users.modules')"
                                severity="secondary"
                                size="small"
                                @click="openModules(item)"
                            />
                            <AppButton
                                v-if="(item.role === 'manager' || item.role === 'practitioner') && item.is_active"
                                :label="t('users.deactivate')"
                                severity="danger"
                                size="small"
                                @click="destroy(item)"
                            />
                        </div>
                    </template>
                </AppDataTable>
            </AppCard>
        </div>

        <AppDialog v-model:visible="isCreating" :header="t('users.new')" max-width="640px">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.name"
                    :label="t('common.name')"
                    :error="createForm.errors.name"
                />

                <AppInputText
                    v-model="createForm.email"
                    :label="t('common.email')"
                    type="email"
                    :error="createForm.errors.email"
                />

                <AppSelect
                    v-model="createForm.role"
                    :options="roleOptions"
                    option-label="label"
                    option-value="value"
                    :label="t('users.role')"
                    :error="createForm.errors.role"
                />

                <AppSelect
                    v-if="createForm.role === 'manager'"
                    v-model="createForm.center_ids"
                    :options="centers"
                    option-label="name"
                    option-value="id"
                    :label="t('users.managedCenters')"
                    :placeholder="t('users.chooseCenters')"
                    multiple
                    :error="createForm.errors.center_ids"
                />


                <div v-if="createForm.role === 'manager'">
                    <AppCheckbox
                        :model-value="createForm.modules !== null"
                        :label="t('users.restrict')"
                        @update:model-value="createForm.modules = $event ? [...allModules] : null"
                    />
                    <p class="text-caption text-medium-emphasis mb-3">{{ t('users.restrictHint') }}</p>
                    <AppModulePicker v-if="createForm.modules !== null" v-model="createForm.modules" :available="allModules" />
                </div>

                <AppSelect
                    v-model="createForm.creation_mode"
                    :options="[
                        { value: 'invite', label: t('users.modeInvite') },
                        { value: 'direct', label: t('users.modeDirect') },
                    ]"
                    option-label="label"
                    option-value="value"
                    :label="t('users.creationMode')"
                />

                <template v-if="createForm.creation_mode === 'direct'">
                    <AppInputText
                        v-model="createForm.password"
                        :label="t('auth.password')"
                        type="password"
                        :error="createForm.errors.password"
                    />
                    <AppInputText
                        v-model="createForm.password_confirmation"
                        :label="t('auth.passwordConfirm')"
                        type="password"
                    />
                </template>

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        :label="t('common.cancel')"
                        severity="secondary"
                        @click="isCreating = false"
                    />
                    <AppButton type="submit" :label="t('common.create')" :loading="createForm.processing" />
                </div>
            </form>
        </AppDialog>

        <AppDialog
            :visible="editingUser !== null"
            :header="t('users.edit')"
            max-width="640px"
            @update:visible="editingUser = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.name"
                    :label="t('common.name')"
                    :error="editForm.errors.name"
                />

                <AppInputText
                    v-model="editForm.email"
                    :label="t('common.email')"
                    type="email"
                    :error="editForm.errors.email"
                />

                <AppSelect
                    v-model="editForm.center_ids"
                    :options="centers"
                    option-label="name"
                    option-value="id"
                    :label="t('users.managedCenters')"
                    multiple
                    :error="editForm.errors.center_ids"
                />

                <div>
                    <AppCheckbox
                        :model-value="editForm.modules !== null"
                        :label="t('users.restrict')"
                        @update:model-value="editForm.modules = $event ? [...allModules] : null"
                    />
                    <p class="text-caption text-medium-emphasis mb-3">{{ t('users.restrictHint') }}</p>
                    <AppModulePicker v-if="editForm.modules !== null" v-model="editForm.modules" :available="allModules" />
                </div>

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        :label="t('common.cancel')"
                        severity="secondary"
                        @click="editingUser = null"
                    />
                    <AppButton type="submit" :label="t('common.save')" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>

        <AppDialog
            :visible="modulesUser !== null"
            :header="modulesUser ? t('users.modulesFor', { name: modulesUser.name }) : ''"
            max-width="640px"
            @update:visible="modulesUser = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitModules">
                <div>
                    <AppCheckbox
                        :model-value="modulesForm.modules !== null"
                        :label="t('users.restrict')"
                        @update:model-value="modulesForm.modules = $event ? [...allModules] : null"
                    />
                    <p class="text-caption text-medium-emphasis mb-3">{{ t('users.restrictHint') }}</p>
                    <AppModulePicker v-if="modulesForm.modules !== null" v-model="modulesForm.modules" :available="allModules" />
                </div>

                <div class="d-flex justify-end ga-2">
                    <AppButton type="button" :label="t('common.cancel')" severity="secondary" @click="modulesUser = null" />
                    <AppButton type="submit" :label="t('common.save')" :loading="modulesForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
