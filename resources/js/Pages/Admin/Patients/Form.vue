<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppTabs, { type AppTabItem } from '@/Components/App/AppTabs.vue';
import PatientConsentsTab from '@/Components/Patients/PatientConsentsTab.vue';
import PatientDocumentsTab from '@/Components/Patients/PatientDocumentsTab.vue';
import PatientInfoForm from '@/Components/Patients/PatientInfoForm.vue';
import PatientStatusChip from '@/Components/Patients/PatientStatusChip.vue';
import TreatmentCard from '@/Components/Patients/TreatmentCard.vue';
import TreatmentCloseDialog from '@/Components/Patients/TreatmentCloseDialog.vue';
import TreatmentSessionDialog from '@/Components/Patients/TreatmentSessionDialog.vue';
import TreatmentWizardDialog from '@/Components/Patients/TreatmentWizardDialog.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useResilientForm } from '@/composables/useResilientForm';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface Center {
    id: number;
    name: string;
    code: string;
}

interface DerivedStatus {
    key: string;
    label: string;
    color: string;
}

interface Patient {
    id: number;
    client_uuid: string;
    first_name: string | null;
    last_name: string | null;
    gender: string | null;
    marital_status: string | null;
    children_count: number | null;
    religion_option_id: number | null;
    birth_date: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    intake_center_id: number;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    derived_status?: DerivedStatus;
}

interface ReligionOption {
    id: number;
    code: string;
    label: string;
}

interface TreatmentDisease {
    id: number;
    code: string;
    label: string;
    category_label: string;
    actively_tracked: boolean;
}

interface TreatmentSessionSummary {
    id: number;
    session_date: string | null;
    duration_minutes: number | null;
    notes: string | null;
    care_items: { id: number; label: string; category_label: string }[];
    disease_progress: { disease_id: number; disease_label: string; outcome: string | null; outcome_percentage: number | null; notes: string | null }[];
    measurements: { measurement_type_option_id: number; measurement_type_code: string; measurement_type_label: string; value: string; unit: string | null; notes: string | null }[];
}

interface MeasurementTypeOption {
    id: number;
    code: string;
    label: string;
    unit: string | null;
    placeholder: string | null;
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
    locked_disease_ids: number[];
    latest_known_outcomes: Record<number, { outcome: string | null; outcome_percentage: number | null; notes: string | null }>;
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
    description: string | null;
    category_id: number;
    category_label: string;
}

