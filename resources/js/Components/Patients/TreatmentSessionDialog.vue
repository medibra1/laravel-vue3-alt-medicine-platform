<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppFileUpload from '@/Components/App/AppFileUpload.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTabs, { type AppTabItem } from '@/Components/App/AppTabs.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import CareItemsPicker from '@/Components/Patients/CareItemsPicker.vue';
import { fromLocalDateString, toLocalDateString } from '@/utils/date';
import { outcomeOptions } from '@/utils/diseaseOutcome';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

interface TreatmentDisease {
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

interface MeasurementTypeOption {
    id: number;
    code: string;
    label: string;
    unit: string | null;
    placeholder: string | null;
}

interface SessionMeasurement {
    measurement_type_option_id: number;
    value: string;
    unit: string | null;
    notes: string | null;
}

interface Session {
    id: number;
    session_date: string | null;
    duration_minutes: number | null;
    notes: string | null;
    care_items: { id: number }[];
    disease_progress: { disease_id: number; outcome: string | null; outcome_percentage: number | null; notes: string | null }[];
    measurements: SessionMeasurement[];
}

interface LastKnownOutcome {
    outcome: string | null;
    outcome_percentage: number | null;
    notes: string | null;
}

interface MedicalDocument {
    id: number;
    name: string;
    mime_type: string;
    size: number;
    download_url: string;
    thumb_url: string | null;
}

const props = withDefaults(
    defineProps<{
        visible: boolean;
        patientId: number;
        treatmentId: number;
        session: Session | null;
        treatmentDiseases: TreatmentDisease[];
        careCategories: CareCategoryOption[];
        measurementTypes: MeasurementTypeOption[];
        /**
         * Most recent outcome/percentage/notes recorded for each disease
         * across every past session of this treatment — used to prefill a
         * brand-new session (props.session === null) instead of starting
         * every field blank, so the practitioner isn't forced to re-key
         * values that haven't changed since last time. Ignored entirely
         * when editing an existing session (that session's own values win).
         */
        lastKnownOutcomes?: Record<number, LastKnownOutcome>;
        /**
         * Medical documents already tagged with this session's id
         * (Patient's single 'medical' collection, filtered by
         * custom_properties.treatment_session_id — see Form.vue's
         * medicalDocumentsForSession()). Always empty for a brand-new
         * session (nothing to tag yet). Listed here purely for review —
         * still only ever deleted/downloaded from the patient file's
         * Documents tab, the single place that owns document management.
         */
        medicalDocuments?: MedicalDocument[];
    }>(),
    { lastKnownOutcomes: () => ({}), medicalDocuments: () => [] },
);

const emit = defineEmits<{ 'update:visible': [value: boolean]; saved: [] }>();

// Care checklist and disease-outcome tracking are two independent facets of
// the same session — not a step-by-step sequence like the treatment wizard
// — so this uses Tabs rather than a Stepper, same choice already made for
// the patient file's own tabs (see AuthenticatedLayout/Form.vue).
//
// A "Documents" tab (upload + review of medical documents tagged to this
// session) was added and then pulled back out on 2026-08-28 — not deleted:
// the <template #documents> below, medicalDocuments prop, and
// uploadMedicalDocuments() all still exist, just no longer reachable
// because AppTabs only renders tabs listed here. Decision was to keep
// document management solely on the patient file's Documents tab for now,
// "as it was at the start" — how a session-level entry point should really
// fit in is still to be worked out. Add { title: 'Documents', value:
// 'documents' } back to re-enable once that's settled.
const sessionTabs: AppTabItem[] = [
    { title: 'Soins', value: 'care' },
    { title: 'Suivi des maladies', value: 'diseases' },
    { title: 'Mesures', value: 'measurements' },
];
const activeSessionTab = ref<string>('care');

const form = reactive({
    session_date: null as string | null,
    duration_minutes: null as number | null,
    notes: null as string | null,
});

const sessionDateBinding = computed<Date | null>({
    get: () => fromLocalDateString(form.session_date),
    set: (value) => {
        form.session_date = value ? toLocalDateString(value) : null;
    },
});

const selectedCareItemIds = ref<Set<number>>(new Set());

interface DiseaseOutcomeRow {
    [key: string]: number | string | null;
    disease_id: number;
    outcome: string | null;
    outcome_percentage: number | null;
    notes: string | null;
}

const diseaseOutcomes = ref<Record<number, DiseaseOutcomeRow>>({});
const measurementRows = ref<SessionMeasurement[]>([]);
const newMeasurementTypeId = ref<number | null>(null);

// A measurement type already present as a row can't be picked again — the
// backend unique constraint on [treatment_session_id, measurement_type_option_id]
// mirrors this, one value per type per session.
const availableMeasurementTypes = computed(() =>
    props.measurementTypes.filter((type) => !measurementRows.value.some((row) => row.measurement_type_option_id === type.id)),
);

function addMeasurementRow() {
    if (newMeasurementTypeId.value === null) {
        return;
    }

    const type = props.measurementTypes.find((option) => option.id === newMeasurementTypeId.value);
    if (!type) {
        return;
    }

    measurementRows.value.push({
        measurement_type_option_id: type.id,
        value: '',
        unit: type.unit,
        notes: null,
    });
    newMeasurementTypeId.value = null;
}

function removeMeasurementRow(typeId: number) {
    measurementRows.value = measurementRows.value.filter((row) => row.measurement_type_option_id !== typeId);
}

function measurementTypeLabel(typeId: number): string {
    return props.measurementTypes.find((type) => type.id === typeId)?.label ?? '';
}

function measurementPlaceholder(typeId: number): string | undefined {
    return props.measurementTypes.find((type) => type.id === typeId)?.placeholder ?? undefined;
}
// Disease ids whose row was prefilled from lastKnownOutcomes (new session
// only) rather than typed by the practitioner — drives the "valeur reprise
// de la dernière séance" hint so it's never presented as if freshly entered.
const prefilledDiseaseIds = ref<Set<number>>(new Set());

// Medical documents attached from here go through the exact same endpoint
// as the Documents tab (admin.patients.documents.store) — one source of
// truth on Patient's 'medical' collection, not a separate collection or
// pivot. treatment_session_id tags the upload so it's traceable back to
// this session. Deletion/replacement still only happens from the patient
// file's Documents tab (medicalDocuments here is read-only review).
const pendingMedicalFiles = ref<File[]>([]);
const uploadingMedical = ref(false);
const medicalUploadError = ref<string | null>(null);

function resetForm() {
    activeSessionTab.value = 'care';
    form.session_date = props.session?.session_date ?? toLocalDateString(new Date());
    form.duration_minutes = props.session?.duration_minutes ?? null;
    form.notes = props.session?.notes ?? null;
    selectedCareItemIds.value = new Set((props.session?.care_items ?? []).map((item) => item.id));
    pendingMedicalFiles.value = [];
    medicalUploadError.value = null;
    measurementRows.value = (props.session?.measurements ?? []).map((row) => ({ ...row }));
    newMeasurementTypeId.value = null;

    const next: Record<number, DiseaseOutcomeRow> = {};
    const prefilled = new Set<number>();

    for (const disease of props.treatmentDiseases) {
        const existing = props.session?.disease_progress.find((row) => row.disease_id === disease.id);

        if (existing) {
            next[disease.id] = {
                disease_id: disease.id,
                outcome: existing.outcome,
                outcome_percentage: existing.outcome_percentage,
                notes: existing.notes,
            };
            continue;
        }

        // No existing session to edit — start from the last known outcome
        // for this disease (if any) instead of leaving every field blank.
        const lastKnown = props.session === null ? props.lastKnownOutcomes[disease.id] : undefined;

        next[disease.id] = {
            disease_id: disease.id,
            outcome: lastKnown?.outcome ?? null,
            outcome_percentage: lastKnown?.outcome_percentage ?? null,
            notes: lastKnown?.notes ?? null,
        };

        if (lastKnown) {
            prefilled.add(disease.id);
        }
    }

    diseaseOutcomes.value = next;
    prefilledDiseaseIds.value = prefilled;
}

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            resetForm();
        }
    },
    { immediate: true },
);

