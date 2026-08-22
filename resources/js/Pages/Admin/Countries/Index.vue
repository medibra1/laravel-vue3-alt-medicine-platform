<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTranslatableInput from '@/Components/App/AppTranslatableInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface Zone {
    id: number;
    code: string;
    name: { fr: string; en: string };
}

interface Country {
    id: number;
    zone_id: number | null;
    code: string;
    name: { fr: string; en: string };
    active: boolean;
    zone?: { id: number; code: string; name: { fr: string; en: string } };
}

const props = defineProps<{
    countries: {
        data: Country[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    zones: Zone[];
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? 'code');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.countries.index'),
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
    { field: 'zone', header: 'Zone' },
    { field: 'active', header: 'Actif' },
    { field: 'actions', header: 'Actions' },
];

// Zone options for the select — a "Aucune zone" entry lets the admin
// explicitly clear zone_id back to null (9 of 46 countries currently
// have no zone assigned, see CLAUDE.md open item). AppSelect needs a
// flat option-label field, so the translatable name is pre-resolved
// to French here rather than passing the {fr, en} object through.
const zoneOptions = [
    { id: null as number | null, label: 'Aucune zone' },
    ...props.zones.map((zone) => ({ id: zone.id, label: zone.name.fr })),
];

function countryName(country: Country): string {
    return country.name.fr;
}

function zoneName(zone?: { name: { fr: string; en: string } }): string {
    return zone?.name.fr ?? '—';
}

const isCreating = ref(false);
const editingCountry = ref<Country | null>(null);

const createForm = useForm({
    zone_id: null as number | null,
    code: '',
    name: { fr: '', en: '' },
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.zone_id = null;
    createForm.name = { fr: '', en: '' };
    createForm.active = true;
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.countries.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    zone_id: null as number | null,
    code: '',
    name: { fr: '', en: '' },
    active: true,
});

function openEdit(country: Country) {
    editingCountry.value = country;
    editForm.reset();
    editForm.zone_id = country.zone_id;
    editForm.code = country.code;
    editForm.name = { fr: country.name.fr, en: country.name.en };
    editForm.active = country.active;
}

function submitEdit() {
    if (!editingCountry.value) {
        return;
    }

    editForm.put(
        route('admin.countries.update', editingCountry.value.id),
        { onSuccess: () => (editingCountry.value = null) },
    );
}

function destroy(country: Country) {
    if (!confirm(`Supprimer le pays ${countryName(country)} ?`)) {
        return;
    }

    router.delete(route('admin.countries.destroy', country.id));
}
</script>

<template>
    <Head title="Pays" />

    <AuthenticatedLayout>
        <AppPageHeader title="Pays" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Pays' }]">
            <template #actions>
                <AppButton label="Nouveau pays" icon="mdi-plus" @click="openCreate" />
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
                    :value="countries.data"
                    :columns="columns"
                    :rows="countries.per_page"
                    :total-records="countries.total"
                    :page="countries.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-name="{ item }">{{ countryName(item) }}</template>
                    <template #column-zone="{ item }">{{ zoneName(item.zone) }}</template>
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

        <AppDialog v-model:visible="isCreating" header="Nouveau pays">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.code"
                    label="Code (2 chiffres)"
                    :maxlength="2"
                    :error="createForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="createForm.name"
                    label="Nom"
                    :error="{ fr: createForm.errors['name.fr'], en: createForm.errors['name.en'] }"
                />

                <AppSelect
                    v-model="createForm.zone_id"
                    :options="zoneOptions"
                    option-label="label"
                    option-value="id"
                    label="Zone"
                    show-clear
                    :error="createForm.errors.zone_id"
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
            :visible="editingCountry !== null"
            header="Modifier le pays"
            @update:visible="editingCountry = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.code"
                    label="Code (2 chiffres)"
                    :maxlength="2"
                    :error="editForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="editForm.name"
                    label="Nom"
                    :error="{ fr: editForm.errors['name.fr'], en: editForm.errors['name.en'] }"
                />

                <AppSelect
                    v-model="editForm.zone_id"
                    :options="zoneOptions"
                    option-label="label"
                    option-value="id"
                    label="Zone"
                    show-clear
                    :error="editForm.errors.zone_id"
                />

                <AppCheckbox v-model="editForm.active" label="Actif" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingCountry = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
