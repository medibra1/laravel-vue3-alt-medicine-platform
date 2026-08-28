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
import { computed, reactive, ref } from 'vue';

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
}>();

const isSuperAdmin = computed(() => Boolean((usePage().props.auth as { is_super_admin?: boolean }).is_super_admin));

const roleOptions = computed(() =>
    isSuperAdmin.value
        ? [
              { value: 'manager', label: 'Manager' },
              { value: 'admin', label: 'Admin' },
          ]
        : [{ value: 'manager', label: 'Manager' }],
);

const roleLabels: Record<string, string> = {
    super_admin: 'Super admin',
    admin: 'Admin',
    manager: 'Manager',
    practitioner: 'Praticien',
};

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

const columns: AppDataTableColumn[] = [
    { field: 'name', header: 'Nom', sortable: true },
    { field: 'email', header: 'Email', sortable: true },
    { field: 'role', header: 'Rôle' },
    { field: 'centers', header: 'Centres' },
    { field: 'status', header: 'Statut' },
    { field: 'actions', header: 'Actions' },
];

function statusLabel(user: UserRow): string {
    if (!user.is_active) {
        return 'Désactivé';
    }

    return user.invitation_pending ? 'En attente' : 'Actif';
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
        },
    });
}

const editForm = useForm({
    name: '',
    email: '',
    role: 'manager' as 'manager',
    center_ids: [] as number[],
});

function openEdit(user: UserRow) {
    editingUser.value = user;
    editForm.reset();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = 'manager';
    editForm.center_ids = [...user.center_ids];
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
    if (!confirm(`Désactiver le compte de ${user.name} ?`)) {
        return;
    }

    router.delete(route('admin.users.destroy', user.id));
}
</script>

<template>
    <Head title="Utilisateurs" />

    <AuthenticatedLayout>
        <AppPageHeader title="Utilisateurs" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Utilisateurs' }]">
            <template #actions>
                <AppButton label="Nouvel utilisateur" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        label="Rechercher (nom, email)"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton label="Filtrer" severity="secondary" @click="reload()" />
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
                        <v-chip size="small" variant="tonal">{{ item.role ? roleLabels[item.role] : '—' }}</v-chip>
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
                    <template #column-status="{ item }">
                        <v-chip size="small" :color="statusColor(item)" variant="tonal">{{ statusLabel(item) }}</v-chip>
                    </template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <AppButton
                                v-if="item.role === 'manager'"
                                label="Modifier"
                                severity="secondary"
                                size="small"
                                @click="openEdit(item)"
                            />
                            <AppButton
                                v-if="(item.role === 'manager' || item.role === 'practitioner') && item.is_active"
                                label="Désactiver"
                                severity="danger"
                                size="small"
                                @click="destroy(item)"
                            />
                        </div>
                    </template>
                </AppDataTable>
            </AppCard>
        </div>

        <AppDialog v-model:visible="isCreating" header="Nouvel utilisateur">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.name"
                    label="Nom"
                    :error="createForm.errors.name"
                />

                <AppInputText
                    v-model="createForm.email"
                    label="Email"
                    type="email"
                    :error="createForm.errors.email"
                />

                <AppSelect
                    v-model="createForm.role"
                    :options="roleOptions"
                    option-label="label"
                    option-value="value"
                    label="Rôle"
                    :error="createForm.errors.role"
                />

                <AppSelect
                    v-if="createForm.role === 'manager'"
                    v-model="createForm.center_ids"
                    :options="centers"
                    option-label="name"
                    option-value="id"
                    label="Centres gérés"
                    placeholder="Choisir un ou plusieurs centres"
                    multiple
                    :error="createForm.errors.center_ids"
                />

                <AppSelect
                    v-model="createForm.creation_mode"
                    :options="[
                        { value: 'invite', label: 'Envoyer une invitation par email' },
                        { value: 'direct', label: 'Définir un mot de passe directement' },
                    ]"
                    option-label="label"
                    option-value="value"
                    label="Mode de création"
                />

                <template v-if="createForm.creation_mode === 'direct'">
                    <AppInputText
                        v-model="createForm.password"
                        label="Mot de passe"
                        type="password"
                        :error="createForm.errors.password"
                    />
                    <AppInputText
                        v-model="createForm.password_confirmation"
                        label="Confirmer le mot de passe"
                        type="password"
                    />
                </template>

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="isCreating = false"
                    />
                    <AppButton type="submit" label="Créer" :loading="createForm.processing" />
                </div>
            </form>
        </AppDialog>

        <AppDialog
            :visible="editingUser !== null"
            header="Modifier l'utilisateur"
            @update:visible="editingUser = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.name"
                    label="Nom"
                    :error="editForm.errors.name"
                />

                <AppInputText
                    v-model="editForm.email"
                    label="Email"
                    type="email"
                    :error="editForm.errors.email"
                />

                <AppSelect
                    v-model="editForm.center_ids"
                    :options="centers"
                    option-label="name"
                    option-value="id"
                    label="Centres gérés"
                    multiple
                    :error="editForm.errors.center_ids"
                />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingUser = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