function clearPrefilled(diseaseId: number) {
    if (!prefilledDiseaseIds.value.has(diseaseId)) {
        return;
    }

    const next = new Set(prefilledDiseaseIds.value);
    next.delete(diseaseId);
    prefilledDiseaseIds.value = next;
}

const saving = ref(false);
const errors = ref<Record<string, string>>({});

function save() {
    saving.value = true;
    errors.value = {};

    const payload = {
        ...form,
        care_item_ids: Array.from(selectedCareItemIds.value),
        disease_progress: Object.values(diseaseOutcomes.value),
        measurements: measurementRows.value,
    };

    const options = {
        onError: (e: Record<string, string>) => {
            errors.value = e;
        },
        onSuccess: () => {
            emit('saved');
            emit('update:visible', false);
        },
        onFinish: () => {
            saving.value = false;
        },
    };

    if (props.session) {
        router.patch(route('admin.treatments.sessions.update', [props.treatmentId, props.session.id]), payload, options);
    } else {
        router.post(route('admin.treatments.sessions.store', props.treatmentId), payload, options);
    }
}

function close() {
    emit('update:visible', false);
}

function uploadMedicalDocuments() {
    if (pendingMedicalFiles.value.length === 0 || !props.session) return;

    uploadingMedical.value = true;
    medicalUploadError.value = null;

    router.post(
        route('admin.patients.documents.store', props.patientId),
        {
            collection: 'medical',
            files: pendingMedicalFiles.value,
            treatment_session_id: props.session.id,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                pendingMedicalFiles.value = [];
            },
            onError: (errors) => {
                medicalUploadError.value = Object.values(errors)[0] as string;
            },
            onFinish: () => {
                uploadingMedical.value = false;
            },
        },
    );
}
</script>

