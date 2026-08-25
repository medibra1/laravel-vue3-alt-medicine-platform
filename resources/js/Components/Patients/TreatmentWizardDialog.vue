<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppStepper from '@/Components/App/AppStepper.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import { useResilientForm } from '@/composables/useResilientForm';
import { fromLocalDateString, toLocalDateString } from '@/utils/date';
import { router } from '@inertiajs/vue3';
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
    actively_tracked_disease_ids: number[];
}

const props = withDefaults(
    defineProps<{
        visible: boolean;
        treatment: Treatment | null;
        patientId?: number;
        centers: Center[];
        patients: PatientOption[];
        practitioners: PractitionerOption[];
        diseases: DiseaseOption[];
        diseaseCategories: DiseaseCategoryOption[];
        careCategories: CareCategoryOption[];
        /**
         * Diseases that already have tracked session progress on this
         * treatment — mirrors the server-side rule in
         * UpdateTreatmentDraftRequest (a disease with a progress row can no
         * longer be removed from disease_ids, only added to). Empty on a
         * brand-new treatment (no `treatment` prop yet) since nothing can
         * be locked before it exists.
         */
        lockedDiseaseIds?: number[];
    }>(),
    { lockedDiseaseIds: () => [] },
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

const { form, serverId, saving, lastSavedAt, saveErrors, scheduleSave, flush } =
    useResilientForm(
        'treatments',
        {
            client_uuid: props.treatment?.client_uuid,
            id: props.treatment?.id ?? null,
            patient_id: props.treatment?.patient_id ?? props.patientId ?? null,
            practitioner_id: props.treatment?.practitioner_id ?? null,
            center_id: props.treatment?.center_id ?? null,
            started_at: props.treatment?.started_at ?? null,
            ended_at: props.treatment?.ended_at ?? null,
            outcome: props.treatment?.outcome ?? null,
            outcome_percentage: props.treatment?.outcome_percentage ?? null,
            notes: props.treatment?.notes ?? null,
            disease_ids: props.treatment?.disease_ids ?? [],
            // Defaults every selected disease to actively tracked on a
            // brand-new treatment (nothing to default from yet — every id
            // added to disease_ids below also lands here via toggleDisease()).
            actively_tracked_disease_ids: props.treatment?.actively_tracked_disease_ids ?? props.treatment?.disease_ids ?? [],
        },
        {
            create: route('admin.treatments.draft.store'),
            update: (id) => route('admin.treatments.draft.update', id),
        },
    );

// --- Care items given during this first (implicit) session (step 4) ---
// Care stays 100% per-session (option B) — this is not a treatment-wide
// protocol, only what was given during the session confirmation creates.
const selectedCareItemIds = ref<Set<number>>(new Set());

function toggleCareItem(itemId: number) {
    const next = new Set(selectedCareItemIds.value);

    if (next.has(itemId)) {
        next.delete(itemId);
    } else {
        next.add(itemId);
    }

    selectedCareItemIds.value = next;
}

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            currentStep.value = 'infos';
            selectedCareItemIds.value = new Set();
        }
    },
);

watch(form, () => scheduleSave(), { deep: true });

// The "Soins" step only makes sense on a brand-new treatment — it seeds
// the first (implicit) session created by TreatmentController::confirm(),
// gated there on Treatment::sessions()->doesntExist(). Editing an
// already-started treatment (props.treatment !== null) has no such
// implicit session to seed: care from then on only goes through
// TreatmentSessionController, so the step is simply not part of the
// wizard in that mode rather than shown-but-inert.
const isCreatingNewTreatment = computed(() => props.treatment === null);

const currentStep = ref<'infos' | 'diseases' | 'careItems'>('infos');
const steps = computed(() =>
    isCreatingNewTreatment.value
        ? [
              { title: 'Infos générales', value: 'infos' },
              { title: 'Maladies', value: 'diseases' },
              { title: 'Soins — 1ère séance', value: 'careItems' },
          ]
        : [
              { title: 'Infos générales', value: 'infos' },
              { title: 'Maladies', value: 'diseases' },
          ],
);

const isLastStep = computed(() => steps.value[steps.value.length - 1]?.value === currentStep.value);

