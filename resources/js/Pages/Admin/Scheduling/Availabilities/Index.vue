<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import BulkApplyScheduleDialog from '@/Components/Scheduling/BulkApplyScheduleDialog.vue';
import CenterOperatingHoursDialog from '@/Components/Scheduling/CenterOperatingHoursDialog.vue';
import PractitionerWeeklyScheduleDialog from '@/Components/Scheduling/PractitionerWeeklyScheduleDialog.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { type WeeklySlot, dayLabels, displayDayOrder, shortDayLabels, summarizeSchedule } from '@/utils/weeklySchedule';
import { computed, ref } from 'vue';

interface Practitioner {
    id: number;
    first_name: string;
    last_name: string;
    full_code: string;
}

interface Availability {
    id: number;
    practitioner_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    practitioner?: Practitioner;
}

const props = defineProps<{
    availabilities: Availability[];
    practitioners: Practitioner[];
    editableCenters: { id: number; name: string; operating_hours: WeeklySlot[] }[];
}>();

const hoursDialogVisible = ref(false);
const hoursCenterId = ref<number | null>(props.editableCenters[0]?.id ?? null);
const hoursCenter = computed(() => props.editableCenters.find((c) => c.id === hoursCenterId.value) ?? null);

const dayOptions = dayLabels.map((label, id) => ({ id, name: label }));

const practitionerOptions = props.practitioners.map((practitioner) => ({
    id: practitioner.id,
    name: practitionerName(practitioner),
}));

function practitionerName(practitioner: Practitioner): string {
    return `${practitioner.first_name} ${practitioner.last_name} (${practitioner.full_code})`;
}

/**
 * One row per practitioner (including those with no hours yet), slots
 * sorted Monday → Sunday then by start time.
 */
const schedules = computed(() =>
    props.practitioners.map((practitioner) => ({
        practitioner,
        slots: props.availabilities
            .filter((availability) => availability.practitioner_id === practitioner.id)
            .sort(
                (a, b) =>
                    displayDayOrder.indexOf(a.day_of_week) - displayDayOrder.indexOf(b.day_of_week) ||
                    a.start_time.localeCompare(b.start_time),
            ),
    })),
);

const editingPractitioner = ref<Practitioner | null>(null);
const isEditingSchedule = ref(false);
const editingSlots = computed(() => (editingPractitioner.value ? schedules.value.find((s) => s.practitioner.id === editingPractitioner.value!.id)?.slots ?? [] : []));

function openSchedule(practitioner: Practitioner) {
    editingPractitioner.value = practitioner;
    isEditingSchedule.value = true;
}

const isBulkApplying = ref(false);

const isCreating = ref(false);

const createForm = useForm({
    practitioner_id: null as number | null,
    day_of_week: null as number | null,
    start_time: '',
    end_time: '',
});

function openCreate() {
    createForm.reset();
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.availabilities.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

function destroy(availability: Availability) {
    if (!confirm('Supprimer ce créneau de disponibilité ?')) {
        return;
    }

    router.delete(route('admin.availabilities.destroy', availability.id));
}
</script>

<template>
    <Head title="Disponibilités" />

    <AuthenticatedLayout>
        <AppPageHeader title="Disponibilités" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Disponibilités' }]">
            <template #actions>
                <AppSelect
                    v-if="editableCenters.length > 1"
                    v-model="hoursCenterId"
                    :options="editableCenters"
                    option-label="name"
                    option-value="id"
                    label="Centre"
                />
                <AppButton
                    v-if="hoursCenter"
                    label="Horaires du centre"
                    icon="mdi-clock-outline"
                    severity="secondary"
                    @click="hoursDialogVisible = true"
                />
                <AppButton label="Appliquer un planning à plusieurs praticiens" icon="mdi-account-multiple" severity="secondary" @click="isBulkApplying = true" />
                <AppButton label="Nouveau créneau" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <div class="d-flex flex-column ga-3">
            <p v-if="!schedules.length" class="text-medium-emphasis">Aucun praticien.</p>
            <AppCard v-for="{ practitioner, slots } in schedules" :key="practitioner.id" variant="elevated" elevation="1">
                <v-card-text>
                    <div class="d-flex justify-space-between align-start flex-wrap ga-2">
                        <div style="min-width: 0">
                            <div class="text-subtitle-1 font-weight-medium">{{ practitionerName(practitioner) }}</div>
                            <div class="text-body-2 text-medium-emphasis">{{ slots.length ? summarizeSchedule(slots) : 'Aucune disponibilité' }}</div>
                        </div>
                        <AppButton label="Gérer le planning" icon="mdi-calendar-edit" size="small" @click="openSchedule(practitioner)" />
                    </div>
                    <div v-if="slots.length" class="d-flex flex-wrap ga-1 mt-3">
                        <v-chip v-for="slot in slots" :key="slot.id" size="small" closable @click:close="destroy(slot)">
                            {{ shortDayLabels[slot.day_of_week] }} {{ slot.start_time }}-{{ slot.end_time }}
                        </v-chip>
                    </div>
                </v-card-text>
            </AppCard>
        </div>

        <PractitionerWeeklyScheduleDialog v-model:visible="isEditingSchedule" :practitioner="editingPractitioner" :initial-slots="editingSlots" />
        <BulkApplyScheduleDialog v-model:visible="isBulkApplying" :practitioner-options="practitionerOptions" />

        <AppDialog v-model:visible="isCreating" header="Nouveau créneau de disponibilité">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppSelect
                    v-model="createForm.practitioner_id"
                    :options="practitionerOptions"
                    option-label="name"
                    option-value="id"
                    label="Praticien"
                    :error="createForm.errors.practitioner_id"
                />

                <AppSelect
                    v-model="createForm.day_of_week"
                    :options="dayOptions"
                    option-label="name"
                    option-value="id"
                    label="Jour de la semaine"
                    :error="createForm.errors.day_of_week"
                />

                <v-row>
                    <v-col cols="6">
                        <AppInputText v-model="createForm.start_time" type="time" label="Heure de début" :error="createForm.errors.start_time" />
                    </v-col>
                    <v-col cols="6">
                        <AppInputText v-model="createForm.end_time" type="time" label="Heure de fin" :error="createForm.errors.end_time" />
                    </v-col>
                </v-row>

                <div class="d-flex justify-end ga-2">
                    <AppButton type="button" label="Annuler" severity="secondary" @click="isCreating = false" />
                    <AppButton type="submit" label="Créer" :loading="createForm.processing" />
                </div>
            </form>
        </AppDialog>
        <CenterOperatingHoursDialog
            v-model:visible="hoursDialogVisible"
            :center="hoursCenter"
            :initial-slots="hoursCenter?.operating_hours ?? []"
        />
    </AuthenticatedLayout>
</template>
