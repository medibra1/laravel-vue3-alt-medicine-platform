<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import TreatmentSessionDialog from '@/Components/Patients/TreatmentSessionDialog.vue';
import TreatmentWizardDialog from '@/Components/Patients/TreatmentWizardDialog.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useResilientForm } from '@/composables/useResilientForm';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Center {
    id: number;
    name: string;
    code: string;
}

interface Patient {
    id: number;
    client_uuid: string;
    first_name: string | null;
    last_name: string | null;
    gender: string | null;
    birth_date: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    intake_center_id: number;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
}

interface TreatmentDisease {
    id: number;
    code: string;
    label: string;
    category_label: string;
}

interface TreatmentSessionSummary {
    id: number;
    session_date: string | null;
    duration_minutes: number | null;
    notes: string | null;
    care_items: { id: number; label: string; category_label: string }[];
    disease_progress: { disease_id: number; disease_label: string; outcome: string | null; outcome_percentage: number | null; notes: string | null }[];
}

interface TreatmentSummary {
    id: number;
    started_at: string | null;
    ended_at: string | null;
    practitioner: { id: number; full_code: string } | null;
    diseases: TreatmentDisease[];
    sessions: TreatmentSessionSummary[];
}

interface PractitionerOption {
    id: number;
    full_code: string;
}

interface DiseaseOption {
    id: number;
    code: string;
    label: string;
    category_id: number;
    category_label: string;
}

interface DiseaseCategoryOption {
    id: number;
    code: string;
    label: string;
}

interface CareItemOption {
    id: number;
    code: string;
    label: string;
}

interface CareCategoryOption {
    id: number;
    code: string;
    label: string;
    items: CareItemOption[];
}

const props = defineProps<{
    patient: Patient | null;
    centers: Center[];
    treatments?: TreatmentSummary[];
    practitioners?: PractitionerOption[];
    diseases?: DiseaseOption[];
    diseaseCategories?: DiseaseCategoryOption[];
    careCategories?: CareCategoryOption[];
}>();

const genderOptions = [
    { label: 'Homme', value: 'male' },
    { label: 'Femme', value: 'female' },
];

const { form, serverId, saving, lastSavedAt, saveErrors, scheduleSave, flush } =
    useResilientForm(
        'patients',
        {
            client_uuid: props.patient?.client_uuid,
            id: props.patient?.id ?? null,
            first_name: props.patient?.first_name ?? null,
            last_name: props.patient?.last_name ?? null,
            gender: props.patient?.gender ?? null,
            birth_date: props.patient?.birth_date ?? null,
            phone: props.patient?.phone ?? null,
            email: props.patient?.email ?? null,
            city: props.patient?.city ?? null,
            intake_center_id: props.patient?.intake_center_id ?? null,
            emergency_contact_name: props.patient?.emergency_contact_name ?? null,
            emergency_contact_phone: props.patient?.emergency_contact_phone ?? null,
            notes: props.patient?.notes ?? null,
        },
        {
            create: route('admin.patients.draft.store'),
            update: (id) => route('admin.patients.draft.update', id),
        },
    );

watch(form, () => scheduleSave(), { deep: true });

const birthDateBinding = computed<Date | null>({
    get: () => (form.birth_date ? new Date(form.birth_date as string) : null),
    set: (value) => {
        form.birth_date = value ? value.toISOString().slice(0, 10) : null;
    },
});

const fieldErrors = computed<Record<string, string>>(() => ({
    ...Object.fromEntries(
        Object.entries(saveErrors.value).map(([field, messages]) => [field, messages[0]]),
    ),
    ...confirmErrors.value,
}));

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

