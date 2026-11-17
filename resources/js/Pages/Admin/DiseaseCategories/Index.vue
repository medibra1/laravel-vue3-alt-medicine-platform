<script setup lang="ts">
import { localized, tr } from '@/lib/i18n';
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
import type { TranslatableValue } from '@/Components/App/AppTranslatableInput.vue';
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
    label: { fr: string; en: string; ar?: string };
    icon: string | null;
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
    { field: 'code', header: tr('ui.code'), sortable: true },
    { field: 'label', header: tr('ui.libelleFr') },
    { field: 'icon', header: tr('ui.icone') },
    { field: 'type', header: tr('ui.type') },
    { field: 'order', header: tr('ui.ordre'), sortable: true },
    { field: 'active', header: tr('ui.actif') },
    { field: 'actions', header: tr('ui.actions') },
];

const isCreating = ref(false);
const editingCategory = ref<DiseaseCategory | null>(null);

const createForm = useForm({
    type_option_id: null as number | null,
    code: '',
    label: { fr: '', en: '' } as TranslatableValue,
    icon: null as string | null,
    order: 0,
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.label = { fr: '', en: '' };
    createForm.icon = null;
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
    label: { fr: '', en: '' } as TranslatableValue,
    icon: null as string | null,
    order: 0,
    active: true,
});

function openEdit(category: DiseaseCategory) {
    editingCategory.value = category;
    editForm.reset();
    editForm.type_option_id = category.type_option_id;
    editForm.code = category.code;
    editForm.label = { ...category.label };
    editForm.icon = category.icon;
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
    if (!confirm(tr('misc.confirmDeleteNamed', { name: localized(category.label) }))) {
        return;
    }

    router.delete(route('admin.disease-categories.destroy', category.id));
}
</script>

<template>
    <Head :title="$t('ui.categoriesDeMaladies')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="$t('ui.categoriesDeMaladies')" :breadcrumbs="[{ label: $t('shell.nav.dashboard'), href: route('dashboard') }, { label: $t('ui.categoriesDeMaladies') }]">
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
                    :value="categories.data"
                    :columns="columns"
                    :rows="categories.per_page"
                    :total-records="categories.total"
                    :page="categories.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-label="{ item }">{{ localized(item.label) }}</template>
                    <template #column-icon="{ item }">
                        <v-icon v-if="item.icon" :icon="item.icon" />
                        <span v-else class="text-medium-emphasis">—</span>
                    </template>
                    <template #column-type="{ item }">{{ item.type?.label }}</template>
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

        <AppDialog v-model:visible="isCreating" :header="$t('ui.nouvelleCategorie')">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.code"
                    :label="$t('ui.code')"
                    :error="createForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="createForm.label"
                    :label="$t('ui.libelle')"
                    :error="{ fr: createForm.errors['label.fr'], en: createForm.errors['label.en'], ar: createForm.errors['label.ar'] }"
                />

                <AppInputText
                    v-model="createForm.icon"
                    :label="$t('ui.iconeNomMdiExMdiStomach')"
                    :error="createForm.errors.icon"
                />

                <AppSelect
                    v-model="createForm.type_option_id"
                    :options="types"
                    option-label="label"
                    option-value="id"
                    :label="$t('ui.type')"
                    :placeholder="$t('ui.choisirUnType')"
                    :error="createForm.errors.type_option_id"
                />

                <AppInputNumber
                    v-model="createForm.order"
                    :label="$t('ui.ordre')"
                    :min="0"
                    :error="createForm.errors.order"
                />

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
            :visible="editingCategory !== null"
            :header="$t('ui.modifierLaCategorie')"
            @update:visible="editingCategory = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.code"
                    :label="$t('ui.code')"
                    :error="editForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="editForm.label"
                    :label="$t('ui.libelle')"
                    :error="{ fr: editForm.errors['label.fr'], en: editForm.errors['label.en'], ar: editForm.errors['label.ar'] }"
                />

                <AppInputText
                    v-model="editForm.icon"
                    :label="$t('ui.iconeNomMdiExMdiStomach')"
                    :error="editForm.errors.icon"
                />

                <AppSelect
                    v-model="editForm.type_option_id"
                    :options="types"
                    option-label="label"
                    option-value="id"
                    :label="$t('ui.type')"
                    :error="editForm.errors.type_option_id"
                />

                <AppInputNumber
                    v-model="editForm.order"
                    :label="$t('ui.ordre')"
                    :min="0"
                    :error="editForm.errors.order"
                />

                <AppCheckbox v-model="editForm.active" :label="$t('ui.actif')" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        :label="$t('ui.annuler')"
                        severity="secondary"
                        @click="editingCategory = null"
                    />
                    <AppButton type="submit" :label="$t('ui.enregistrer')" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