function dateBinding(field: 'started_at' | 'ended_at') {
    return computed<Date | null>({
        get: () => fromLocalDateString(form[field] as string | null),
        set: (value) => {
            form[field] = value ? toLocalDateString(value) : null;
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

// --- Disease selection (step 2) ---
const activeCategoryId = ref<number | null>(null);
const diseaseSearch = ref('');

const selectedDiseaseIds = computed<Set<number>>({
    get: () => new Set(form.disease_ids as number[]),
    set: (value) => {
        form.disease_ids = Array.from(value);
    },
});

// Which of the selected diseases are actively tracked (evaluated at every
// session) versus merely on record alongside the treatment — see CLAUDE.md
// "Suivi actif vs maladie secondaire". Kept as its own Set (not derived from
// selectedDiseaseIds) so toggling it doesn't touch selection at all.
const activelyTrackedDiseaseIds = computed<Set<number>>({
    get: () => new Set(form.actively_tracked_disease_ids as number[]),
    set: (value) => {
        form.actively_tracked_disease_ids = Array.from(value);
    },
});

function isActivelyTracked(diseaseId: number): boolean {
    return activelyTrackedDiseaseIds.value.has(diseaseId);
}

// Freely toggleable at any time, including on an already-confirmed
// treatment (editTreatment()) — unlike removal, deactivating active
// tracking never orphans a disease's existing progress history, it only
// exempts it from future sessions' required evaluation.
function toggleActivelyTracked(diseaseId: number) {
    const next = new Set(activelyTrackedDiseaseIds.value);

    if (next.has(diseaseId)) {
        next.delete(diseaseId);
    } else {
        next.add(diseaseId);
    }

    activelyTrackedDiseaseIds.value = next;
}

const lockedDiseaseIdSet = computed(() => new Set(props.lockedDiseaseIds));

function isDiseaseLocked(diseaseId: number): boolean {
    return lockedDiseaseIdSet.value.has(diseaseId);
}

function toggleDisease(diseaseId: number) {
    // Locked diseases already have tracked session progress — removing
    // them would orphan that history (see UpdateTreatmentDraftRequest).
    // They're always selected, so this only ever guards against
    // unchecking; the checkbox is also rendered disabled for the same
    // reason, this is the belt-and-braces check.
    if (isDiseaseLocked(diseaseId)) {
        return;
    }

    const next = new Set(selectedDiseaseIds.value);
    const nextActivelyTracked = new Set(activelyTrackedDiseaseIds.value);

    if (next.has(diseaseId)) {
        next.delete(diseaseId);
        nextActivelyTracked.delete(diseaseId);
    } else {
        next.add(diseaseId);
        // Active by default on selection — matches the "checked by
        // default" wizard behavior asked for this toggle.
        nextActivelyTracked.add(diseaseId);
    }

    selectedDiseaseIds.value = next;
    activelyTrackedDiseaseIds.value = nextActivelyTracked;
}

const diseasesInActiveCategory = computed(() =>
    props.diseases.filter((disease) => disease.category_id === activeCategoryId.value),
);

const searchResults = computed(() => {
    const term = diseaseSearch.value.trim().toLowerCase();

    if (!term) {
        return [];
    }

    return props.diseases.filter(
        (disease) =>
            disease.label.toLowerCase().includes(term) || disease.code.includes(term),
    );
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
        {
            ...form,
            care_item_ids: Array.from(selectedCareItemIds.value),
        },
        {
            onError: (errors) => {
                confirmErrors.value = errors as Record<string, string>;
            },
            onSuccess: () => {
                emit('saved');
                emit('update:visible', false);
            },
            onFinish: () => {
                confirming.value = false;
            },
        },
    );
}

function close() {
    emit('update:visible', false);
}
</script>

<template>
    <AppDialog
        :visible="visible"
        :header="treatment ? 'Modifier le traitement' : 'Nouveau traitement'"
        max-width="960px"
        @update:visible="close"
    >
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

        <AppStepper v-model="currentStep" :steps="steps">
            <template #default="{ step }">
                <div class="pa-4">
                    <v-row v-if="step === 'infos'">
                        <v-col cols="12" :md="patientId ? 12 : 6">
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
                        </v-col>

                        <v-col v-if="!patientId" cols="12" md="6">
                            <AppSelect
                                v-model="form.patient_id"
                                :options="patientOptions"
                                option-label="name"
                                option-value="id"
                                label="Patient"
                                placeholder="Choisir un patient"
                                :error="fieldErrors.patient_id"
                            />
                        </v-col>

                        <v-col cols="12">
                            <AppSelect
                                v-model="form.practitioner_id"
                                :options="practitionerOptions"
                                option-label="name"
                                option-value="id"
                                label="Praticien"
                                placeholder="Choisir un praticien"
                                :error="fieldErrors.practitioner_id"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <AppDatePicker
                                v-model="startedAtBinding"
                                label="Date de début"
                                :error="fieldErrors.started_at"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <AppDatePicker v-model="endedAtBinding" label="Date de fin" />
                        </v-col>

                        <v-col cols="12">
                            <AppTextarea v-model="form.notes" label="Notes" :rows="3" />
                        </v-col>
                    </v-row>

                    <div v-else-if="step === 'diseases'" class="d-flex flex-column ga-4">
                        <v-alert v-if="lockedDiseaseIdSet.size" type="info" variant="tonal" density="compact">
                            Certaines maladies ne peuvent plus être retirées car elles ont déjà un suivi de
                            séance.
                        </v-alert>

                        <AppInputText
                            v-model="diseaseSearch"
                            label="Rechercher une maladie (toutes catégories)"
                            placeholder="Ex. anxiété, cauchemar…"
                        />

                        <div v-if="diseaseSearch.trim()" class="d-flex flex-column ga-2">
                            <p class="text-body-2 text-medium-emphasis">
                                {{ searchResults.length }} résultat(s)
                            </p>
                            <div
                                v-for="disease in searchResults"
                                :key="disease.id"
                                class="d-flex align-center ga-2"
                            >
                                <span>
                                    <AppCheckbox
                                        :model-value="selectedDiseaseIds.has(disease.id)"
                                        :disabled="isDiseaseLocked(disease.id)"
                                        @update:model-value="toggleDisease(disease.id)"
                                    />
                                    <v-tooltip v-if="isDiseaseLocked(disease.id)" activator="parent" location="top">
                                        Cette maladie a déjà un suivi enregistré et ne peut plus être retirée.
                                    </v-tooltip>
                                </span>
                                <span>{{ disease.code }} — {{ disease.label }}</span>
                                <v-chip size="small" variant="tonal">{{ disease.category_label }}</v-chip>
                                <span v-if="selectedDiseaseIds.has(disease.id)">
                                    <AppCheckbox
                                        :model-value="isActivelyTracked(disease.id)"
                                        label="Suivi actif à chaque séance"
                                        @update:model-value="toggleActivelyTracked(disease.id)"
                                    />
                                </span>
                            </div>
                        </div>

                        <template v-else>
                            <v-row>
                                <v-col
                                    v-for="category in diseaseCategories"
                                    :key="category.id"
                                    cols="6"
                                    sm="6"
                                    md="4"
                                >
                                    <AppCard
                                        :title="category.label"
                                        :icon="category.icon"
                                        clickable
                                        :selected="activeCategoryId === category.id"
                                        @click="activeCategoryId = category.id"
                                    />
                                </v-col>
                            </v-row>

                            <div v-if="activeCategoryId" class="d-flex flex-column ga-2 mt-2">
                                <div
                                    v-for="disease in diseasesInActiveCategory"
                                    :key="disease.id"
                                    class="d-flex align-center ga-2"
                                >
                                    <span>
                                        <AppCheckbox
                                            :model-value="selectedDiseaseIds.has(disease.id)"
                                            :label="`${disease.code} — ${disease.label}`"
                                            :disabled="isDiseaseLocked(disease.id)"
                                            @update:model-value="toggleDisease(disease.id)"
                                        />
                                        <v-tooltip v-if="isDiseaseLocked(disease.id)" activator="parent" location="top">
                                            Cette maladie a déjà un suivi enregistré et ne peut plus être retirée.
                                        </v-tooltip>
                                    </span>
                                    <span v-if="selectedDiseaseIds.has(disease.id)">
                                        <AppCheckbox
                                            :model-value="isActivelyTracked(disease.id)"
                                            label="Suivi actif à chaque séance"
                                            @update:model-value="toggleActivelyTracked(disease.id)"
                                        />
                                    </span>
                                </div>
                            </div>
                        </template>

                        <v-alert v-if="fieldErrors.disease_ids" type="error" variant="tonal" density="compact">
                            {{ fieldErrors.disease_ids }}
                        </v-alert>
                    </div>

                    <div v-else-if="step === 'careItems'" class="d-flex flex-column ga-4">
                        <p class="text-body-2 text-medium-emphasis">
                            Soins donnés lors de cette première séance — pas un protocole pour
                            l'ensemble du traitement, seulement ce qui a été fait aujourd'hui.
                        </p>

                        <div v-if="careCategories.length">
                            <div v-for="category in careCategories" :key="category.id" class="mb-3">
                                <p class="text-body-2 text-medium-emphasis">{{ category.label }}</p>
                                <div class="d-flex flex-wrap ga-3">
                                    <AppCheckbox
                                        v-for="item in category.items"
                                        :key="item.id"
                                        :model-value="selectedCareItemIds.has(item.id)"
                                        :label="item.label"
                                        @update:model-value="toggleCareItem(item.id)"
                                    />
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-body-2 text-medium-emphasis">Aucun soin disponible.</p>
                    </div>
                </div>
            </template>
        </AppStepper>

        <div class="d-flex justify-space-between ga-2 mt-4">
            <AppButton type="button" label="Fermer" severity="secondary" @click="close" />

            <div class="d-flex ga-2">
                <AppButton
                    v-if="currentStep !== 'infos'"
                    type="button"
                    label="Précédent"
                    severity="secondary"
                    @click="currentStep = steps[steps.findIndex((s) => s.value === currentStep) - 1].value as typeof currentStep"
                />
                <AppButton
                    v-if="!isLastStep"
                    type="button"
                    label="Suivant"
                    @click="currentStep = steps[steps.findIndex((s) => s.value === currentStep) + 1].value as typeof currentStep"
                />
                <AppButton
                    v-else
                    type="button"
                    label="Confirmer"
                    :loading="confirming"
                    @click="confirmTreatment"
                />
            </div>
        </div>
    </AppDialog>
</template>
