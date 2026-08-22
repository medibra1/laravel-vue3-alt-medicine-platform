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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

interface Country {
    id: number;
    name: string;
    code: string;
}

interface Center {
    id: number;
    country_id: number;
    code: string;
    name: string;
    city: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    active: boolean;
    country?: { id: number; name: string; code: string };
}

const props = defineProps<{
    centers: {
        data: Center[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    countries: Country[];
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? '-created_at');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.centers.index'),
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
    { field: 'city', header: 'Ville', sortable: true },
    { field: 'country', header: 'Pays' },
    { field: 'active', header: 'Actif' },
    { field: 'actions', header: 'Actions' },
];

function fullCenterCode(center: Center): string {
    return `${center.country?.code ?? ''}${center.code}`;
}

const isCreating = ref(false);
const editingCenter = ref<Center | null>(null);

const createForm = useForm({
    country_id: null as number | null,
    code: '',
    name: '',
    city: '',
    address: '',
    phone: '',
    email: '',
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.active = true;
    isCreating.value = true;
}

// Auto-suggested next code for the selected country — the field stays
// editable, this only pre-fills it (see CenterCodeGenerator::suggestNext()).
watch(
    () => createForm.country_id,
    async (countryId) => {
        if (!countryId || createForm.code) {
            return;
        }

        const response = await fetch(
            route('admin.centers.next-code', { country_id: countryId }),
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            const data = await response.json();
            createForm.code = data.code;
        }
    },
);

function submitCreate() {
    createForm.post(route('admin.centers.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    country_id: null as number | null,
    code: '',
    name: '',
    city: '',
    address: '',
    phone: '',
    email: '',
    active: true,
});

function openEdit(center: Center) {
    editingCenter.value = center;
    editForm.reset();
    editForm.country_id = center.country_id;
    editForm.code = center.code;
    editForm.name = center.name;
    editForm.city = center.city ?? '';
    editForm.address = center.address ?? '';
    editForm.phone = center.phone ?? '';
    editForm.email = center.email ?? '';
    editForm.active = center.active;
}

function submitEdit() {
    if (!editingCenter.value) {
        return;
    }

    editForm.put(
        route('admin.centers.update', editingCenter.value.id),
        { onSuccess: () => (editingCenter.value = null) },
    );
}

function destroy(center: Center) {
    if (!confirm(`Supprimer le centre ${center.name} ?`)) {
        return;
    }

    router.delete(route('admin.centers.destroy', center.id));
}
</script>

<template>
    <Head title="Centres" />

    <AuthenticatedLayout>
        <AppPageHeader title="Centres" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Centres' }]">
            <template #actions>
                <AppButton label="Nouveau centre" icon="mdi-plus" @click="openCreate" />
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
                    :value="centers.data"
                    :columns="columns"
                    :rows="centers.per_page"
                    :total-records="centers.total"
                    :page="centers.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-code="{ item }">{{ fullCenterCode(item) }}</template>
                    <template #column-country="{ item }">{{ item.country?.name }}</template>
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

        <AppDialog v-model:visible="isCreating" header="Nouveau centre">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppSelect
                    v-model="createForm.country_id"
                    :options="countries"
                    option-label="name"
                    option-value="id"
                    label="Pays"
                    placeholder="Choisir un pays"
                    :error="createForm.errors.country_id"
                />

                <AppInputText
                    v-model="createForm.code"
                    label="Code centre (2 chiffres, suggéré automatiquement)"
                    :maxlength="2"
                    :error="createForm.errors.code"
                />

                <AppInputText
                    v-model="createForm.name"
                    label="Nom"
                    :error="createForm.errors.name"
                />

                <AppInputText v-model="createForm.city" label="Ville" :error="createForm.errors.city" />
                <AppInputText v-model="createForm.address" label="Adresse" />
                <AppInputText v-model="createForm.phone" label="Téléphone" />
                <AppInputText v-model="createForm.email" label="Email" :error="createForm.errors.email" />

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
            :visible="editingCenter !== null"
            header="Modifier le centre"
            @update:visible="editingCenter = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppSelect
                    v-model="editForm.country_id"
                    :options="countries"
                    option-label="name"
                    option-value="id"
                    label="Pays"
                    :error="editForm.errors.country_id"
                />

                <AppInputText
                    v-model="editForm.code"
                    label="Code centre (2 chiffres)"
                    :maxlength="2"
                    :error="editForm.errors.code"
                />

                <AppInputText
                    v-model="editForm.name"
                    label="Nom"
                    :error="editForm.errors.name"
                />

                <AppInputText v-model="editForm.city" label="Ville" :error="editForm.errors.city" />
                <AppInputText v-model="editForm.address" label="Adresse" />
                <AppInputText v-model="editForm.phone" label="Téléphone" />
                <AppInputText v-model="editForm.email" label="Email" :error="editForm.errors.email" />

                <AppCheckbox v-model="editForm.active" label="Actif" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingCenter = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
