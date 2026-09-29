<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import WeeklySlotsEditor from '@/Components/Scheduling/WeeklySlotsEditor.vue';
import { useWeeklySlotsSubmit } from '@/Components/Scheduling/useWeeklySlotsSubmit';
import { emptyGrid, gridFromSlots, type WeeklySlot } from '@/utils/weeklySchedule';
import { ref, watch } from 'vue';

// Same grid/submit plumbing as PractitionerWeeklyScheduleDialog — only the
// entity and the endpoint differ. A day left empty means "closed".
const props = defineProps<{
    visible: boolean;
    center: { id: number; name: string } | null;
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
    if (confirm('Effacer tous les horaires de ce centre ?')) {
        grid.value = emptyGrid();
    }
}

function save() {
    if (!props.center) {
        return;
    }
    submit(route('admin.centers.operating-hours.sync', props.center.id), grid.value, {}, () => emit('update:visible', false));
}
</script>

<template>
    <AppDialog
        :visible="visible"
        :header="center ? `Horaires d'ouverture — ${center.name}` : `Horaires d'ouverture`"
        max-width="720px"
        @update:visible="emit('update:visible', $event)"
    >
        <p class="text-body-2 text-medium-emphasis mb-3">
            Un jour sans plage est considéré comme fermé. Ces horaires définissent la plage affichée dans l'agenda ; ils
            ne remplacent pas les disponibilités des praticiens.
        </p>

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