interface DiseaseCategoryOption {
    id: number;
    code: string;
    label: string;
    icon: string | null;
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

interface PatientDocument {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    download_url: string;
    thumb_url: string | null;
    treatment_session_id: number | null;
    created_at: string;
}

interface PatientDocuments {
    identity: PatientDocument | null;
    medical: PatientDocument[];
    other: PatientDocument[];
}

type ConsentType = 'treatment' | 'data_privacy' | 'image_rights';

interface ConsentTemplate {
    type: ConsentType;
    title: string;
    content: string;
    version: number;
}

interface Consent {
    id: number;
    type: ConsentType;
    version: number;
    template_version: number;
    signer_name: string;
    accepted_at: string;
    accepted_by: string;
    download_url: string;
}

const props = defineProps<{
    patient: Patient | null;
    centers: Center[];
    treatments?: TreatmentSummary[];
    practitioners?: PractitionerOption[];
    diseases?: DiseaseOption[];
    diseaseCategories?: DiseaseCategoryOption[];
    careCategories?: CareCategoryOption[];
    religionOptions?: ReligionOption[];
    measurementTypes?: MeasurementTypeOption[];
    can_update: boolean;
    documents?: PatientDocuments;
    consents?: Consent[];
    consentTemplates?: ConsentTemplate[];
}>();

const { form, serverId, saving, lastSavedAt, saveErrors, scheduleSave, flush } =
    useResilientForm(
        'patients',
        {
            client_uuid: props.patient?.client_uuid,
            id: props.patient?.id ?? null,
            first_name: props.patient?.first_name ?? null,
            last_name: props.patient?.last_name ?? null,
            gender: props.patient?.gender ?? null,
            marital_status: props.patient?.marital_status ?? null,
            children_count: props.patient?.children_count ?? null,
            religion_option_id: props.patient?.religion_option_id ?? null,
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
const editingTreatment = ref<{ id: number; client_uuid: string; patient_id: number; practitioner_id: number | null; center_id: number | null; started_at: string | null; ended_at: string | null; outcome: string | null; outcome_percentage: number | null; notes: string | null; disease_ids: number[]; actively_tracked_disease_ids: number[] } | null>(null);
// Diseases already tracked by a session on the treatment being edited —
// passed to TreatmentWizardDialog so it can lock their checkboxes. Always
// empty for a brand-new treatment (nothing tracked yet).
const editingTreatmentLockedDiseaseIds = ref<number[]>([]);

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
const ongoingTreatment = computed(() => (props.treatments ?? []).find((t) => t.status === 'ongoing') ?? null);

const pastTreatments = computed(() => (props.treatments ?? []).filter((t) => t.status !== 'ongoing'));

// --- Tabs (patient file navigation) ---
// The Documents tab only needs a patient id to work (upload/list attach to
// any existing Patient row regardless of draft/confirmed status) — usable
// as soon as the very first storeDraft() succeeds, not gated behind
// confirmation like the rest of the file. `patient` itself stays null for
// the whole /admin/patients/create session (Inertia never reloads this
// page after storeDraft(), the id only ever lives in serverId from
// useResilientForm) — effectivePatientId is the id to use everywhere a
// patient id is needed before a page reload, patient.id/`patient` itself
// stays the source of truth for anything that actually needs the loaded
// record (treatments, derived_status...).
const effectivePatientId = computed(() => props.patient?.id ?? serverId.value);

const tabs: AppTabItem[] = [
    { title: 'Informations', value: 'informations' },
    { title: 'Traitement en cours', value: 'ongoing' },
    { title: 'Documents', value: 'documents' },
    { title: 'Consentement', value: 'consent' },
    { title: 'Historique', value: 'history' },
];

const urlParams = new URLSearchParams(window.location.search);
const tabFromUrl = urlParams.get('tab');

// Defaults to "informations" on arrival, unless the patient already has an
// ongoing treatment — in that case the raqi almost always came here to log
// a session or close it, not to re-edit identity fields.
const activeTab = ref<string>(
    tabFromUrl && tabs.some((tab) => tab.value === tabFromUrl)
        ? tabFromUrl
        : ongoingTreatment.value
          ? 'ongoing'
          : 'informations',
);

watch(activeTab, (value) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', value);
    window.history.replaceState({}, '', url);
});

function openNewTreatment() {
    editingTreatment.value = null;
    editingTreatmentLockedDiseaseIds.value = [];
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
        actively_tracked_disease_ids: treatment.diseases.filter((d) => d.actively_tracked).map((d) => d.id),
    };
    editingTreatmentLockedDiseaseIds.value = treatment.locked_disease_ids;
    wizardKey.value++;
    wizardVisible.value = true;
}

function reloadPatient() {
    router.reload({ only: ['patient', 'treatments', 'documents', 'consents'] });
}

// Only ever set by PatientController::confirm()'s redirect, right after a
// patient is first confirmed (draft -> confirmed happens once in a
// patient's lifecycle) — auto-opens the treatment wizard so the raqi isn't
// forced to click "Ajouter un traitement" as an extra step. Skipped if a
// treatment somehow already exists (shouldn't happen right after
// confirmation, but ongoingTreatment is the single source of truth here).
// `open` is stripped from the URL either way, so a page reload never
// re-opens the wizard on its own.
//
// Checked on Inertia's `navigate` event, not just onMounted: confirming a
// patient redirects back to this same route/component, so Inertia reuses
// the existing Form.vue instance instead of remounting it — onMounted alone
// would never fire for this navigation.
function checkOpenTreatmentParam() {
    const params = new URLSearchParams(window.location.search);

    if (params.get('open') === 'treatment' && !ongoingTreatment.value) {
        openNewTreatment();
    }

    if (params.has('open')) {
        const url = new URL(window.location.href);
        url.searchParams.delete('open');
        window.history.replaceState({}, '', url);
    }
}

onMounted(checkOpenTreatmentParam);

const removeNavigateListener = router.on('navigate', checkOpenTreatmentParam);
onUnmounted(removeNavigateListener);

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

// TreatmentTimeline already confirms via window.confirm() before emitting
// this — no second confirmation here.
function deleteSession(treatment: TreatmentSummary, session: TreatmentSessionSummary) {
    router.delete(route('admin.treatments.sessions.destroy', [treatment.id, session.id]), {
        onSuccess: reloadPatient,
    });
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

// Only actively tracked diseases are handed to TreatmentSessionDialog — a
// disease the treatment merely records but doesn't actively follow has no
// required evaluation at each session, so it never appears in that form.
function treatmentDiseasesFor(treatmentId: number): TreatmentDisease[] {
    return (props.treatments?.find((t) => t.id === treatmentId)?.diseases ?? []).filter(
        (disease) => disease.actively_tracked,
    );
}

function latestKnownOutcomesFor(treatmentId: number): TreatmentSummary['latest_known_outcomes'] {
    return props.treatments?.find((t) => t.id === treatmentId)?.latest_known_outcomes ?? {};
}

// Medical documents already show up on the Documents tab (the single
// source of truth) — this just also surfaces the subset tagged with this
// particular session's id, so a practitioner reviewing/editing a session
// doesn't have to jump to another tab to see what was attached.
function medicalDocumentsForSession(sessionId: number): PatientDocument[] {
    return (props.documents?.medical ?? []).filter((doc) => doc.treatment_session_id === sessionId);
}

const pageTitle = computed(() =>
    props.patient ? `${props.patient.first_name ?? ''} ${props.patient.last_name ?? ''}`.trim() : 'Nouveau patient',
);

// Clickable only when the latest treatment is still ongoing — a closed
// treatment's reopen action already lives on its own TreatmentCard in the
// Historique tab (Treatment::reopen()), not duplicated here. Reuses
// openCloseTreatment() rather than a second dialog-opening path, same
// dialog the "Clôturer" button on the ongoing TreatmentCard already opens.
function onStatusChipClick() {
    if (ongoingTreatment.value) {
        openCloseTreatment(ongoingTreatment.value);
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <AppPageHeader
            :title="pageTitle"
            :breadcrumbs="[
                { label: 'Tableau de bord', href: route('dashboard') },
                { label: 'Patients', href: route('admin.patients.index') },
                { label: pageTitle },
            ]"
        >
            <template v-if="patient?.derived_status" #title-suffix>
                <PatientStatusChip :status="patient.derived_status" :clickable="!!ongoingTreatment" @click="onStatusChipClick" />
            </template>
        </AppPageHeader>

        <div class="mx-auto" style="max-width: 800px">
            <AppTabs v-if="effectivePatientId" v-model="activeTab" :tabs="tabs">
                <template #informations>
                    <PatientInfoForm
                        :form="form"
                        :centers="centers"
                        :religion-options="religionOptions ?? []"
                        :field-errors="fieldErrors"
                        :saved-label="savedLabel"
                        :save-errors="saveErrors"
                        :confirming="confirming"
                        :readonly="!can_update"
                        @confirm="confirmPatient"
                        @cancel="router.get(route('admin.patients.index'))"
                    />
                </template>

                <template #ongoing>
                    <div v-if="patient">
                        <div class="d-flex align-center justify-space-between mb-1">
                            <h2 class="text-h6">Traitement en cours</h2>
                            <AppButton
                                v-if="!ongoingTreatment && can_update"
                                label="Ajouter un traitement"
                                icon="mdi-plus"
                                @click="openNewTreatment"
                            />
                        </div>

                        <p v-if="!ongoingTreatment" class="text-body-2 text-medium-emphasis">
                            Aucun traitement en cours pour ce patient.
                        </p>

                        <TreatmentCard
                            v-else
                            :treatment="ongoingTreatment"
                            @edit="editTreatment"
                            @add-session="openNewSession"
                            @close="openCloseTreatment"
                            @reopen="reopenTreatment"
                            @edit-session="openEditSession"
                            @delete-session="deleteSession"
                        />
                    </div>
                    <!-- Patient not yet confirmed (still on /admin/patients/create, no
                         server reload has happened yet) — treatments need a real
                         confirmed patient, not just an id, so this stays a plain
                         message rather than reusing the "Ajouter un traitement" flow. -->
                    <p v-else class="text-body-2 text-medium-emphasis">
                        Confirmez d'abord les informations du patient pour pouvoir ajouter un traitement.
                    </p>
                </template>

                <template #documents>
                    <PatientDocumentsTab
                        :patient-id="effectivePatientId"
                        :identity="documents?.identity ?? null"
                        :medical="documents?.medical ?? []"
                        :other="documents?.other ?? []"
                        :readonly="!can_update"
                        @saved="reloadPatient"
                    />
                </template>

                <template #consent>
                    <PatientConsentsTab
                        :patient-id="effectivePatientId"
                        :patient-full-name="pageTitle"
                        :consents="consents ?? []"
                        :consent-templates="consentTemplates ?? []"
                        :readonly="!can_update"
                        @saved="reloadPatient"
                    />
                </template>

                <template #history>
                    <div v-if="patient">
                        <h2 class="text-h6 mb-3">Historique des traitements</h2>

                        <p v-if="!pastTreatments.length" class="text-body-2 text-medium-emphasis">
                            Aucun traitement passé pour ce patient.
                        </p>

                        <TreatmentCard
                            v-for="treatment in pastTreatments"
                            :key="treatment.id"
                            class="mb-4"
                            :treatment="treatment"
                            @edit="editTreatment"
                            @add-session="openNewSession"
                            @close="openCloseTreatment"
                            @reopen="reopenTreatment"
                            @edit-session="openEditSession"
                            @delete-session="deleteSession"
                        />
                    </div>
                    <p v-else class="text-body-2 text-medium-emphasis">
                        Confirmez d'abord les informations du patient pour voir son historique.
                    </p>
                </template>
            </AppTabs>

            <PatientInfoForm
                v-else
                :form="form"
                :centers="centers"
                :religion-options="religionOptions ?? []"
                :field-errors="fieldErrors"
                :saved-label="savedLabel"
                :save-errors="saveErrors"
                :confirming="confirming"
                @confirm="confirmPatient"
                @cancel="router.get(route('admin.patients.index'))"
            />
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
            :care-categories="careCategories ?? []"
            :locked-disease-ids="editingTreatmentLockedDiseaseIds"
            @saved="reloadPatient"
        />

        <TreatmentSessionDialog
            v-if="sessionTreatmentId && patient"
            v-model:visible="sessionDialogVisible"
            :patient-id="patient.id"
            :treatment-id="sessionTreatmentId"
            :session="editingSession"
            :treatment-diseases="treatmentDiseasesFor(sessionTreatmentId)"
            :care-categories="careCategories ?? []"
            :measurement-types="measurementTypes ?? []"
            :latest-known-outcomes="latestKnownOutcomesFor(sessionTreatmentId)"
            :medical-documents="editingSession ? medicalDocumentsForSession(editingSession.id) : []"
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
