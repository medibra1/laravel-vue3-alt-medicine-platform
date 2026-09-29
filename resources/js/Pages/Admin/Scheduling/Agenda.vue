<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppWeekCalendar, { type AppCalendarColumn, type AppCalendarEvent } from '@/Components/App/AppWeekCalendar.vue';
import AppointmentDialog from '@/Components/Scheduling/AppointmentDialog.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { http } from '@/lib/http';
import { computeGridHours } from '@/utils/agendaGridHours';
import { toLocalDateString } from '@/utils/date';
import type { WeeklySlot } from '@/utils/weeklySchedule';
import { modalityIcon } from '@/utils/modality';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Center {
    id: number;
    name: string;
    code: string;
}

interface PatientOption {
    id: number;
    first_name: string | null;
    last_name: string | null;
}

interface PractitionerOption {
    id: number;
    first_name: string;
    last_name: string;
    full_code: string;
}

interface AppointmentEntry {
    id: number;
    center_id: number;
    practitioner_id: number;
    patient_id: number;
    treatment_id: number | null;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    status: string;
    modality: string | null;
    meeting_link: string | null;
    reason: string | null;
    cancellation_reason: string | null;
    patient?: { id: number; first_name: string | null; last_name: string | null };
    practitioner?: { id: number; first_name: string; last_name: string; full_code: string };
}

const props = defineProps<{
    centers: Center[];
    practitioners: PractitionerOption[];
    patients: PatientOption[];
    activeCenterId: number | null;
    centerOperatingHours: Record<number, WeeklySlot[]>;
}>();

const isSuperAdmin = computed(() => Boolean((usePage().props.auth as { is_super_admin?: boolean }).is_super_admin));

// Two modes: a front-desk "Jour" view across every practitioner of the
// active center, and a "Semaine" view scoped to one practitioner —
// covers both "who's free right now at this center" and "what does this
// practitioner's week look like" without building two separate pages.
// Deep link from the patient file ("Voir dans l'agenda"): land directly on
// the targeted practitioner's week. Read once at setup, same convention as
// Patients/Form.vue's ?tab= handling.
const query = new URLSearchParams(window.location.search);
const queryPractitionerId = Number(query.get('practitioner_id')) || null;
const queryDate = query.get('date') ? new Date(query.get('date') as string) : null;
const hasDeepLink = queryPractitionerId !== null && queryDate !== null && !Number.isNaN(queryDate.getTime());

const mode = ref<'day' | 'week'>(hasDeepLink ? 'week' : 'day');
const anchorDate = ref(hasDeepLink ? (queryDate as Date) : new Date());
const selectedCenterId = ref<number | null>(props.centers[0]?.id ?? null);
const selectedPractitionerId = ref<number | null>(hasDeepLink ? queryPractitionerId : (props.practitioners[0]?.id ?? null));

const centerOptions = computed(() => props.centers.map((c) => ({ id: c.id, name: `${c.name} (${c.code})` })));
const practitionerOptions = computed(() =>
    props.practitioners.map((p) => ({ id: p.id, name: `${p.first_name} ${p.last_name} (${p.full_code})` })),
);

function startOfWeek(date: Date): Date {
    const result = new Date(date);
    const day = result.getDay();
    // Monday as the first day of the week.
    const diff = (day === 0 ? -6 : 1) - day;
    result.setDate(result.getDate() + diff);
    result.setHours(0, 0, 0, 0);
    return result;
}

const rangeDates = computed<Date[]>(() => {
    if (mode.value === 'day') {
        return [new Date(anchorDate.value)];
    }

    const start = startOfWeek(anchorDate.value);
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(start);
        d.setDate(d.getDate() + i);
        return d;
    });
});

const dayLabelFormatter = new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });

const columns = computed<AppCalendarColumn[]>(() => {
    if (mode.value === 'day') {
        // props.practitioners is already scoped server-side for a
        // non-super_admin (see ResolvesPractitionerOptions) — no client
        // filtering needed on top of it.
        return props.practitioners.map((practitioner) => ({
            id: practitioner.id,
            label: `${practitioner.first_name} ${practitioner.last_name}`,
            date: toLocalDateString(anchorDate.value),
        }));
    }

    return rangeDates.value.map((date) => ({
        id: toLocalDateString(date),
        label: dayLabelFormatter.format(date),
        date: toLocalDateString(date),
    }));
});

const appointments = ref<AppointmentEntry[]>([]);
const loading = ref(false);

async function reload() {
    loading.value = true;

    const from = mode.value === 'day' ? anchorDate.value : startOfWeek(anchorDate.value);
    // Exclusive upper bound: the day after the last displayed day. A bare
    // YYYY-MM-DD is parsed server-side as midnight, so sending the last
    // day itself would cut off everything after 00:00 on it (and turn the
    // day view into a zero-width window).
    const to = new Date(rangeDates.value[rangeDates.value.length - 1]);
    to.setDate(to.getDate() + 1);

    const params: Record<string, string> = {
        from: toLocalDateString(from),
        to: toLocalDateString(to),
    };

    if (isSuperAdmin.value && selectedCenterId.value) {
        params.center_id = String(selectedCenterId.value);
    }

    if (mode.value === 'week' && selectedPractitionerId.value) {
        params.practitioner_id = String(selectedPractitionerId.value);
    }

    try {
        // AppServiceProvider calls JsonResource::withoutWrapping() globally
        // (see its docblock) — a Resource::collection() returned directly
        // from a controller comes back as a plain array, not {data: [...]}.
        appointments.value = await http.get<AppointmentEntry[]>(
            route('admin.appointments.index', params),
        );
    } finally {
        loading.value = false;
    }
}

