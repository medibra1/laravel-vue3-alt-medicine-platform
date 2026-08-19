<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable, {
    type DataTablePageEvent,
    type DataTableSortEvent,
} from 'primevue/datatable';
import InputText from 'primevue/inputtext';
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

function onPage(event: DataTablePageEvent) {
    reload({ page: event.page + 1 });
}

function onSort(event: DataTableSortEvent) {
    if (!event.sortField) {
        return;
    }

    currentSort.value =
        event.sortOrder === -1 ? `-${event.sortField}` : `${event.sortField}`;
    reload();
}

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
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Patients
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <label
                            for="filter-first-name"
                            class="text-sm text-gray-600"
                            >Prénom</label
                        >
                        <InputText
                            id="filter-first-name"
                            v-model="search.first_name"
                            @keyup.enter="reload()"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            for="filter-last-name"
                            class="text-sm text-gray-600"
                            >Nom</label
                        >
                        <InputText
                            id="filter-last-name"
                            v-model="search.last_name"
                            @keyup.enter="reload()"
                        />
                    </div>
                    <Button label="Filtrer" @click="reload()" />
                    <Link :href="route('admin.patients.create')" class="ms-auto">
                        <Button label="Nouveau patient" as="span" />
                    </Link>
                </div>

                <div class="rounded-lg bg-white shadow">
                    <DataTable
                        :value="patients.data"
                        lazy
                        paginator
                        removable-sort
                        sort-mode="single"
                        :rows="patients.per_page"
                        :total-records="patients.total"
                        :first="(patients.current_page - 1) * patients.per_page"
                        @page="onPage"
                        @sort="onSort"
                    >
                        <Column field="first_name" header="Prénom" sortable />
                        <Column field="last_name" header="Nom" sortable />
                        <Column header="Centre">
                            <template #body="{ data }">{{ data.center?.name }}</template>
                        </Column>
                        <Column header="Actions">
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.patients.edit', data.id)">
                                        <Button
                                            label="Modifier"
                                            severity="secondary"
                                            size="small"
                                            as="span"
                                        />
                                    </Link>
                                    <Button
                                        label="Supprimer"
                                        severity="danger"
                                        size="small"
                                        @click="destroy(data)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
