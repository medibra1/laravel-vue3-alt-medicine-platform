<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Center {
    id: number;
    name: string;
    code: string;
}

interface Treatment {
    id: number;
    started_at: string | null;
    ended_at: string | null;
    outcome: string | null;
    patient?: { id: number; first_name: string | null; last_name: string | null };
    practitioner?: { id: number; first_name: string; last_name: string; full_code: string };
    center?: { id: number; name: string };
}

const props = defineProps<{
    treatments: {
        data: Treatment[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    centers: Center[];
    canCreate: boolean;
}>();

const currentSort = ref(props.filters.sort ?? '-created_at');

function reload(extra: Record<string, unknown> = {}) {
    router.get(
        route('admin.treatments.index'),
        { sort: currentSort.value, ...extra },
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
    { field: 'patient', header: 'Patient' },
    { field: 'practitioner', header: 'Praticien' },
    { field: 'started_at', header: 'Début', sortable: true },
    { field: 'center', header: 'Centre' },
    { field: 'actions', header: 'Actions' },
];

function destroy(treatment: Treatment) {
    if (!confirm('Supprimer ce traitement ?')) {
        return;
    }

    router.delete(route('admin.treatments.destroy', treatment.id));
}
</script>

<template>
    <Head title="Traitements" />

    <AuthenticatedLayout>
        <AppPageHeader title="Traitements" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Traitements' }]">
            <template v-if="canCreate" #actions>
                <Link :href="route('admin.treatments.create')">
                    <AppButton label="Nouveau traitement" icon="mdi-plus" as="span" />
                </Link>
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-4">
            <AppCard variant="elevated" elevation="1">
                <AppDataTable
                    :value="treatments.data"
                    :columns="columns"
                    :rows="treatments.per_page"
                    :total-records="treatments.total"
                    :page="treatments.current_page"
                    @page="onPage"
                    @sort="onSort"
                >
                    <template #column-patient="{ item }">
                        {{ item.patient?.first_name }} {{ item.patient?.last_name }}
                    </template>
                    <template #column-practitioner="{ item }">
                        {{ item.practitioner ? `${item.practitioner.first_name} ${item.practitioner.last_name}` : '' }}
                    </template>
                    <template #column-started_at="{ item }">
                        {{ item.started_at ? new Date(item.started_at).toLocaleDateString() : '' }}
                    </template>
                    <template #column-center="{ item }">{{ item.center?.name }}</template>
                    <template #actions="{ item }">
                        <div v-if="canCreate" class="d-flex ga-2">
                            <Link :href="route('admin.treatments.edit', item.id)">
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
                        <span v-else class="text-medium-emphasis">—</span>
                    </template>
                </AppDataTable>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