watch([mode, anchorDate, selectedCenterId, selectedPractitionerId], reload, { immediate: true });

const statusColor: Record<string, string> = {
    scheduled: 'primary',
    confirmed: 'info',
    completed: 'success',
    cancelled: 'secondary',
    no_show: 'error',
};

// Grid range = the displayed center's opening hours for the shown weekdays,
// widened to fit any loaded appointment (see computeGridHours()).
const displayedCenterId = computed(() => (isSuperAdmin.value ? selectedCenterId.value : props.activeCenterId));

const gridHours = computed(() =>
    computeGridHours(
        props.centerOperatingHours[displayedCenterId.value ?? 0] ?? [],
        rangeDates.value.map((d) => d.getDay()),
        appointments.value,
    ),
);

const events = computed<AppCalendarEvent[]>(() =>
    appointments.value.map((appointment) => ({
        id: appointment.id,
        columnId: mode.value === 'day' ? appointment.practitioner_id : toLocalDateString(new Date(appointment.starts_at)),
        startsAt: appointment.starts_at,
        endsAt: appointment.ends_at,
        title: `${appointment.patient?.first_name ?? ''} ${appointment.patient?.last_name ?? ''}`.trim(),
        subtitle: mode.value === 'week' ? undefined : appointment.practitioner ? `${appointment.practitioner.first_name} ${appointment.practitioner.last_name}` : undefined,
        color: statusColor[appointment.status] ?? 'primary',
        icon: appointment.modality === 'remote' ? modalityIcon(appointment.modality) : undefined,
    })),
);

function goToPrevious() {
    const step = mode.value === 'day' ? 1 : 7;
    const d = new Date(anchorDate.value);
    d.setDate(d.getDate() - step);
    anchorDate.value = d;
}

function goToNext() {
    const step = mode.value === 'day' ? 1 : 7;
    const d = new Date(anchorDate.value);
    d.setDate(d.getDate() + step);
    anchorDate.value = d;
}

function goToToday() {
    anchorDate.value = new Date();
}

// --- Booking dialog ---
const dialogVisible = ref(false);
const editingAppointment = ref<AppointmentEntry | null>(null);
const prefill = ref<{ practitionerId: number | null; date: string | null } | null>(null);

function onSlotClick(payload: { columnId: string | number; date: string; hour: number; minute: number }) {
    editingAppointment.value = null;
    const practitionerId = mode.value === 'day' ? Number(payload.columnId) : selectedPractitionerId.value;
    prefill.value = { practitionerId, date: payload.date };
    dialogVisible.value = true;
}

function onEventClick(event: AppCalendarEvent) {
    const appointment = appointments.value.find((a) => a.id === event.id);
    if (!appointment) return;

    editingAppointment.value = appointment;
    prefill.value = null;
    dialogVisible.value = true;
}

function openNewAppointment() {
    editingAppointment.value = null;
    prefill.value = null;
    dialogVisible.value = true;
}
</script>

<template>
    <Head title="Agenda" />

    <AuthenticatedLayout>
        <AppPageHeader
            title="Agenda"
            :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Agenda' }]"
        >
            <template #actions>
                <AppButton label="Nouveau rendez-vous" icon="mdi-plus" @click="openNewAppointment" />
            </template>
        </AppPageHeader>

        <AppCard variant="elevated" elevation="1" class="mb-4">
            <v-card-text class="d-flex flex-wrap align-end ga-3">
                <v-btn-toggle v-model="mode" mandatory density="comfortable">
                    <v-btn value="day">Jour</v-btn>
                    <v-btn value="week">Semaine</v-btn>
                </v-btn-toggle>

                <AppSelect
                    v-if="isSuperAdmin && centers.length"
                    v-model="selectedCenterId"
                    :options="centerOptions"
                    option-label="name"
                    option-value="id"
                    label="Centre"
                />

                <AppSelect
                    v-if="mode === 'week'"
                    v-model="selectedPractitionerId"
                    :options="practitionerOptions"
                    option-label="name"
                    option-value="id"
                    label="Praticien"
                />

                <div class="d-flex ga-1 align-center">
                    <AppButton icon="mdi-chevron-left" severity="secondary" @click="goToPrevious" />
                    <AppButton label="Aujourd'hui" severity="secondary" @click="goToToday" />
                    <AppButton icon="mdi-chevron-right" severity="secondary" @click="goToNext" />
                </div>
            </v-card-text>
        </AppCard>

        <AppCard variant="elevated" elevation="1">
            <v-card-text>
                <div v-if="loading" class="d-flex justify-center pa-8">
                    <v-progress-circular indeterminate color="primary" />
                </div>
                <AppWeekCalendar
                    v-else
                    :start-hour="gridHours.startHour"
                    :end-hour="gridHours.endHour"
                    :columns="columns" :events="events" @slot-click="onSlotClick" @event-click="onEventClick" />
            </v-card-text>
        </AppCard>

        <AppointmentDialog
            v-model:visible="dialogVisible"
            :appointment="
                editingAppointment
                    ? {
                          id: editingAppointment.id,
                          practitioner_id: editingAppointment.practitioner_id,
                          starts_at: editingAppointment.starts_at,
                          duration_minutes: editingAppointment.duration_minutes,
                          modality: editingAppointment.modality,
                          meeting_link: editingAppointment.meeting_link,
                          reason: editingAppointment.reason,
                      }
                    : null
            "
            :centers="centers"
            :patients="patients"
            :practitioners="practitioners"
            @saved="reload"
        />
    </AuthenticatedLayout>
</template>
