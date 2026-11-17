<script setup lang="ts">
import { formatDate, tr } from '@/lib/i18n';
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, {
    type AppDataTableColumn,
    type AppDataTableSortEvent,
} from '@/Components/App/AppDataTable.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Center {
    id: number;
    name: string;
    code: string;
}

type TreatmentStatus = 'draft' | 'confirmed' | 'ongoing' | 'closed';

interface TreatmentRow {
    id: number;
    center_id: number;
    status: TreatmentStatus | null;
    closure_reason: string | null;
    started_at: string | null;
    ended_at: string | null;
    sessions_count: number;
    last_session_on: string | null;
    patient: { id: number; first_name: string | null; last_name: string | null } | null;
    practitioner: { id: number; first_name: string; last_name: string } | null;
    center: { id: number; name: string } | null;
    diseases: { id: number; code: string; label: string }[];
}

const props = defineProps<{
    treatments: {
        data: TreatmentRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { filter?: Record<string, string>; sort?: string };
    status_counts: Record<TreatmentStatus, number>;
    practitioners: { id: number; first_name: string; last_name: string; full_code: string }[];
    centers: Center[];
    can_create: boolean;
}>();

const { t, locale } = useI18n();

const STATUSES: TreatmentStatus[] = ['ongoing', 'draft', 'confirmed', 'closed'];

const search = reactive({
    search: props.filters.filter?.search ?? '',
    status: props.filters.filter?.status ?? 'ongoing',
    practitioner_id: props.filters.filter?.practitioner_id ? Number(props.filters.filter.practitioner_id) : null,
    center_id: props.filters.filter?.center_id ? Number(props.filters.filter.center_id) : null,
});

const currentSort = ref(props.filters.sort ?? '-created_at');

const practitionerOptions = computed(() =>
    props.practitioners.map((practitioner) => ({ id: practitioner.id, label: `${practitioner.first_name} ${practitioner.last_name}` })),
);
const totalCount = computed(() => STATUSES.reduce((sum, status) => sum + (props.status_counts[status] ?? 0), 0));

function reload(extra: Record<string, unknown> = {}) {
    // Empty values are dropped so the URL stays clean. "all" is sent as is:
    // without a status the server shows ongoing treatments.
    const filter = Object.fromEntries(Object.entries(search).filter(([, value]) => value !== '' && value !== null));

    router.get(
        route('admin.treatments.index'),
        { filter, sort: currentSort.value, ...extra },
        { preserveState: true, replace: true },
    );
}

function setStatus(status: string) {
    search.status = status;
    reload();
}

function onPage(page: number) {
    reload({ page });
}

function onSort(event: AppDataTableSortEvent) {
    if (!event.sortField) {
        return;
    }

    currentSort.value = event.sortOrder === -1 ? `-${event.sortField}` : `${event.sortField}`;
    reload();
}

const columns = computed<AppDataTableColumn[]>(() => [
    { field: 'patient', header: tr('ui.patient') },
    { field: 'status', header: tr('ui.statut') },
    { field: 'diseases', header: tr('ui.maladies') },
    { field: 'practitioner', header: tr('ui.praticien') },
    { field: 'started_at', header: tr('ui.debut'), sortable: true },
    { field: 'sessions_max_session_date', header: t('patients.treatmentList.lastSession'), sortable: true },
    ...(props.centers.length ? [{ field: 'center', header: tr('ui.centre') }] : []),
    { field: 'actions', header: tr('ui.actions') },
]);

function statusLabel(row: TreatmentRow): string {
    const base = row.status ? t(`domain.treatmentStatus.${row.status}`) : '—';

    if (row.status === 'closed' && row.closure_reason) {
        return t('misc.closedWithReason', { status: base, reason: t(`domain.closureReason.${row.closure_reason}`).toLowerCase() });
    }

    return base;
}

/** Same colors as the treatment card of the patient file. */
function statusColor(row: TreatmentRow): string {
    if (row.status === 'ongoing') return 'primary';
    if (row.status === 'closed') return row.closure_reason === 'resolved' ? 'success' : 'warning';

    return 'secondary';
}

/** Opens the patient file on the tab that shows this treatment. */
function patientFileUrl(row: TreatmentRow): string {
    // Only the ongoing treatment is on "Traitement en cours"; drafts and
    // closed ones are listed under "Historique".
    return `${route('admin.patients.edit', row.patient!.id)}?tab=${row.status === 'ongoing' ? 'ongoing' : 'history'}`;
}

function destroy(row: TreatmentRow) {
    if (!confirm(tr('misc.confirmDeleteTreatment'))) {
        return;
    }

    router.delete(route('admin.treatments.destroy', row.id));
}
</script>

<template>
    <Head :title="$t('ui.traitements')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="$t('ui.traitements')" :breadcrumbs="[{ label: $t('shell.nav.dashboard'), href: route('dashboard') }, { label: $t('ui.traitements') }]">
            <template v-if="can_create" #actions>
                <Link :href="route('admin.treatments.create')">
                    <AppButton :label="$t('ui.nouveauTraitement')" icon="mdi-plus" as="span" />
                </Link>
            </template>
        </AppPageHeader>

        <p class="text-body-2 text-medium-emphasis mb-4">{{ t('patients.treatmentList.subtitle') }}</p>

        <div class="d-flex flex-column ga-4">
            <div class="d-flex flex-wrap ga-2">
                <v-chip
                    v-for="status in STATUSES"
                    :key="status"
                    :color="search.status === status ? 'primary' : undefined"
                    :variant="search.status === status ? 'flat' : 'outlined'"
                    @click="setStatus(status)"
                >
                    {{ t(`domain.treatmentStatus.${status}`) }}
                    <span class="ms-2 font-weight-bold app-tabular">{{ status_counts[status] ?? 0 }}</span>
                </v-chip>
                <v-chip
                    :color="search.status === 'all' ? 'primary' : undefined"
                    :variant="search.status === 'all' ? 'flat' : 'outlined'"
                    @click="setStatus('all')"
                >
                    {{ t('patients.treatmentList.all') }}
                    <span class="ms-2 font-weight-bold app-tabular">{{ totalCount }}</span>
                </v-chip>
            </div>

            <AppCard variant="elevated" elevation="1">
                <v-card-text>
                    <form class="d-flex flex-wrap align-center ga-3" @submit.prevent="reload()">
                        <div class="treatment-filter treatment-filter--wide">
                            <AppInputText v-model="search.search" :label="t('patients.treatmentList.search')" />
                        </div>
                        <div class="treatment-filter">
                            <AppSelect
                                v-model="search.practitioner_id"
                                :options="practitionerOptions"
                                option-label="label"
                                option-value="id"
                                :label="$t('ui.praticien')"
                                show-clear
                                @update:model-value="reload()"
                            />
                        </div>
                        <div v-if="centers.length" class="treatment-filter">
                            <AppSelect
                                v-model="search.center_id"
                                :options="centers"
                                option-label="name"
                                option-value="id"
                                :label="$t('ui.centre')"
                                show-clear
                                @update:model-value="reload()"
                            />
                        </div>
                        <AppButton type="submit" :label="$t('ui.filtrer')" icon="mdi-magnify" />
                    </form>
                </v-card-text>
            </AppCard>

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
                        <Link v-if="item.patient" :href="patientFileUrl(item)" class="treatment-patient" :title="t('patients.treatmentList.openFile')">
                            {{ item.patient.first_name }} {{ item.patient.last_name }}
                        </Link>
                    </template>
                    <template #column-status="{ item }">
                        <v-chip size="small" variant="tonal" :color="statusColor(item)">{{ statusLabel(item) }}</v-chip>
                    </template>
                    <template #column-diseases="{ item }">
                        <div class="d-flex flex-wrap ga-1 py-1">
                            <v-chip v-for="disease in item.diseases" :key="disease.id" size="x-small" variant="outlined" :title="disease.label">
                                {{ disease.code }} — {{ disease.label }}
                            </v-chip>
                            <span v-if="!item.diseases.length" class="text-medium-emphasis">—</span>
                        </div>
                    </template>
                    <template #column-practitioner="{ item }">
                        {{ item.practitioner ? `${item.practitioner.first_name} ${item.practitioner.last_name}` : '—' }}
                    </template>
                    <template #column-started_at="{ item }">
                        {{ item.started_at ? formatDate(item.started_at, locale) : '—' }}
                    </template>
                    <template #column-sessions_max_session_date="{ item }">
                        <template v-if="item.last_session_on">
                            <div>{{ formatDate(item.last_session_on, locale) }}</div>
                            <div class="text-caption text-medium-emphasis">{{ t('patients.treatmentList.sessionsCount', { n: item.sessions_count }, item.sessions_count) }}</div>
                        </template>
                        <span v-else class="text-medium-emphasis">{{ t('patients.treatmentList.noSession') }}</span>
                    </template>
                    <template #column-center="{ item }">{{ item.center?.name }}</template>
                    <template #actions="{ item }">
                        <div class="d-flex ga-2">
                            <Link v-if="item.patient" :href="patientFileUrl(item)">
                                <AppButton :label="t('patients.treatmentList.openFile')" severity="secondary" size="small" as="span" />
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

<style scoped>
.treatment-filter {
    flex: 1 1 200px;
    min-width: 180px;
}

.treatment-filter--wide {
    flex-basis: 280px;
}

.treatment-patient {
    color: rgb(var(--v-theme-primary));
    font-weight: 600;
    text-decoration: none;
}

.treatment-patient:hover {
    text-decoration: underline;
}
</style>
