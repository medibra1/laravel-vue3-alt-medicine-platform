<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import { router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

const props = defineProps<{
    visible: boolean;
    treatmentId: number;
}>();

const emit = defineEmits<{ 'update:visible': [value: boolean]; saved: [] }>();

// 'resolved' isn't offered here on purpose — that reason is only ever set
// automatically once every disease has a final outcome
// (Treatment::refreshClosureStatus()). These three cover every early/forced
// closure a raqi or manager might need instead. 'lost_to_follow_up' and
// 'protocol_not_followed' used to be a single merged reason — split so
// stats can tell "couldn't reach the patient" apart from "reached them, but
// they didn't follow the sessions/care plan".
const reasonOptions = [
    { label: 'Injoignable', value: 'lost_to_follow_up' },
    { label: 'Arrêté — protocole non suivi (séances/soins non respectés)', value: 'protocol_not_followed' },
    { label: 'Autre motif', value: 'closed_manually' },
];

const form = reactive({
    closure_reason: null as string | null,
    notes: null as string | null,
});

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            form.closure_reason = null;
            form.notes = null;
        }
    },
    { immediate: true },
);

const saving = ref(false);
const errors = ref<Record<string, string>>({});

function save() {
    saving.value = true;
    errors.value = {};

    router.post(route('admin.treatments.close', props.treatmentId), form, {
        onError: (e) => {
            errors.value = e as Record<string, string>;
        },
        onSuccess: () => {
            emit('saved');
            emit('update:visible', false);
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function close() {
    emit('update:visible', false);
}
</script>

<template>
    <AppDialog :visible="visible" header="Clôturer le traitement" max-width="560px" @update:visible="close">
        <div class="d-flex flex-column ga-4">
            <p class="text-body-2 text-medium-emphasis">
                Un traitement clôturé libère le patient pour un nouveau traitement. À utiliser quand toutes les
                maladies suivies ne seront pas résolues normalement (le traitement se ferme alors automatiquement).
            </p>

            <AppSelect
                v-model="form.closure_reason"
                :options="reasonOptions"
                option-label="label"
                option-value="value"
                label="Motif de clôture"
                placeholder="Choisir un motif"
                :error="errors.closure_reason"
            />

            <AppTextarea v-model="form.notes" label="Détail (optionnel)" :rows="3" />

            <div class="d-flex justify-end ga-2">
                <AppButton type="button" label="Annuler" severity="secondary" @click="close" />
                <AppButton type="button" label="Clôturer" severity="danger" :loading="saving" @click="save" />
            </div>
        </div>
    </AppDialog>
</template>
