<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
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
    intake_center_id: number;
    center?: { id: number; name: string };
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
}>();

const search = reactive({
    first_name: props.filters.filter?.first_name ?? '',
    last_name: props.filters.filter?.last_name ?? '',
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
    { field: 'first_name', header: 'Prénom', sortable: true },
    { field: 'last_name', header: 'Nom', sortable: true },
    { field: 'center', header: 'Centre' },
    { field: 'actions', header: 'Actions' },
];

function destroy(patient: Patient) {
    if (!confirm(`Supprimer le patient ${patient.first_name ?? ''} ${patient.last_name ?? ''} ?`)) {
        return;
    }

    router.delete(route('admin.patients.destroy', patient.id));
}
</script>

<template>
    <Head title="Patients" />

    <AuthenticatedLayout>
        <template #header>Patients</template>

        <div class="d-flex flex-column ga-4">
            <div class="d-flex flex-wrap align-end ga-3">
                <AppInputText
                    id="filter-first-name"
                    v-model="search.first_name"
                    label="Prénom"
                    @keyup.enter="reload()"
                />
                <AppInputText
                    id="filter-last-name"
                    v-model="search.last_name"
                    label="Nom"
                    @keyup.enter="reload()"
                />
                <AppButton label="Filtrer" @click="reload()" />
                <Link :href="route('admin.patients.create')" class="ms-auto">
                    <AppButton label="Nouveau patient" as="span" />
                </Link>
            </div>

            <v-card>
                <AppDataTable
                    :value="patients.data"
                    :columns="columns"
                    :rows="patients.per_page"
                    :total-records="patients.total"
                    :page="patients.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-center="{ item }">{{ item.center?.name }}</template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <Link :href="route('admin.patients.edit', item.id)">
                                <AppButton
                                    label="Modifier"
                                    severity="secondary"
                                    size="small"
                                    as="span"
                                />
                            </Link>
                            <AppButton
                                label="Supprimer"
                                severity="danger"
                                size="small"
                                @click="destroy(item)"
                            />
                        </div>
                    </template>
                </AppDataTable>
            </v-card>
        </div>
    </AuthenticatedLayout>
</template>
