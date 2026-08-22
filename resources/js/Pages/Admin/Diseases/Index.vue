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
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTranslatableInput from '@/Components/App/AppTranslatableInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

interface DiseaseCategoryOption {
    id: number;
    code: string;
    label: { fr: string; en: string };
}

interface Disease {
    id: number;
    disease_category_id: number;
    code: string;
    label: { fr: string; en: string };
    description: { fr: string; en: string };
    default_duration_months: number;
    active: boolean;
    category?: { id: number; code: string; label: { fr: string; en: string } };
}

const props = defineProps<{
    diseases: {
        data: Disease[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    categories: DiseaseCategoryOption[];
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? 'code');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.diseases.index'),
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
    { field: 'label', header: 'Libellé (FR)' },
    { field: 'category', header: 'Catégorie' },
    { field: 'default_duration_months', header: 'Durée (mois)' },
    { field: 'active', header: 'Actif' },
    { field: 'actions', header: 'Actions' },
];

const categoryOptions = props.categories.map((category) => ({
    id: category.id,
    code: category.code,
    label: category.label.fr,
}));

const isCreating = ref(false);
const editingDisease = ref<Disease | null>(null);

const createForm = useForm({
    disease_category_id: null as number | null,
    code: '',
    label: { fr: '', en: '' },
    description: { fr: '', en: '' },
    default_duration_months: 1,
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.label = { fr: '', en: '' };
    createForm.description = { fr: '', en: '' };
    createForm.default_duration_months = 1;
    createForm.active = true;
    isCreating.value = true;
}

// Auto-suggested next code for the selected category — the field stays
// editable, this only pre-fills it (see DiseaseCodeGenerator::suggestNext()).
watch(
    () => createForm.disease_category_id,
    async (categoryId) => {
        if (!categoryId || createForm.code) {
            return;
        }

        const response = await fetch(
            route('admin.diseases.next-code', { category_id: categoryId }),
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            const data = await response.json();
            createForm.code = data.code;
        }
    },
);

function submitCreate() {
    createForm.post(route('admin.diseases.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    disease_category_id: null as number | null,
    code: '',
    label: { fr: '', en: '' },
    description: { fr: '', en: '' },
    default_duration_months: 1,
    active: true,
});

function openEdit(disease: Disease) {
    editingDisease.value = disease;
    editForm.reset();
    editForm.disease_category_id = disease.disease_category_id;
    editForm.code = disease.code;
    editForm.label = { ...disease.label };
    editForm.description = { fr: disease.description?.fr ?? '', en: disease.description?.en ?? '' };
    editForm.default_duration_months = disease.default_duration_months;
    editForm.active = disease.active;
}

function submitEdit() {
    if (!editingDisease.value) {
        return;
    }

    editForm.put(
        route('admin.diseases.update', editingDisease.value.id),
        { onSuccess: () => (editingDisease.value = null) },
    );
}

function destroy(disease: Disease) {
    if (!confirm(`Supprimer la maladie ${disease.label.fr} ?`)) {
        return;
    }

    router.delete(route('admin.diseases.destroy', disease.id));
}
</script>

<template>
    <Head title="Maladies" />

    <AuthenticatedLayout>
        <AppPageHeader title="Maladies" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Maladies' }]">
            <template #actions>
                <AppButton label="Nouvelle maladie" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        label="Rechercher (code, libellé)"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton label="Filtrer" severity="secondary" @click="reload()" />
                </v-card-text>
            </AppCard>

            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="diseases.data"
                    :columns="columns"
                    :rows="diseases.per_page"
                    :total-records="diseases.total"
                    :page="diseases.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-label="{ item }">{{ item.label.fr }}</template>
                    <template #column-category="{ item }">{{ item.category?.label.fr }}</template>
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

        <AppDialog v-model:visible="isCreating" header="Nouvelle maladie">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppSelect
                    v-model="createForm.disease_category_id"
                    :options="categoryOptions"
                    option-label="label"
                    option-value="id"
                    label="Catégorie"
                    placeholder="Choisir une catégorie"
                    :error="createForm.errors.disease_category_id"
                />

                <AppInputText
                    v-model="createForm.code"
                    label="Code (3 chiffres, suggéré automatiquement)"
                    :maxlength="3"
                    :error="createForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="createForm.label"
                    label="Libellé"
                    :error="{ fr: createForm.errors['label.fr'], en: createForm.errors['label.en'] }"
                />

                <AppTranslatableInput
                    v-model="createForm.description"
                    label="Description"
                    multiline
                    :error="{ fr: createForm.errors['description.fr'], en: createForm.errors['description.en'] }"
                />

                <AppInputNumber
                    v-model="createForm.default_duration_months"
                    label="Durée par défaut (mois)"
                    :min="0"
                    :error="createForm.errors.default_duration_months"
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
            :visible="editingDisease !== null"
            header="Modifier la maladie"
            @update:visible="editingDisease = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppSelect
                    v-model="editForm.disease_category_id"
                    :options="categoryOptions"
                    option-label="label"
                    option-value="id"
                    label="Catégorie"
                    :error="editForm.errors.disease_category_id"
                />

                <AppInputText
                    v-model="editForm.code"
                    label="Code (3 chiffres)"
                    :maxlength="3"
                    :error="editForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="editForm.label"
                    label="Libellé"
                    :error="{ fr: editForm.errors['label.fr'], en: editForm.errors['label.en'] }"
                />

                <AppTranslatableInput
                    v-model="editForm.description"
                    label="Description"
                    multiline
                    :error="{ fr: editForm.errors['description.fr'], en: editForm.errors['description.en'] }"
                />

                <AppInputNumber
                    v-model="editForm.default_duration_months"
                    label="Durée par défaut (mois)"
                    :min="0"
                    :error="editForm.errors.default_duration_months"
                />

                <AppCheckbox v-model="editForm.active" label="Actif" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingDisease = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
