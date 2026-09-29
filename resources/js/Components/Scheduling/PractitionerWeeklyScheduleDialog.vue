<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import WeeklySlotsEditor from '@/Components/Scheduling/WeeklySlotsEditor.vue';
import { useWeeklySlotsSubmit } from '@/Components/Scheduling/useWeeklySlotsSubmit';
import { emptyGrid, gridFromSlots, type WeeklySlot } from '@/utils/weeklySchedule';
import { ref, watch } from 'vue';

const props = defineProps<{
    visible: boolean;
    practitioner: { id: number; first_name: string; last_name: string } | null;
    initialSlots: WeeklySlot[];
}>();

const emit = defineEmits<{ 'update:visible': [value: boolean] }>();

const grid = ref(emptyGrid());
const { rowErrors, generalErrors, processing, submit } = useWeeklySlotsSubmit();

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            grid.value = gridFromSlots(props.initialSlots);
            rowErrors.value = {};
            generalErrors.value = [];
        }
    },
    { immediate: true },
);

function clearAll() {
    if (confirm('Effacer toutes les plages de ce praticien ?')) {
        grid.value = emptyGrid();
    }
}

function save() {
    if (!props.practitioner) {
        return;
    }
    submit(route('admin.practitioners.availabilities.sync', props.practitioner.id), grid.value, {}, () => emit('update:visible', false));
}
</script>

<template>
    <AppDialog
        :visible="visible"
        :header="practitioner ? `Planning de ${practitioner.first_name} ${practitioner.last_name}` : 'Planning'"
        max-width="720px"
        @update:visible="emit('update:visible', $event)"
    >
        <v-alert v-if="generalErrors.length" type="error" variant="tonal" density="compact" class="mb-3">
            <div v-for="message in generalErrors" :key="message">{{ message }}</div>
        </v-alert>

        <WeeklySlotsEditor :grid="grid" :errors="rowErrors" />

        <div class="d-flex justify-space-between flex-wrap ga-2 mt-4">
            <AppButton label="Tout effacer" severity="danger" @click="clearAll" />
            <div class="d-flex ga-2">
                <AppButton label="Annuler" severity="secondary" @click="emit('update:visible', false)" />
                <AppButton label="Enregistrer" :loading="processing" @click="save" />
            </div>
        </div>
    </AppDialog>
</template>
