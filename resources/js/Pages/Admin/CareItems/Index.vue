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

interface CareCategory {
    id: number;
    code: string;
    label: { fr: string; en: string };
}

interface CareItem {
    id: number;
    care_category_id: number;
    code: string;
    label: { fr: string; en: string };
    description: { fr: string; en: string };
    order: number;
    active: boolean;
    category?: { id: number; code: string; label: { fr: string; en: string } };
}

const props = defineProps<{
    careItems: {
        data: CareItem[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    categories: CareCategory[];
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? 'order');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.care-items.index'),
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
    { field: 'label', header: 'Libellé' },
    { field: 'category', header: 'Catégorie' },
    { field: 'order', header: 'Ordre', sortable: true },
    { field: 'active', header: 'Actif' },
    { field: 'actions', header: 'Actions' },
];

function categoryLabel(careItem: CareItem): string {
    return careItem.category?.label.fr ?? '';
}

const isCreating = ref(false);
const editingCareItem = ref<CareItem | null>(null);

const createForm = useForm({
    care_category_id: null as number | null,
    code: '',
    label: { fr: '', en: '' },
    description: { fr: '', en: '' },
    order: 0,
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.label = { fr: '', en: '' };
    createForm.description = { fr: '', en: '' };
    createForm.order = 0;
    createForm.active = true;
    isCreating.value = true;
}

// Auto-suggested next code for the selected category — the field
// stays editable, this only pre-fills it (see CareItemCodeGenerator::suggestNext()).
watch(
    () => createForm.care_category_id,
    async (categoryId) => {
        if (!categoryId || createForm.code) {
            return;
        }

        const response = await fetch(
            route('admin.care-items.next-code', { category_id: categoryId }),
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            const data = await response.json();
            createForm.code = data.code;
        }
    },
);

function submitCreate() {
    createForm.post(route('admin.care-items.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    care_category_id: null as number | null,
    code: '',
    label: { fr: '', en: '' },
    description: { fr: '', en: '' },
    order: 0,
    active: true,
});

function openEdit(careItem: CareItem) {
    editingCareItem.value = careItem;
    editForm.reset();
    editForm.care_category_id = careItem.care_category_id;
    editForm.code = careItem.code;
    editForm.label = { ...careItem.label };
    editForm.description = { ...careItem.description };
    editForm.order = careItem.order;
    editForm.active = careItem.active;
}

function submitEdit() {
    if (!editingCareItem.value) {
        return;
    }

    editForm.put(
        route('admin.care-items.update', editingCareItem.value.id),
        { onSuccess: () => (editingCareItem.value = null) },
    );
}

function destroy(careItem: CareItem) {
    if (!confirm(`Supprimer le soin ${careItem.label.fr} ?`)) {
        return;
    }

    router.delete(route('admin.care-items.destroy', careItem.id));
}
</script>

<template>
    <Head title="Soins" />

    <AuthenticatedLayout>
        <AppPageHeader title="Soins" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Soins' }]">
            <template #actions>
                <AppButton label="Nouveau soin" icon="mdi-plus" @click="openCreate" />
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
                    :value="careItems.data"
                    :columns="columns"
                    :rows="careItems.per_page"
                    :total-records="careItems.total"
                    :page="careItems.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-label="{ item }">{{ item.label.fr }}</template>
                    <template #column-category="{ item }">{{ categoryLabel(item) }}</template>
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

        <AppDialog v-model:visible="isCreating" header="Nouveau soin">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppSelect
                    v-model="createForm.care_category_id"
                    :options="categories"
                    option-label="code"
                    option-value="id"
                    label="Catégorie"
                    placeholder="Choisir une catégorie"
                    :error="createForm.errors.care_category_id"
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

                <AppInputNumber v-model="createForm.order" label="Ordre" :min="0" />

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
            :visible="editingCareItem !== null"
            header="Modifier le soin"
            @update:visible="editingCareItem = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppSelect
                    v-model="editForm.care_category_id"
                    :options="categories"
                    option-label="code"
                    option-value="id"
                    label="Catégorie"
                    :error="editForm.errors.care_category_id"
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

                <AppInputNumber v-model="editForm.order" label="Ordre" :min="0" />

                <AppCheckbox v-model="editForm.active" label="Actif" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingCareItem = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
