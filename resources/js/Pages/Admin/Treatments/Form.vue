<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useResilientForm } from '@/composables/useResilientForm';
import { Head, router } from '@inertiajs/vue3';
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
    full_code: string;
}

interface DiseaseOption {
    id: number;
    code: string;
    label: string;
}

interface Treatment {
    id: number;
    client_uuid: string;
    patient_id: number;
    practitioner_id: number | null;
    center_id: number | null;
    started_at: string | null;
    ended_at: string | null;
    outcome: string | null;
    outcome_percentage: number | null;
    notes: string | null;
    disease_ids: number[];
}

const props = defineProps<{
    treatment: Treatment | null;
    centers: Center[];
    patients: PatientOption[];
    practitioners: PractitionerOption[];
    diseases: DiseaseOption[];
}>();

const outcomeOptions = [
    { label: 'Guéri', value: 'cured' },
    { label: 'Non guéri', value: 'not_cured' },
    { label: 'Pourcentage', value: 'percentage' },
];

const patientOptions = props.patients.map((patient) => ({
    id: patient.id,
    name: `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim(),
}));

const diseaseOptions = props.diseases.map((disease) => ({
    id: disease.id,
    name: `${disease.code} — ${disease.label}`,
}));

const { form, serverId, saving, lastSavedAt, saveErrors, scheduleSave, flush } =
    useResilientForm(
        'treatments',
        {
            client_uuid: props.treatment?.client_uuid,
            id: props.treatment?.id ?? null,
            patient_id: props.treatment?.patient_id ?? null,
            practitioner_id: props.treatment?.practitioner_id ?? null,
            center_id: props.treatment?.center_id ?? null,
            started_at: props.treatment?.started_at ?? null,
            ended_at: props.treatment?.ended_at ?? null,
            outcome: props.treatment?.outcome ?? null,
            outcome_percentage: props.treatment?.outcome_percentage ?? null,
            notes: props.treatment?.notes ?? null,
            disease_ids: props.treatment?.disease_ids ?? [],
        },
        {
            create: route('admin.treatments.draft.store'),
            update: (id) => route('admin.treatments.draft.update', id),
        },
    );

watch(form, () => scheduleSave(), { deep: true });

function dateBinding(field: 'started_at' | 'ended_at') {
    return computed<Date | null>({
        get: () => (form[field] ? new Date(form[field] as string) : null),
        set: (value) => {
            form[field] = value ? value.toISOString().slice(0, 10) : null;
        },
    });
}

const startedAtBinding = dateBinding('started_at');
const endedAtBinding = dateBinding('ended_at');

const savedLabel = computed(() => {
    if (saving.value) {
        return 'Enregistrement…';
    }

    if (!lastSavedAt.value) {
        return 'Pas encore enregistré';
    }

    const time = new Date(lastSavedAt.value).toLocaleTimeString();

    return `Brouillon enregistré à ${time}`;
});

const confirming = ref(false);
const confirmErrors = ref<Record<string, string>>({});

const fieldErrors = computed<Record<string, string>>(() => ({
    ...Object.fromEntries(
        Object.entries(saveErrors.value).map(([field, messages]) => [field, messages[0]]),
    ),
    ...confirmErrors.value,
}));

async function confirmTreatment() {
    try {
        await flush();
    } catch {
        return;
    }

    if (serverId.value === null) {
        return;
    }

    confirming.value = true;
    confirmErrors.value = {};

    router.post(
        route('admin.treatments.confirm', serverId.value),
        { ...form },
        {
            onError: (errors) => {
                confirmErrors.value = errors as Record<string, string>;
            },
            onFinish: () => {
                confirming.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="treatment ? 'Modifier le traitement' : 'Nouveau traitement'" />

    <AuthenticatedLayout>
        <template #header>{{ treatment ? 'Modifier le traitement' : 'Nouveau traitement' }}</template>

        <div class="mx-auto" style="max-width: 640px">
            <p class="text-body-2 text-medium-emphasis mb-4">{{ savedLabel }}</p>

            <v-alert
                v-if="Object.keys(saveErrors).length"
                type="error"
                variant="tonal"
                class="mb-4"
                title="Enregistrement impossible"
            >
                <ul class="ps-4">
                    <li v-for="(messages, field) in saveErrors" :key="field">{{ messages[0] }}</li>
                </ul>
            </v-alert>

            <v-card>
                <v-card-text>
                    <form class="d-flex flex-column ga-4" @submit.prevent="confirmTreatment">
                        <AppSelect
                            v-if="centers.length"
                            v-model="form.center_id"
                            :options="centers"
                            option-label="name"
                            option-value="id"
                            label="Centre"
                            placeholder="Choisir un centre"
                            :error="fieldErrors.center_id"
                        />

                        <AppSelect
                            v-model="form.patient_id"
                            :options="patientOptions"
                            option-label="name"
                            option-value="id"
                            label="Patient"
                            placeholder="Choisir un patient"
                            :error="fieldErrors.patient_id"
                        />

                        <AppSelect
                            v-model="form.practitioner_id"
                            :options="practitioners"
                            option-label="full_code"
                            option-value="id"
                            label="Praticien"
                            placeholder="Choisir un praticien"
                            :error="fieldErrors.practitioner_id"
                        />

                        <AppSelect
                            v-model="form.disease_ids"
                            :options="diseaseOptions"
                            option-label="name"
                            option-value="id"
                            label="Maladies traitées"
                            placeholder="Choisir une ou plusieurs maladies"
                            multiple
                            :error="fieldErrors.disease_ids"
                        />

                        <AppDatePicker
                            v-model="startedAtBinding"
                            label="Date de début"
                            :error="fieldErrors.started_at"
                        />

                        <AppDatePicker v-model="endedAtBinding" label="Date de fin" />

                        <AppSelect
                            v-model="form.outcome"
                            :options="outcomeOptions"
                            option-label="label"
                            option-value="value"
                            label="Issue"
                            show-clear
                            placeholder="Non renseignée"
                        />

                        <AppInputNumber
                            v-if="form.outcome === 'percentage'"
                            v-model="form.outcome_percentage"
                            label="Pourcentage"
                            :min="1"
                            :max="99"
                            :error="fieldErrors.outcome_percentage"
                        />

                        <AppTextarea v-model="form.notes" label="Notes" :rows="3" />

                        <div class="d-flex justify-end ga-2">
                            <AppButton
                                type="button"
                                label="Retour à la liste"
                                severity="secondary"
                                @click="router.get(route('admin.treatments.index'))"
                            />
                            <AppButton type="submit" label="Confirmer" :loading="confirming" />
                        </div>
                    </form>
                </v-card-text>
            </v-card>
        </div>
    </AuthenticatedLayout>
</template>
