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
import AppTranslatableInput from '@/Components/App/AppTranslatableInput.vue';
import type { TranslatableValue } from '@/Components/App/AppTranslatableInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface Zone {
    id: number;
    code: string;
    name: { fr: string; en: string; ar?: string };
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
    { field: 'code', header: tr('ui.code'), sortable: true },
    { field: 'name', header: tr('ui.nom'), sortable: true },
    { field: 'order', header: tr('ui.ordre'), sortable: true },
    { field: 'active', header: tr('ui.actif') },
    { field: 'actions', header: tr('ui.actions') },
];

function zoneName(zone: Zone): string {
    return localized(zone.name);
}

const isCreating = ref(false);
const editingZone = ref<Zone | null>(null);

const createForm = useForm({
    code: '',
    name: { fr: '', en: '' } as TranslatableValue,
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
    name: { fr: '', en: '' } as TranslatableValue,
    order: 0 as number | null,
    active: true,
});

function openEdit(zone: Zone) {
    editingZone.value = zone;
    editForm.reset();
    editForm.code = zone.code;
    editForm.name = { ...zone.name };
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
    if (!confirm(tr('misc.confirmDeleteNamed', { name: zoneName(zone) }))) {
        return;
    }

    router.delete(route('admin.zones.destroy', zone.id));
}
</script>

<template>
    <Head :title="$t('ui.zones')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="$t('ui.zones')" :breadcrumbs="[{ label: $t('shell.nav.dashboard'), href: route('dashboard') }, { label: $t('ui.zones') }]">
            <template #actions>
                <AppButton :label="$t('ui.nouvelleZone')" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        :label="$t('ui.rechercherNomCode')"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton :label="$t('ui.filtrer')" severity="secondary" @click="reload()" />
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

        <AppDialog v-model:visible="isCreating" :header="$t('ui.nouvelleZone')">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppInputText
                    v-model="createForm.code"
                    :label="$t('ui.code')"
                    :error="createForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="createForm.name"
                    :label="$t('ui.nom')"
                    :error="{ fr: createForm.errors['name.fr'], en: createForm.errors['name.en'], ar: createForm.errors['name.ar'] }"
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
            :visible="editingZone !== null"
            :header="$t('ui.modifierLaZone')"
            @update:visible="editingZone = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <AppInputText
                    v-model="editForm.code"
                    :label="$t('ui.code')"
                    :error="editForm.errors.code"
                />

                <AppTranslatableInput
                    v-model="editForm.name"
                    :label="$t('ui.nom')"
                    :error="{ fr: editForm.errors['name.fr'], en: editForm.errors['name.en'], ar: editForm.errors['name.ar'] }"
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
                        @click="editingZone = null"
                    />
                    <AppButton type="submit" :label="$t('ui.enregistrer')" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
