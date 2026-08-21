<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppTranslatableInput from '@/Components/App/AppTranslatableInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface Zone {
    id: number;
    code: string;
    name: { fr: string; en: string };
    order: number;
    active: boolean;
}

const props = defineProps<{
    zones: {
        data: Zone[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? 'order');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.zones.index'),
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
    { field: 'code', header: 'Code', sortable: true },
    { field: 'name', header: 'Nom', sortable: true },
    { field: 'order', header: 'Ordre', sortable: true },
    { field: 'active', header: 'Actif' },
    { field: 'actions', header: 'Actions' },
];

function zoneName(zone: Zone): string {
    return zone.name.fr;
}

const isCreating = ref(false);
const editingZone = ref<Zone | null>(null);

const createForm = useForm({
    code: '',
    name: { fr: '', en: '' },
    order: 0 as number | null,
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.name = { fr: '', en: '' };
    createForm.order = 0;
    createForm.active = true;
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.zones.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    code: '',
    name: { fr: '', en: '' },
    order: 0 as number | null,
    active: true,
});

function openEdit(zone: Zone) {
    editingZone.value = zone;
    editForm.reset();
    editForm.code = zone.code;
    editForm.name = { fr: zone.name.fr, en: zone.name.en };
    editForm.order = zone.order;
    editForm.active = zone.active;
}

function submitEdit() {
    if (!editingZone.value) {
        return;
    }

    editForm.put(
        route('admin.zones.update', editingZone.value.id),
        { onSuccess: () => (editingZone.value = null) },
    );
}

function destroy(zone: Zone) {
    if (!confirm(`Supprimer la zone ${zoneName(zone)} ?`)) {
        return;
    }

    router.delete(route('admin.zones.destroy', zone.id));
}
</script>

<template>
    <Head title="Zones" />

    <AuthenticatedLayout>
        <AppPageHeader title="Zones" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Zones' }]">
            <template #actions>
                <AppButton label="Nouvelle zone" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        label="Rechercher (nom, code)"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton label="Filtrer" severity="secondary" @click="reload()" />
                </v-card-text>
            </AppCard>

            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="zones.data"
                    :columns="columns"
                    :rows="zones.per_page"
                    :total-records="zones.total"
                    :page="zones.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-name="{ item }">{{ zoneName(item) }}</template>
                    <template #column-active="{ item }">{{ item.active ? 'Oui' : 'Non' }}</template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <AppButton
                                label="Modifier"
                                severity="secondary"
                                size="small"
                                @click="openEdit(item)"
                            />
                            <AppButton
                                label="Supprimer"
                                severity="danger"
                                size="small"
                                @click="destroy(item)"
                            />
                        </div>
                    </template>
                </AppDataTable>
            </AppCard>
        </div>

        <AppDialog v-model:visible="isCreating" header="Nouvelle zone">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.code"
                    label="Code"
                    :error="createForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="createForm.name"
                    label="Nom"
                    :error="{ fr: createForm.errors['name.fr'], en: createForm.errors['name.en'] }"
                />

                <AppInputNumber
                    v-model="createForm.order"
                    label="Ordre"
                    :min="0"
                    :error="createForm.errors.order"
                />

                <AppCheckbox v-model="createForm.active" label="Actif" />

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
            :visible="editingZone !== null"
            header="Modifier la zone"
            @update:visible="editingZone = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.code"
                    label="Code"
                    :error="editForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="editForm.name"
                    label="Nom"
                    :error="{ fr: editForm.errors['name.fr'], en: editForm.errors['name.en'] }"
                />

                <AppInputNumber
                    v-model="editForm.order"
                    label="Ordre"
                    :min="0"
                    :error="editForm.errors.order"
                />

                <AppCheckbox v-model="editForm.active" label="Actif" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingZone = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
