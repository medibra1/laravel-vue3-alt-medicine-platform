<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import { http } from '@/lib/http';
import { toLocalDateString } from '@/utils/date';
import { modalityOptions } from '@/utils/modality';
import { useForm } from '@inertiajs/vue3';
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

interface TreatmentOption {
    id: number;
    status: string | null;
}

interface Slot {
    starts_at: string;
    ends_at: string;
}

/**
 * A brand-new booking (id === null) vs. a reschedule of an existing one —
 * plain CRUD, not the resilient-wizard draft/confirm dance (an
 * appointment is a short one-shot booking, not a long form worth
 * autosaving, same reasoning already applied to TreatmentSessionDialog).
 */
interface AppointmentToEdit {
    id: number;
    practitioner_id: number;
    starts_at: string;
    duration_minutes: number;
    modality: string | null;
    meeting_link: string | null;
    reason: string | null;
}

const props = withDefaults(
    defineProps<{
        visible: boolean;
        appointment?: AppointmentToEdit | null;
        /** Pre-selects and locks the patient when opened from the patient file. */
        patientId?: number | null;
        /** Empty for a manager/practitioner (center is forced server-side) — only a super_admin picks one, same convention as TreatmentWizardDialog. */
        centers?: Center[];
        patients: PatientOption[];
        practitioners: PractitionerOption[];
        /** Open (non-closed) treatments of the currently selected patient. */
        treatments?: TreatmentOption[];
    }>(),
    { appointment: null, patientId: null, centers: () => [], treatments: () => [] },
);

const emit = defineEmits<{ 'update:visible': [value: boolean]; saved: [] }>();

