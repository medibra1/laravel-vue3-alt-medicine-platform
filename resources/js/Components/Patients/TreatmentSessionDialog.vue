<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
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

const props = defineProps<{
    visible: boolean;
    treatmentId: number;
    session: Session | null;
    treatmentDiseases: TreatmentDisease[];
    careCategories: CareCategoryOption[];
}>();

const emit = defineEmits<{ 'update:visible': [value: boolean]; saved: [] }>();

const outcomeOptions = [
    { label: 'Guéri', value: 'cured' },
    { label: 'Non guéri', value: 'not_cured' },
    { label: 'En cours', value: 'ongoing' },
    { label: 'Pourcentage', value: 'percentage' },
];

const form = reactive({
    session_date: null as string | null,
    duration_minutes: null as number | null,
    notes: null as string | null,
});

const sessionDateBinding = computed<Date | null>({
    get: () => (form.session_date ? new Date(form.session_date) : null),
    set: (value) => {
        form.session_date = value ? value.toISOString().slice(0, 10) : null;
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

function resetForm() {
    form.session_date = props.session?.session_date ?? new Date().toISOString().slice(0, 10);
    form.duration_minutes = props.session?.duration_minutes ?? null;
    form.notes = props.session?.notes ?? null;
    selectedCareItemIds.value = new Set((props.session?.care_items ?? []).map((item) => item.id));

    const next: Record<number, DiseaseOutcomeRow> = {};
    for (const disease of props.treatmentDiseases) {
        const existing = props.session?.disease_progress.find((row) => row.disease_id === disease.id);
        next[disease.id] = {
            disease_id: disease.id,
            outcome: existing?.outcome ?? null,
            outcome_percentage: existing?.outcome_percentage ?? null,
            notes: existing?.notes ?? null,
        };
    }
    diseaseOutcomes.value = next;
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
            <AppDatePicker v-model="sessionDateBinding" label="Date de la séance" :error="errors.session_date" />

            <AppInputNumber
                v-model="form.duration_minutes"
                label="Durée (minutes)"
                :min="1"
                :error="errors.duration_minutes"
            />

            <div v-if="careCategories.length">
                <p class="text-subtitle-2 mb-2">Soins utilisés</p>
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

            <div v-if="treatmentDiseases.length" class="d-flex flex-column ga-4">
                <p class="text-subtitle-2">Progression par maladie</p>
                <div v-for="disease in treatmentDiseases" :key="disease.id" class="d-flex flex-column ga-2">
                    <p class="text-body-2">{{ disease.code }} — {{ disease.label }}</p>

                    <AppSelect
                        v-model="diseaseOutcomes[disease.id].outcome"
                        :options="outcomeOptions"
                        option-label="label"
                        option-value="value"
                        label="Issue"
                        show-clear
                        placeholder="Non renseignée"
                    />

                    <AppInputNumber
                        v-if="diseaseOutcomes[disease.id].outcome === 'percentage'"
                        v-model="diseaseOutcomes[disease.id].outcome_percentage"
                        label="Pourcentage"
                        :min="1"
                        :max="99"
                    />

                    <AppTextarea v-model="diseaseOutcomes[disease.id].notes" label="Notes" :rows="2" />
                </div>
            </div>

            <AppTextarea v-model="form.notes" label="Notes générales de la séance" :rows="3" />

            <div class="d-flex justify-end ga-2">
                <AppButton type="button" label="Annuler" severity="secondary" @click="close" />
                <AppButton type="button" label="Enregistrer" :loading="saving" @click="save" />
            </div>
        </div>
    </AppDialog>
</template>
