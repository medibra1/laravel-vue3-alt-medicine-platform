<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import { dayLabels, displayDayOrder, type WeeklyGrid } from '@/utils/weeklySchedule';
import { ref } from 'vue';

/**
 * Day-by-day editor of recurring time ranges, mutating the grid passed
 * in (owned by the parent dialog). Errors are keyed `${day}-${index}`.
 */
const props = defineProps<{
    grid: WeeklyGrid;
    errors?: Record<string, string>;
}>();

const duplicateTargets = ref<Set<number>>(new Set());

function addRange(day: number) {
    const last = props.grid[day].at(-1);
    props.grid[day].push(last ? { start_time: last.end_time, end_time: '' } : { start_time: '09:00', end_time: '17:00' });
}

function removeRange(day: number, index: number) {
    props.grid[day].splice(index, 1);
}

function toggleTarget(day: number, checked: boolean) {
    const next = new Set(duplicateTargets.value);
    if (checked) {
        next.add(day);
    } else {
        next.delete(day);
    }
    duplicateTargets.value = next;
}

function duplicate(sourceDay: number) {
    const targets = [...duplicateTargets.value].filter((day) => day !== sourceDay);
    const overwritten = targets.filter((day) => props.grid[day].length > 0);

    if (overwritten.length && !confirm(`Remplacer les plages existantes de : ${overwritten.map((day) => dayLabels[day]).join(', ')} ?`)) {
        return;
    }

    for (const day of targets) {
        props.grid[day] = props.grid[sourceDay].map((range) => ({ ...range }));
    }
    duplicateTargets.value = new Set();
}
</script>

<template>
    <div class="d-flex flex-column ga-3">
        <div v-for="day in displayDayOrder" :key="day" class="weekly-day pa-3 rounded border">
            <div class="d-flex align-center justify-space-between flex-wrap ga-2 mb-2">
                <span class="text-subtitle-2">{{ dayLabels[day] }}</span>
                <div class="d-flex ga-1">
                    <v-menu v-if="grid[day].length" :close-on-content-click="false" @update:model-value="duplicateTargets = new Set()">
                        <template #activator="{ props: activator }">
                            <v-btn v-bind="activator" size="small" variant="text" prepend-icon="mdi-content-copy">Dupliquer vers…</v-btn>
                        </template>
                        <v-card min-width="220">
                            <v-card-text class="py-2">
                                <AppCheckbox
                                    v-for="target in displayDayOrder.filter((d) => d !== day)"
                                    :key="target"
                                    :model-value="duplicateTargets.has(target)"
                                    :label="dayLabels[target]"
                                    @update:model-value="toggleTarget(target, $event)"
                                />
                            </v-card-text>
                            <v-card-actions>
                                <AppButton label="Copier" size="small" :disabled="!duplicateTargets.size" @click="duplicate(day)" />
                            </v-card-actions>
                        </v-card>
                    </v-menu>
                    <AppButton label="Ajouter une plage" icon="mdi-plus" size="small" severity="secondary" @click="addRange(day)" />
                </div>
            </div>

            <p v-if="!grid[day].length" class="text-body-2 text-medium-emphasis mb-0">Pas de disponibilité</p>

            <v-row v-for="(range, index) in grid[day]" :key="index" dense align="center">
                <v-col cols="5">
                    <AppInputText v-model="range.start_time" type="time" label="Début" :error="errors?.[`${day}-${index}`]" />
                </v-col>
                <v-col cols="5">
                    <AppInputText v-model="range.end_time" type="time" label="Fin" />
                </v-col>
                <v-col cols="2" class="d-flex justify-end">
                    <v-btn icon="mdi-delete" size="small" variant="text" color="error" aria-label="Supprimer la plage" @click="removeRange(day, index)" />
                </v-col>
            </v-row>
        </div>
    </div>
</template>
