<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import WeeklySlotsEditor from '@/Components/Scheduling/WeeklySlotsEditor.vue';
import { useWeeklySlotsSubmit } from '@/Components/Scheduling/useWeeklySlotsSubmit';
import { emptyGrid } from '@/utils/weeklySchedule';
import { ref, watch } from 'vue';

/**
 * Starts from an empty grid on purpose (never pre-filled from one
 * practitioner) and overwrites every selected practitioner's schedule.
 * Rows stay independent afterwards — no shared template is stored.
 */
const props = defineProps<{
    visible: boolean;
    practitionerOptions: { id: number; name: string }[];
}>();

const emit = defineEmits<{ 'update:visible': [value: boolean] }>();

const grid = ref(emptyGrid());
const practitionerIds = ref<number[]>([]);
const { rowErrors, generalErrors, processing, submit } = useWeeklySlotsSubmit();

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            grid.value = emptyGrid();
            practitionerIds.value = [];
            rowErrors.value = {};
            generalErrors.value = [];
        }
    },
    { immediate: true },
);

function apply() {
    if (!confirm(`Remplacer intégralement le planning de ${practitionerIds.value.length} praticien(s) ?`)) {
        return;
    }
    submit(route('admin.practitioners.availabilities.bulk-sync'), grid.value, { practitioner_ids: practitionerIds.value }, () =>
        emit('update:visible', false),
    );
}
</script>

<template>
    <AppDialog :visible="visible" header="Appliquer un planning à plusieurs praticiens" max-width="720px" @update:visible="emit('update:visible', $event)">
        <AppSelect
            v-model="practitionerIds"
            :options="practitionerOptions"
            option-label="name"
            option-value="id"
            label="Praticiens"
            multiple
            class="mb-3"
        />

        <v-alert type="warning" variant="tonal" density="compact" class="mb-3">
            Ceci va remplacer intégralement le planning existant de chaque praticien sélectionné.
        </v-alert>

        <v-alert v-if="generalErrors.length" type="error" variant="tonal" density="compact" class="mb-3">
            <div v-for="message in generalErrors" :key="message">{{ message }}</div>
        </v-alert>

        <WeeklySlotsEditor :grid="grid" :errors="rowErrors" />

        <div class="d-flex justify-end ga-2 mt-4">
            <AppButton label="Annuler" severity="secondary" @click="emit('update:visible', false)" />
            <AppButton label="Appliquer" :loading="processing" :disabled="!practitionerIds.length" @click="apply" />
        </div>
    </AppDialog>
</template>
