<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTabs, { type AppTabItem } from '@/Components/App/AppTabs.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
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

interface Session {
    id: number;
    session_date: string | null;
    duration_minutes: number | null;
    notes: string | null;
    care_items: { id: number }[];
    disease_progress: { disease_id: number; outcome: string | null; outcome_percentage: number | null; notes: string | null }[];
}

interface LastKnownOutcome {
    outcome: string | null;
    outcome_percentage: number | null;
    notes: string | null;
}

const props = withDefaults(
    defineProps<{
        visible: boolean;
        treatmentId: number;
        session: Session | null;
        treatmentDiseases: TreatmentDisease[];
        careCategories: CareCategoryOption[];
        /**
         * Most recent outcome/percentage/notes recorded for each disease
         * across every past session of this treatment — used to prefill a
         * brand-new session (props.session === null) instead of starting
         * every field blank, so the practitioner isn't forced to re-key
         * values that haven't changed since last time. Ignored entirely
         * when editing an existing session (that session's own values win).
         */
        lastKnownOutcomes?: Record<number, LastKnownOutcome>;
    }>(),
    { lastKnownOutcomes: () => ({}) },
);

const emit = defineEmits<{ 'update:visible': [value: boolean]; saved: [] }>();

// Care checklist and disease-outcome tracking are two independent facets of
// the same session — not a step-by-step sequence like the treatment wizard
// — so this uses Tabs rather than a Stepper, same choice already made for
// the patient file's own tabs (see AuthenticatedLayout/Form.vue).
const sessionTabs: AppTabItem[] = [
    { title: 'Soins', value: 'care' },
    { title: 'Suivi des maladies', value: 'diseases' },
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
// Disease ids whose row was prefilled from lastKnownOutcomes (new session
// only) rather than typed by the practitioner — drives the "valeur reprise
// de la dernière séance" hint so it's never presented as if freshly entered.
const prefilledDiseaseIds = ref<Set<number>>(new Set());

function resetForm() {
    activeSessionTab.value = 'care';
    form.session_date = props.session?.session_date ?? toLocalDateString(new Date());
    form.duration_minutes = props.session?.duration_minutes ?? null;
    form.notes = props.session?.notes ?? null;
    selectedCareItemIds.value = new Set((props.session?.care_items ?? []).map((item) => item.id));

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

function toggleCareItem(itemId: number) {
    const next = new Set(selectedCareItemIds.value);

    if (next.has(itemId)) {
        next.delete(itemId);
    } else {
        next.add(itemId);
    }

    selectedCareItemIds.value = next;
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
            </AppTabs>

            <AppTextarea v-model="form.notes" label="Notes générales de la séance" :rows="3" />

            <div class="d-flex justify-end ga-2">
                <AppButton type="button" label="Annuler" severity="secondary" @click="close" />
                <AppButton type="button" label="Enregistrer" :loading="saving" @click="save" />
            </div>
        </div>
    </AppDialog>
</template>
