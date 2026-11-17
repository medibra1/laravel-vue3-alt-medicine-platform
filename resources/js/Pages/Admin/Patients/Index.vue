<script setup lang="ts">
import { tr } from '@/lib/i18n';
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface Center {
    id: number;
    name: string;
    code: string;
}

interface Patient {
    id: number;
    first_name: string | null;
    last_name: string | null;
    patient_number: string | null;
    intake_center_id: number;
    center?: { id: number; name: string; code: string; country?: { code: string } };
}

const props = defineProps<{
    patients: {
        data: Patient[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    centers: Center[];
    can_create: boolean;
}>();

const search = reactive({
    search: props.filters.filter?.search ?? '',
});

const currentSort = ref(props.filters.sort ?? '-created_at');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.patients.index'),
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
    { field: 'patient_number', header: tr('ui.nPatient'), sortable: true },
    { field: 'first_name', header: tr('ui.prenom'), sortable: true },
    { field: 'last_name', header: tr('ui.nom'), sortable: true },
    { field: 'center', header: tr('ui.centre') },
    { field: 'actions', header: tr('ui.actions') },
];

function fullPatientNumber(patient: Patient): string {
    if (!patient.patient_number || !patient.center) {
        return '—';
    }

    const countryCode = patient.center.country?.code ?? '';

    return `${countryCode}${patient.center.code}${patient.patient_number}`;
}

function destroy(patient: Patient) {
    if (!confirm(tr('misc.confirmDeleteNamed', { name: `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim() }))) {
        return;
    }

    router.delete(route('admin.patients.destroy', patient.id));
}
</script>

<template>
    <Head :title="$t('ui.patients')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="$t('ui.patients')" :breadcrumbs="[{ label: $t('shell.nav.dashboard'), href: route('dashboard') }, { label: $t('ui.patients') }]">
            <template v-if="can_create" #actions>
                <Link :href="route('admin.patients.create')">
                    <AppButton :label="$t('ui.nouveauPatient')" icon="mdi-plus" as="span" />
                </Link>
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <v-card-text class="d-flex flex-wrap align-end ga-3">
                    <AppInputText
                        id="filter-search"
                        v-model="search.search"
                        :label="$t('ui.rechercherPrenomNom')"
                        prepend-inner-icon="mdi-magnify"
                        @keyup.enter="reload()"
                    />
                    <AppButton :label="$t('ui.filtrer')" severity="secondary" @click="reload()" />
                </v-card-text>
            </AppCard>

            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="patients.data"
                    :columns="columns"
                    :rows="patients.per_page"
                    :total-records="patients.total"
                    :page="patients.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-patient_number="{ item }">
                        <Link :href="route('admin.patients.edit', item.id)" class="font-weight-bold text-primary text-decoration-none app-tabular">
                            {{ fullPatientNumber(item) }}
                        </Link>
                    </template>
                    <template #column-center="{ item }">{{ item.center?.name }}</template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <Link :href="route('admin.patients.edit', item.id)">
                                <AppButton
                                    :label="can_create ? $t('common.edit') : $t('common.view')"
                                    severity="secondary"
                                    size="small"
                                    as="span"
                                />
                            </Link>
                            <AppButton
                                v-if="can_create"
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
    </AuthenticatedLayout>
</template>
