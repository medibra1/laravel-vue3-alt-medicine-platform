<script setup lang="ts">
import { tr } from '@/lib/i18n';
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
    { label: tr('ui.injoignable'), value: 'lost_to_follow_up' },
    { label: tr('ui.arreteProtocoleNonSuiviSeancesSoins'), value: 'protocol_not_followed' },
    { label: tr('ui.autreMotif'), value: 'closed_manually' },
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
    <AppDialog :visible="visible" :header="$t('ui.cloturerLeTraitement')" max-width="560px" @update:visible="close">
        <div class="d-flex flex-column ga-4">
            <p class="text-body-2 text-medium-emphasis">
                {{ $t('ui.unTraitementClotureLibereLePatient') }}
            </p>

            <AppSelect
                v-model="form.closure_reason"
                :options="reasonOptions"
                option-label="label"
                option-value="value"
                :label="$t('ui.motifDeCloture')"
                :placeholder="$t('ui.choisirUnMotif')"
                :error="errors.closure_reason"
            />

            <AppTextarea v-model="form.notes" :label="$t('ui.detailOptionnel')" :rows="3" />

            <div class="d-flex justify-end ga-2">
                <AppButton type="button" :label="$t('ui.annuler')" severity="secondary" @click="close" />
                <AppButton type="button" :label="$t('ui.cloturer')" severity="danger" :loading="saving" @click="save" />
            </div>
        </div>
    </AppDialog>
</template>
