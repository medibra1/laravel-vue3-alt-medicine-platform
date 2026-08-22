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

interface EnumOption {
    id: number;
    enum_type: string;
    domain: string | null;
    code: string;
    label: { fr: string; en: string };
    order: number;
    active: boolean;
}

const props = defineProps<{
    options: {
        data: EnumOption[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    enumTypes: string[];
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
    enum_type: props.filters.filter?.enum_type ?? null,
});

const currentSort = ref(props.filters.sort ?? 'enum_type');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.enum-options.index'),
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
    { field: 'enum_type', header: 'Type', sortable: true },
    { field: 'code', header: 'Code', sortable: true },
    { field: 'label', header: 'Libellé' },
    { field: 'order', header: 'Ordre', sortable: true },
    { field: 'active', header: 'Actif' },
    { field: 'actions', header: 'Actions' },
];

const enumTypeOptions = props.enumTypes.map((type) => ({ id: type, label: type }));

const isCreating = ref(false);
const editingOption = ref<EnumOption | null>(null);

const createForm = useForm({
    enum_type: '',
    domain: '',
    code: '',
    label: { fr: '', en: '' },
    order: 0,
    active: true,
});

function openCreate() {
    createForm.reset();
    createForm.active = true;
    createForm.order = 0;
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.enum-options.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    enum_type: '',
    domain: '',
    code: '',
    label: { fr: '', en: '' },
    order: 0,
    active: true,
});

function openEdit(option: EnumOption) {
    editingOption.value = option;
    editForm.reset();
    editForm.enum_type = option.enum_type;
    editForm.domain = option.domain ?? '';
    editForm.code = option.code;
    editForm.label = { fr: option.label.fr, en: option.label.en };
    editForm.order = option.order;
    editForm.active = option.active;
}

function submitEdit() {
    if (!editingOption.value) {
        return;
    }

    editForm.put(
        route('admin.enum-options.update', editingOption.value.id),
        { onSuccess: () => (editingOption.value = null) },
    );
}

function destroy(option: EnumOption) {
    if (!confirm(`Supprimer l'option ${option.code} ?`)) {
        return;
    }

    router.delete(route('admin.enum-options.destroy', option.id));
}
</script>

<template>
    <Head title="Options dynamiques" />

    <AuthenticatedLayout>
        <AppPageHeader title="Options dynamiques" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Options dynamiques' }]">
            <template #actions>
                <AppButton label="Nouvelle option" icon="mdi-plus" @click="openCreate" />
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
                    <AppSelect
                        v-model="search.enum_type"
                        :options="enumTypeOptions"
                        option-label="label"
                        option-value="id"
                        label="Type"
                        show-clear
                        style="min-width: 220px"
                    />
                    <AppButton label="Filtrer" severity="secondary" @click="reload()" />
                </v-card-text>
            </AppCard>

            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="options.data"
                    :columns="columns"
                    :rows="options.per_page"
                    :total-records="options.total"
                    :page="options.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-label="{ item }">{{ item.label.fr }}</template>
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

        <AppDialog v-model:visible="isCreating" header="Nouvelle option">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.enum_type"
                    label="Type (ex. disease_category.type)"
                    :error="createForm.errors.enum_type"
                />

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
            :visible="editingOption !== null"
            header="Modifier l'option"
            @update:visible="editingOption = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.enum_type"
                    label="Type"
                    :error="editForm.errors.enum_type"
                />

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

                <AppInputNumber v-model="editForm.order" label="Ordre" :min="0" />

                <AppCheckbox v-model="editForm.active" label="Actif" />

                <div class="d-flex justify-end ga-2">
                    <AppButton
                        type="button"
                        label="Annuler"
                        severity="secondary"
                        @click="editingOption = null"
                    />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