async function confirmPatient() {
    try {
        await flush();
    } catch {
        // saveErrors (from useResilientForm) already carries the detail;
        // stop here instead of navigating to confirm with a not-yet-saved draft.
        return;
    }

    if (serverId.value === null) {
        return;
    }

    confirming.value = true;
    confirmErrors.value = {};

    router.post(
        route('admin.patients.confirm', serverId.value),
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

// --- Treatments section (dossier patient) ---
const wizardVisible = ref(false);
const editingTreatment = ref<{ id: number; client_uuid: string; patient_id: number; practitioner_id: number | null; center_id: number | null; started_at: string | null; ended_at: string | null; outcome: string | null; outcome_percentage: number | null; notes: string | null; disease_ids: number[] } | null>(null);

function openNewTreatment() {
    editingTreatment.value = null;
    wizardVisible.value = true;
}

function editTreatment(treatment: TreatmentSummary) {
    editingTreatment.value = {
        id: treatment.id,
        client_uuid: '',
        patient_id: props.patient!.id,
        practitioner_id: treatment.practitioner?.id ?? null,
        center_id: null,
        started_at: treatment.started_at,
        ended_at: treatment.ended_at,
        outcome: null,
        outcome_percentage: null,
        notes: null,
        disease_ids: treatment.diseases.map((d) => d.id),
    };
    wizardVisible.value = true;
}

function reloadPatient() {
    router.reload({ only: ['patient', 'treatments'] });
}

const sessionDialogVisible = ref(false);
const sessionTreatmentId = ref<number | null>(null);
const editingSession = ref<TreatmentSessionSummary | null>(null);

function openNewSession(treatment: TreatmentSummary) {
    sessionTreatmentId.value = treatment.id;
    editingSession.value = null;
    sessionDialogVisible.value = true;
}

function openEditSession(treatment: TreatmentSummary, session: TreatmentSessionSummary) {
    sessionTreatmentId.value = treatment.id;
    editingSession.value = session;
    sessionDialogVisible.value = true;
}

function treatmentDiseasesFor(treatmentId: number): TreatmentDisease[] {
    return props.treatments?.find((t) => t.id === treatmentId)?.diseases ?? [];
}
</script>

<template>
    <Head :title="patient ? 'Modifier le patient' : 'Nouveau patient'" />

    <AuthenticatedLayout>
        <template #header>{{ patient ? 'Modifier le patient' : 'Nouveau patient' }}</template>

        <div class="mx-auto d-flex flex-column ga-6" style="max-width: 800px">
            <div>
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
                        <form class="d-flex flex-column ga-4" @submit.prevent="confirmPatient">
                            <AppSelect
                                v-if="centers.length"
                                v-model="form.intake_center_id"
                                :options="centers"
                                option-label="name"
                                option-value="id"
                                label="Centre d'accueil"
                                placeholder="Choisir un centre"
                                :error="fieldErrors.intake_center_id"
                            />

                            <AppInputText v-model="form.first_name" label="Prénom" :error="fieldErrors.first_name" />

                            <AppInputText v-model="form.last_name" label="Nom" :error="fieldErrors.last_name" />

                            <AppSelect
                                v-model="form.gender"
                                :options="genderOptions"
                                option-label="label"
                                option-value="value"
                                label="Genre"
                                show-clear
                                placeholder="Non renseigné"
                                :error="fieldErrors.gender"
                            />

                            <AppDatePicker v-model="birthDateBinding" label="Date de naissance" />

                            <AppInputText v-model="form.phone" label="Téléphone" :error="fieldErrors.phone" />

                            <AppInputText v-model="form.email" type="email" label="Email" />

                            <AppInputText v-model="form.city" label="Ville" :error="fieldErrors.city" />

                            <AppInputText v-model="form.emergency_contact_name" label="Contact d'urgence — nom" />

                            <AppInputText v-model="form.emergency_contact_phone" label="Contact d'urgence — téléphone" />

                            <AppTextarea v-model="form.notes" label="Notes" :rows="3" />

                            <div class="d-flex justify-end ga-2">
                                <AppButton
                                    type="button"
                                    label="Retour à la liste"
                                    severity="secondary"
                                    @click="router.get(route('admin.patients.index'))"
                                />
                                <AppButton type="submit" label="Confirmer" :loading="confirming" />
                            </div>
                        </form>
                    </v-card-text>
                </v-card>
            </div>

            <div v-if="patient">
                <div class="d-flex align-center justify-space-between mb-3">
                    <h2 class="text-h6">Traitements</h2>
                    <AppButton label="Ajouter un traitement" @click="openNewTreatment" />
                </div>

                <p v-if="!treatments?.length" class="text-body-2 text-medium-emphasis">
                    Aucun traitement pour ce patient.
                </p>

                <v-card v-for="treatment in treatments" :key="treatment.id" class="mb-4" variant="outlined">
                    <v-card-text>
                        <div class="d-flex justify-space-between align-start mb-2">
                            <div>
                                <p class="text-subtitle-1">
                                    Début : {{ treatment.started_at ? new Date(treatment.started_at).toLocaleDateString() : '—' }}
                                </p>
                                <p class="text-body-2 text-medium-emphasis">
                                    Praticien : {{ treatment.practitioner?.full_code ?? '—' }}
                                </p>
                            </div>
                            <div class="d-flex ga-2">
                                <AppButton
                                    label="Modifier"
                                    severity="secondary"
                                    size="small"
                                    @click="editTreatment(treatment)"
                                />
                                <AppButton
                                    label="Ajouter une séance"
                                    size="small"
                                    @click="openNewSession(treatment)"
                                />
                            </div>
                        </div>

                        <div class="d-flex flex-wrap ga-2 mb-3">
                            <v-chip v-for="disease in treatment.diseases" :key="disease.id" size="small" variant="tonal">
                                {{ disease.code }} — {{ disease.label }}
                            </v-chip>
                        </div>

                        <div v-if="treatment.sessions.length">
                            <p class="text-body-2 font-weight-medium mb-2">Séances</p>
                            <v-card
                                v-for="session in treatment.sessions"
                                :key="session.id"
                                variant="tonal"
                                class="mb-2 pa-3"
                                link
                                @click="openEditSession(treatment, session)"
                            >
                                <p class="text-body-2">
                                    {{ session.session_date ? new Date(session.session_date).toLocaleDateString() : '—' }}
                                    <span v-if="session.duration_minutes"> — {{ session.duration_minutes }} min</span>
                                </p>
                                <p v-if="session.care_items.length" class="text-caption text-medium-emphasis">
                                    Soins : {{ session.care_items.map((i) => i.label).join(', ') }}
                                </p>
                            </v-card>
                        </div>
                    </v-card-text>
                </v-card>
            </div>
        </div>

        <TreatmentWizardDialog
            v-if="patient"
            v-model:visible="wizardVisible"
            :treatment="editingTreatment"
            :patient-id="patient.id"
            :centers="centers"
            :patients="[]"
            :practitioners="practitioners ?? []"
            :diseases="diseases ?? []"
            :disease-categories="diseaseCategories ?? []"
            @saved="reloadPatient"
        />

        <TreatmentSessionDialog
            v-if="sessionTreatmentId"
            v-model:visible="sessionDialogVisible"
            :treatment-id="sessionTreatmentId"
            :session="editingSession"
            :treatment-diseases="treatmentDiseasesFor(sessionTreatmentId)"
            :care-categories="careCategories ?? []"
            @saved="reloadPatient"
        />
    </AuthenticatedLayout>
</template>
