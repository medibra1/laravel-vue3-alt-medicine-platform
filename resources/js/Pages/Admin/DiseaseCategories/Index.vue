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
import { reactive, ref } from 'vue';

interface EnumOptionOption {
    id: number;
    code: string;
    label: string;
}

interface DiseaseCategory {
    id: number;
    type_option_id: number;
    code: string;
    label: { fr: string; en: string };
    order: number;
    active: boolean;
    type?: { id: number; code: string; label: string };
}

const props = defineProps<{
    categories: {
        data: DiseaseCategory[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    types: EnumOptionOption[];
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? 'order');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.disease-categories.index'),
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
    { field: 'type', header: 'Type' },
    { field: 'order', header: 'Ordre', sortable: true },
    { field: 'active', header: 'Actif' },
    { field: 'actions', header: 'Actions' },
];

const isCreating = ref(false);
const editingCategory = ref<DiseaseCategory | null>(null);

const createForm = useForm({
    type_option_id: null as number | null,
    code: '',
    label: { fr: '', en: '' },
    order: 0,
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.label = { fr: '', en: '' };
    createForm.order = 0;
    createForm.active = true;
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.disease-categories.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    type_option_id: null as number | null,
    code: '',
    label: { fr: '', en: '' },
    order: 0,
    active: true,
});

function openEdit(category: DiseaseCategory) {
    editingCategory.value = category;
    editForm.reset();
    editForm.type_option_id = category.type_option_id;
    editForm.code = category.code;
    editForm.label = { ...category.label };
    editForm.order = category.order;
    editForm.active = category.active;
}

function submitEdit() {
    if (!editingCategory.value) {
        return;
    }

    editForm.put(
        route('admin.disease-categories.update', editingCategory.value.id),
        { onSuccess: () => (editingCategory.value = null) },
    );
}

function destroy(category: DiseaseCategory) {
    if (!confirm(`Supprimer la catégorie ${category.label.fr} ?`)) {
        return;
    }

    router.delete(route('admin.disease-categories.destroy', category.id));
}
</script>

<template>
    <Head title="Catégories de maladies" />

    <AuthenticatedLayout>
        <AppPageHeader title="Catégories de maladies" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Catégories de maladies' }]">
            <template #actions>
                <AppButton label="Nouvelle catégorie" icon="mdi-plus" @click="openCreate" />
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
                    :value="categories.data"
                    :columns="columns"
                    :rows="categories.per_page"
                    :total-records="categories.total"
                    :page="categories.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-label="{ item }">{{ item.label.fr }}</template>
                    <template #column-type="{ item }">{{ item.type?.label }}</template>
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

        <AppDialog v-model:visible="isCreating" header="Nouvelle catégorie">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.code"
                    label="Code"
                    :error="createForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="createForm.label"
                    label="Libellé"
                    :error="{ fr: createForm.errors['label.fr'], en: createForm.errors['label.en'] }"
                />

                <AppSelect
                    v-model="createForm.type_option_id"
                    :options="types"
                    option-label="label"
                    option-value="id"
                    label="Type"
                    placeholder="Choisir un type"
                    :error="createForm.errors.type_option_id"
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
            :visible="editingCategory !== null"
            header="Modifier la catégorie"
            @update:visible="editingCategory = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.code"
                    label="Code"
                    :error="editForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="editForm.label"
                    label="Libellé"
                    :error="{ fr: editForm.errors['label.fr'], en: editForm.errors['label.en'] }"
                />

                <AppSelect
                    v-model="editForm.type_option_id"
                    :options="types"
                    option-label="label"
                    option-value="id"
                    label="Type"
                    :error="editForm.errors.type_option_id"
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
                        @click="editingCategory = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
