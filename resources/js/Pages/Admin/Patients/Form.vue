<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import TreatmentCloseDialog from '@/Components/Patients/TreatmentCloseDialog.vue';
import TreatmentSessionDialog from '@/Components/Patients/TreatmentSessionDialog.vue';
import TreatmentTimeline from '@/Components/Patients/TreatmentTimeline.vue';
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
    client_uuid: string;
    practitioner_id: number | null;
    center_id: number | null;
    started_at: string | null;
    ended_at: string | null;
    outcome: string | null;
    outcome_percentage: number | null;
    notes: string | null;
    status: string | null;
    closure_reason: string | null;
    practitioner: { id: number; first_name: string; last_name: string; full_code: string } | null;
    diseases: TreatmentDisease[];
    sessions: TreatmentSessionSummary[];
}

interface PractitionerOption {
    id: number;
    first_name: string;
    last_name: string;
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

// TreatmentWizardDialog stays mounted for the page's whole lifetime (its
// `v-if="patient"` never toggles once a patient exists), so its internal
// useResilientForm() only runs its one-time initialization once — without
// this key, re-opening it for a *different* treatment (or a new one after
// an existing one) would keep reusing the very first form state instead of
// the target's actual data. Bumped on every open so each one is a clean
// remount, not just switches between "new" and "edit" mode.
const wizardKey = ref(0);

// A raqi adding a treatment while an old one is still open is exactly the
// confusion this guards against (they meant to log a session/appointment,
// not start a whole new treatment) — the backend enforces this too
// (StoreTreatmentDraftRequest), this just avoids making them fill the
// whole wizard before finding out.
const hasOngoingTreatment = computed(() => (props.treatments ?? []).some((t) => t.status === 'ongoing'));

const treatmentStatusLabels: Record<string, string> = {
    draft: 'Brouillon',
    confirmed: 'Confirmé',
    ongoing: 'En cours',
    closed: 'Fermé',
};

const closureReasonLabels: Record<string, string> = {
    resolved: 'toutes les maladies résolues',
    lost_to_follow_up: 'perdu de vue',
    closed_manually: 'clôture manuelle',
};

function treatmentStatusLabel(treatment: TreatmentSummary): string {
    const base = treatment.status ? (treatmentStatusLabels[treatment.status] ?? treatment.status) : '—';

    if (treatment.status === 'closed' && treatment.closure_reason) {
        return `${base} (${closureReasonLabels[treatment.closure_reason] ?? treatment.closure_reason})`;
    }

    return base;
}

function treatmentStatusColor(treatment: TreatmentSummary): string {
    if (treatment.status === 'ongoing') return 'primary';
    if (treatment.status === 'closed') return treatment.closure_reason === 'resolved' ? 'success' : 'warning';

    return 'secondary';
}

function openNewTreatment() {
    editingTreatment.value = null;
    wizardKey.value++;
    wizardVisible.value = true;
}

function editTreatment(treatment: TreatmentSummary) {
    editingTreatment.value = {
        id: treatment.id,
        client_uuid: treatment.client_uuid,
        patient_id: props.patient!.id,
        practitioner_id: treatment.practitioner_id,
        center_id: treatment.center_id,
        started_at: treatment.started_at,
        ended_at: treatment.ended_at,
        outcome: treatment.outcome,
        outcome_percentage: treatment.outcome_percentage,
        notes: treatment.notes,
        disease_ids: treatment.diseases.map((d) => d.id),
    };
    wizardKey.value++;
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

const closeDialogVisible = ref(false);
const closingTreatmentId = ref<number | null>(null);

function openCloseTreatment(treatment: TreatmentSummary) {
    closingTreatmentId.value = treatment.id;
    closeDialogVisible.value = true;
}

// Reopening reverses an automatic or manual closure (mistaken close, or a
// late session that should have kept the treatment open) — no reason to
// capture, so a plain confirm() is enough (same pattern as destroy()
// elsewhere in this app), no dedicated dialog like TreatmentCloseDialog.
function reopenTreatment(treatment: TreatmentSummary) {
    if (!confirm('Rouvrir ce traitement ? Il redeviendra "en cours" et bloquera à nouveau la création d\'un nouveau traitement pour ce patient.')) {
        return;
    }

    router.post(route('admin.treatments.reopen', treatment.id), {}, { onSuccess: reloadPatient });
}

function treatmentDiseasesFor(treatmentId: number): TreatmentDisease[] {
    return props.treatments?.find((t) => t.id === treatmentId)?.diseases ?? [];
}
</script>

<template>
    <Head :title="patient ? 'Modifier le patient' : 'Nouveau patient'" />

    <AuthenticatedLayout>
        <AppPageHeader
            :title="patient ? 'Modifier le patient' : 'Nouveau patient'"
            :breadcrumbs="[
                { label: 'Tableau de bord', href: route('dashboard') },
                { label: 'Patients', href: route('admin.patients.index') },
                { label: patient ? 'Modifier' : 'Nouveau' },
            ]"
        />

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