<template>
    <AppDialog
        :visible="visible"
        :header="session ? 'Modifier la séance' : 'Nouvelle séance'"
        max-width="720px"
        @update:visible="close"
    >
        <div class="d-flex flex-column ga-4">
            <v-row>
                <v-col cols="12" md="6">
                    <AppDatePicker v-model="sessionDateBinding" label="Date de la séance" :error="errors.session_date" />
                </v-col>
                <v-col cols="12" md="6">
                    <AppInputNumber
                        v-model="form.duration_minutes"
                        label="Durée (minutes)"
                        :min="1"
                        :error="errors.duration_minutes"
                    />
                </v-col>
            </v-row>

            <AppTabs v-model="activeSessionTab" :tabs="sessionTabs">
                <template #care>
                    <CareItemsPicker v-model="selectedCareItemIds" :care-categories="careCategories" />
                </template>

                <template #diseases>
                    <div v-if="treatmentDiseases.length" class="d-flex flex-column ga-4">
                        <div v-for="disease in treatmentDiseases" :key="disease.id" class="d-flex flex-column ga-2">
                            <div class="d-flex align-center ga-2">
                                <p class="text-body-2 mb-0">{{ disease.code }} — {{ disease.label }}</p>
                                <v-chip v-if="prefilledDiseaseIds.has(disease.id)" size="x-small" variant="tonal" color="info">
                                    Reprise de la dernière séance
                                </v-chip>
                            </div>

                            <AppSelect
                                v-model="diseaseOutcomes[disease.id].outcome"
                                :options="outcomeOptions"
                                option-label="label"
                                option-value="value"
                                label="Issue"
                                show-clear
                                placeholder="Non renseignée"
                                @update:model-value="clearPrefilled(disease.id)"
                            />

                            <AppInputNumber
                                v-if="diseaseOutcomes[disease.id].outcome === 'percentage'"
                                v-model="diseaseOutcomes[disease.id].outcome_percentage"
                                label="Pourcentage"
                                :min="1"
                                :max="99"
                                @update:model-value="clearPrefilled(disease.id)"
                            />

                            <AppTextarea
                                v-model="diseaseOutcomes[disease.id].notes"
                                label="Notes"
                                :rows="2"
                                @update:model-value="clearPrefilled(disease.id)"
                            />
                        </div>
                    </div>
                    <p v-else class="text-body-2 text-medium-emphasis">Aucune maladie suivie sur ce traitement.</p>
                </template>

                <template #documents>
                    <div v-if="session" class="d-flex flex-column ga-3">
                        <template v-if="medicalDocuments.length">
                            <div
                                v-for="document in medicalDocuments"
                                :key="document.id"
                                class="d-flex align-center ga-3"
                            >
                                <v-avatar v-if="document.thumb_url" size="48" rounded>
                                    <v-img :src="document.thumb_url" cover />
                                </v-avatar>
                                <v-icon v-else icon="mdi-file-pdf-box" size="36" color="error" />

                                <p class="text-body-2 mb-0 flex-grow-1">{{ document.name }}</p>

                                <AppButton
                                    as="a"
                                    :href="document.download_url"
                                    target="_blank"
                                    icon="mdi-download"
                                    severity="secondary"
                                    size="small"
                                />
                            </div>
                        </template>
                        <p v-else class="text-body-2 text-medium-emphasis mb-0">
                            Aucun document médical lié à cette séance.
                        </p>

                        <AppFileUpload
                            v-model="pendingMedicalFiles"
                            multiple
                            :error="medicalUploadError"
                            label="Ajouter des documents médicaux (plusieurs photos d'un même document sont fusionnées en un seul PDF)"
                        />
                        <AppButton
                            v-if="pendingMedicalFiles.length"
                            label="Enregistrer les documents"
                            :loading="uploadingMedical"
                            @click="uploadMedicalDocuments"
                        />
                    </div>
                    <p v-else class="text-body-2 text-medium-emphasis">
                        Enregistrez la séance pour pouvoir y attacher un document médical.
                    </p>
                </template>

                <template #measurements>
                    <div class="d-flex flex-column ga-4">
                        <div v-if="measurementRows.length" class="d-flex flex-column ga-3">
                            <div
                                v-for="row in measurementRows"
                                :key="row.measurement_type_option_id"
                                class="d-flex flex-column ga-2"
                            >
                                <div class="d-flex justify-space-between align-center">
                                    <p class="text-body-2 font-weight-medium mb-0">{{ measurementTypeLabel(row.measurement_type_option_id) }}</p>
                                    <AppButton
                                        icon="mdi-delete"
                                        severity="danger"
                                        size="small"
                                        aria-label="Retirer cette mesure"
                                        @click="removeMeasurementRow(row.measurement_type_option_id)"
                                    />
                                </div>

                                <v-row>
                                    <v-col cols="8">
                                        <AppInputText
                                            v-model="row.value"
                                            label="Valeur"
                                            :placeholder="measurementPlaceholder(row.measurement_type_option_id)"
                                            :error="errors[`measurements.${measurementRows.indexOf(row)}.value`]"
                                        />
                                    </v-col>
                                    <v-col cols="4">
                                        <AppInputText v-model="row.unit" label="Unité" />
                                    </v-col>
                                </v-row>

                                <AppTextarea v-model="row.notes" label="Notes" :rows="2" />
                            </div>
                        </div>
                        <p v-else class="text-body-2 text-medium-emphasis">Aucune mesure enregistrée pour cette séance.</p>

                        <v-row v-if="availableMeasurementTypes.length" align="center">
                            <v-col cols="8">
                                <AppSelect
                                    v-model="newMeasurementTypeId"
                                    :options="availableMeasurementTypes"
                                    option-label="label"
                                    option-value="id"
                                    label="Type de mesure"
                                    placeholder="Choisir un type"
                                />
                            </v-col>
                            <v-col cols="4">
                                <AppButton
                                    type="button"
                                    label="Ajouter une mesure"
                                    icon="mdi-plus"
                                    severity="secondary"
                                    :disabled="newMeasurementTypeId === null"
                                    @click="addMeasurementRow"
                                />
                            </v-col>
                        </v-row>
                    </div>
                </template>
            </AppTabs>

            <AppTextarea v-model="form.notes" label="Notes générales de la séance" :rows="3" />

            <div class="d-flex justify-end ga-2">
                <AppButton type="button" label="Annuler" severity="secondary" @click="close" />
                <AppButton type="button" label="Enregistrer" :loading="saving" @click="save" />
            </div>
        </div>
    </AppDialog>
</template>
