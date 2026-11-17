<script setup lang="ts">
import { tr } from '@/lib/i18n';
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

interface CareCategory {
    id: number;
    code: string;
    label: { fr: string; en: string };
    order: number;
    active: boolean;
}

const props = defineProps<{
    careCategories: {
        data: CareCategory[];
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
        route('admin.care-categories.index'),
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
    { field: 'code', header: tr('ui.code'), sortable: true },
    { field: 'label', header: tr('ui.libelle') },
    { field: 'order', header: tr('ui.ordre'), sortable: true },
    { field: 'active', header: tr('ui.actif') },
    { field: 'actions', header: tr('ui.actions') },
];

const isCreating = ref(false);
const editingCareCategory = ref<CareCategory | null>(null);

const createForm = useForm({
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
    createForm.post(route('admin.care-categories.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    code: '',
    label: { fr: '', en: '' },
    order: 0,
    active: true,
});

function openEdit(careCategory: CareCategory) {
    editingCareCategory.value = careCategory;
    editForm.reset();
    editForm.code = careCategory.code;
    editForm.label = { ...careCategory.label };
    editForm.order = careCategory.order;
    editForm.active = careCategory.active;
}

function submitEdit() {
    if (!editingCareCategory.value) {
        return;
    }

    editForm.put(
        route('admin.care-categories.update', editingCareCategory.value.id),
        { onSuccess: () => (editingCareCategory.value = null) },
    );
}

function destroy(careCategory: CareCategory) {
    if (!confirm(tr('misc.confirmDeleteNamed', { name: careCategory.label.fr }))) {
        return;
    }

    router.delete(route('admin.care-categories.destroy', careCategory.id));
}
</script>

<template>
    <Head :title="$t('ui.categoriesDeSoins')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="$t('ui.categoriesDeSoins')" :breadcrumbs="[{ label: $t('shell.nav.dashboard'), href: route('dashboard') }, { label: $t('ui.categoriesDeSoins') }]">
            <template #actions>
                <AppButton :label="$t('ui.nouvelleCategorie')" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        :label="$t('ui.rechercherCodeLibelle')"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton :label="$t('ui.filtrer')" severity="secondary" @click="reload()" />
                </v-card-text>
            </AppCard>

            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="careCategories.data"
                    :columns="columns"
                    :rows="careCategories.per_page"
                    :total-records="careCategories.total"
                    :page="careCategories.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-label="{ item }">{{ item.label[$i18n.locale as 'fr'] ?? item.label.fr }}</template>
                    <template #column-active="{ item }">{{ item.active ? $t('common.yes') : $t('common.no') }}</template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <AppButton
                                :label="$t('ui.modifier')"
                                severity="secondary"
                                size="small"
                                @click="openEdit(item)"
                            />
                            <AppButton
                                :label="$t('ui.supprimer')"
                                severity="danger"
                                size="small"
                                @click="destroy(item)"
                            />
                        </div>
                    </template>
                </AppDataTable>
            </AppCard>
        </div>

        <AppDialog v-model:visible="isCreating" :header="$t('ui.nouvelleCategorieDeSoin')">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.code"
                    :label="$t('ui.codeIdentifiantExOintment')"
                    :error="createForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="createForm.label"
                    :label="$t('ui.libelle')"
                    :error="{ fr: createForm.errors['label.fr'], en: createForm.errors['label.en'] }"
                />

                <AppInputNumber v-model="createForm.order" :label="$t('ui.ordre')" :min="0" />

                <AppCheckbox v-model="createForm.active" :label="$t('ui.actif')" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        :label="$t('ui.annuler')"
                        severity="secondary"
                        @click="isCreating = false"
                    />
                    <AppButton type="submit" :label="$t('ui.creer')" :loading="createForm.processing" />
                </div>
            </form>
        </AppDialog>

        <AppDialog
            :visible="editingCareCategory !== null"
            :header="$t('ui.modifierLaCategorieDeSoin')"
            @update:visible="editingCareCategory = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.code"
                    :label="$t('ui.codeIdentifiant')"
                    :error="editForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="editForm.label"
                    :label="$t('ui.libelle')"
                    :error="{ fr: editForm.errors['label.fr'], en: editForm.errors['label.en'] }"
                />

                <AppInputNumber v-model="editForm.order" :label="$t('ui.ordre')" :min="0" />

                <AppCheckbox v-model="editForm.active" :label="$t('ui.actif')" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        :label="$t('ui.annuler')"
                        severity="secondary"
                        @click="editingCareCategory = null"
                    />
                    <AppButton type="submit" :label="$t('ui.enregistrer')" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