                <AppCard variant="elevated" elevation="1">
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
                </AppCard>
            </div>

            <div v-if="patient">
                <div class="d-flex align-center justify-space-between mb-1">
                    <h2 class="text-h6">Traitements</h2>
                    <AppButton label="Ajouter un traitement" icon="mdi-plus" :disabled="hasOngoingTreatment" @click="openNewTreatment" />
                </div>

                <p v-if="hasOngoingTreatment" class="text-body-2 text-medium-emphasis mb-3">
                    Un traitement est en cours — clôturez-le avant d'en ajouter un nouveau.
                </p>

                <p v-if="!treatments?.length" class="text-body-2 text-medium-emphasis">
                    Aucun traitement pour ce patient.
                </p>

                <AppCard v-for="treatment in treatments" :key="treatment.id" class="mb-4" variant="outlined">
                    <v-card-text>
                        <div class="d-flex justify-space-between align-start mb-2">
                            <div>
                                <div class="d-flex align-center ga-2 mb-1">
                                    <p class="text-subtitle-1 mb-0">
                                        Début : {{ treatment.started_at ? new Date(treatment.started_at).toLocaleDateString() : '—' }}
                                    </p>
                                    <v-chip size="small" :color="treatmentStatusColor(treatment)" variant="tonal">
                                        {{ treatmentStatusLabel(treatment) }}
                                    </v-chip>
                                </div>
                                <p class="text-body-2 text-medium-emphasis">
                                    Praticien :
                                    {{
                                        treatment.practitioner
                                            ? `${treatment.practitioner.first_name} ${treatment.practitioner.last_name} (${treatment.practitioner.full_code})`
                                            : '—'
                                    }}
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
                                    v-if="treatment.status === 'ongoing'"
                                    label="Ajouter une séance"
                                    size="small"
                                    @click="openNewSession(treatment)"
                                />
                                <AppButton
                                    v-if="treatment.status === 'ongoing'"
                                    label="Clôturer"
                                    severity="secondary"
                                    size="small"
                                    @click="openCloseTreatment(treatment)"
                                />
                                <AppButton
                                    v-if="treatment.status === 'closed'"
                                    label="Rouvrir"
                                    severity="secondary"
                                    size="small"
                                    @click="reopenTreatment(treatment)"
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
                            <TreatmentTimeline
                                :sessions="treatment.sessions"
                                :treatment-status="treatment.status ?? ''"
                                @edit-session="(session) => openEditSession(treatment, session)"
                            />
                        </div>
                    </v-card-text>
                </AppCard>
            </div>
        </div>

        <TreatmentWizardDialog
            v-if="patient"
            :key="wizardKey"
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

        <TreatmentCloseDialog
            v-if="closingTreatmentId"
            v-model:visible="closeDialogVisible"
            :treatment-id="closingTreatmentId"
            @saved="reloadPatient"
        />
    </AuthenticatedLayout>
</template>