const patientOptions = computed(() =>
    props.patients.map((patient) => ({
        id: patient.id,
        name: `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim(),
    })),
);

const practitionerOptions = computed(() =>
    props.practitioners.map((practitioner) => ({
        id: practitioner.id,
        name: `${practitioner.first_name} ${practitioner.last_name} (${practitioner.full_code})`,
    })),
);

// Only treatments still open make sense to attach a new appointment to —
// a closed one is done, booking against it would be confusing.
const openTreatmentOptions = computed(() =>
    props.treatments
        .filter((treatment) => treatment.status !== 'closed')
        .map((treatment) => ({ id: treatment.id, name: `Traitement #${treatment.id}` })),
);

const form = useForm({
    center_id: null as number | null,
    patient_id: null as number | null,
    practitioner_id: null as number | null,
    treatment_id: null as number | null,
    starts_at: null as string | null,
    duration_minutes: 30,
    modality: 'in_person' as string,
    meeting_link: null as string | null,
    reason: null as string | null,
});

const selectedDate = ref<Date | null>(null);
const selectedTime = ref<string | null>(null);
const slots = ref<Slot[]>([]);
const loadingSlots = ref(false);

const slotOptions = computed(() =>
    slots.value.map((slot) => ({
        value: slot.starts_at,
        label: new Date(slot.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    })),
);

function resetForm() {
    form.clearErrors();
    form.center_id = null;
    form.patient_id = props.appointment ? null : (props.patientId ?? null);
    form.practitioner_id = props.appointment?.practitioner_id ?? null;
    form.treatment_id = null;
    form.duration_minutes = props.appointment?.duration_minutes ?? 30;
    form.modality = props.appointment?.modality ?? 'in_person';
    form.meeting_link = props.appointment?.meeting_link ?? null;
    form.reason = props.appointment?.reason ?? null;

    const initialDate = props.appointment ? new Date(props.appointment.starts_at) : null;
    selectedDate.value = initialDate;
    selectedTime.value = props.appointment?.starts_at ?? null;
    slots.value = [];
}

watch(() => props.visible, (visible) => visible && resetForm(), { immediate: true });

// A patient picked from the patient file is fixed for the whole dialog —
// re-selecting on reschedule isn't offered (an appointment stays tied to
// its original patient, see StoreAppointmentRequest's authoritative rules).
const patientLocked = computed(() => props.patientId !== null || props.appointment !== null);

async function fetchSlots() {
    if (!form.practitioner_id || !selectedDate.value || !form.duration_minutes) {
        slots.value = [];
        return;
    }

    loadingSlots.value = true;

    try {
        const response = await http.get<{ slots: Slot[] }>(
            route('admin.appointments.available-slots', {
                practitioner_id: form.practitioner_id,
                date: toLocalDateString(selectedDate.value),
                duration_minutes: form.duration_minutes,
            }),
        );
        slots.value = response.slots;
    } catch {
        slots.value = [];
    } finally {
        loadingSlots.value = false;
    }
}

watch([() => form.practitioner_id, selectedDate, () => form.duration_minutes], fetchSlots);

watch(selectedTime, (value) => {
    form.starts_at = value;
});

function close() {
    emit('update:visible', false);
}

function save() {
    form.starts_at = selectedTime.value;

    const options = {
        onSuccess: () => {
            emit('saved');
            emit('update:visible', false);
        },
    };

    if (props.appointment) {
        form
            .transform((data) => ({
                practitioner_id: data.practitioner_id,
                starts_at: data.starts_at,
                duration_minutes: data.duration_minutes,
                modality: data.modality,
                meeting_link: data.meeting_link,
                reason: data.reason,
            }))
            .put(route('admin.appointments.update', props.appointment.id), options);
    } else {
        form.post(route('admin.appointments.store'), options);
    }
}
</script>

<template>
    <AppDialog :visible="visible" :header="appointment ? 'Modifier le rendez-vous' : 'Nouveau rendez-vous'" max-width="560px" @update:visible="close">
        <div class="d-flex flex-column ga-4">
            <AppSelect
                v-if="centers.length && !appointment"
                v-model="form.center_id"
                :options="centers"
                option-label="name"
                option-value="id"
                label="Centre"
                :error="form.errors.center_id"
            />

            <AppSelect
                v-model="form.patient_id"
                :options="patientOptions"
                option-label="name"
                option-value="id"
                label="Patient"
                :disabled="patientLocked"
                :error="form.errors.patient_id"
            />

            <AppSelect
                v-model="form.practitioner_id"
                :options="practitionerOptions"
                option-label="name"
                option-value="id"
                label="Praticien"
                :error="form.errors.practitioner_id"
            />

            <AppSelect
                v-if="openTreatmentOptions.length"
                v-model="form.treatment_id"
                :options="openTreatmentOptions"
                option-label="name"
                option-value="id"
                label="Traitement (optionnel)"
                show-clear
                placeholder="Aucun traitement"
            />

            <AppDatePicker v-model="selectedDate" label="Date" />

            <AppSelect
                v-model="selectedTime"
                :options="slotOptions"
                option-label="label"
                option-value="value"
                label="Créneau"
                :placeholder="loadingSlots ? 'Chargement…' : 'Choisir un créneau'"
                :disabled="!form.practitioner_id || !selectedDate"
                :error="form.errors.starts_at"
            />

            <AppInputNumber v-model="form.duration_minutes" label="Durée (minutes)" :min="5" :max="480" :error="form.errors.duration_minutes" />

            <AppSelect
                v-model="form.modality"
                :options="modalityOptions"
                option-label="label"
                option-value="value"
                label="Modalité"
                :error="form.errors.modality"
            />

            <AppInputText
                v-if="form.modality === 'remote'"
                v-model="form.meeting_link"
                label="Lien / contact de visio"
                placeholder="https://..."
                :error="form.errors.meeting_link ?? undefined"
            />

            <AppTextarea v-model="form.reason" label="Motif" :rows="2" :error="form.errors.reason ?? undefined" />

            <div class="d-flex justify-end ga-2">
                <AppButton type="button" label="Annuler" severity="secondary" @click="close" />
                <AppButton type="button" label="Enregistrer" :loading="form.processing" @click="save" />
            </div>
        </div>
    </AppDialog>
</template>
