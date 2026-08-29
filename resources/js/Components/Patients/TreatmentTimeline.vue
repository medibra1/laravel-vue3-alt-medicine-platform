<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import { outcomeColor, outcomeIcon, outcomeLabel } from '@/utils/diseaseOutcome';

interface TreatmentDisease {
    id: number;
    code: string;
    label: string;
    category_label: string;
}

interface TreatmentSessionSummary {
    id: number;
    session_date: string | null;
    duration_minutes: number | null;
    notes: string | null;
    care_items: { id: number; label: string; category_label: string }[];
    disease_progress: { disease_id: number; disease_label: string; outcome: string | null; outcome_percentage: number | null; notes: string | null }[];
    measurements: { measurement_type_option_id: number; measurement_type_code: string; measurement_type_label: string; value: string; unit: string | null; notes: string | null }[];
}

const props = defineProps<{
    sessions: TreatmentSessionSummary[];
    treatmentStatus: string;
}>();

const emit = defineEmits<{
    'edit-session': [session: TreatmentSessionSummary];
    'delete-session': [session: TreatmentSessionSummary];
}>();

// window.confirm() is the project-wide pattern for destructive actions
// (Patients/Practitioners/Centers/Treatments index pages, Form.vue's
// reopenTreatment()) — no dedicated confirmation dialog component exists,
// so this follows the same convention rather than introducing a new one.
function confirmDelete(session: TreatmentSessionSummary) {
    if (!confirm('Supprimer cette séance ? Cette action est irréversible.')) {
        return;
    }

    emit('delete-session', session);
}

// Treatment::sessions() is now ordered orderByDesc('session_date')
// server-side, so the most recent session is always the first array entry —
// no client-side re-sort needed here, just read [0].
function mostRecentSessionId(sessions: TreatmentSessionSummary[]): number | null {
    return sessions[0]?.id ?? null;
}

function statusColor(session: TreatmentSessionSummary): string {
    return session.id === mostRecentSessionId(props.sessions) ? 'primary' : 'muted';
}

function formatSessionDate(session: TreatmentSessionSummary): string {
    return session.session_date ? new Date(session.session_date).toLocaleDateString() : '—';
}

interface CareItemGroup {
    categoryLabel: string;
    items: TreatmentSessionSummary['care_items'];
}

// Grouped by category_label (not a fixed list of categories) so any
// current or future care category — not just Ventouses/Verset — shows
// under its own heading instead of one undifferentiated chip row.
function careItemGroups(session: TreatmentSessionSummary): CareItemGroup[] {
    const groups = new Map<string, TreatmentSessionSummary['care_items']>();

    for (const item of session.care_items) {
        const existing = groups.get(item.category_label);
        if (existing) {
            existing.push(item);
        } else {
            groups.set(item.category_label, [item]);
        }
    }

    return Array.from(groups, ([categoryLabel, items]) => ({ categoryLabel, items }));
}
</script>

<template>
    <v-timeline density="compact" side="end">
        <v-timeline-item v-for="session in sessions" :key="session.id" :dot-color="statusColor(session)" size="small">
            <template #opposite>
                <span class="text-body-2 text-medium-emphasis">{{ formatSessionDate(session) }}</span>
            </template>

            <AppCard variant="tonal" clickable @click="emit('edit-session', session)">
                <v-card-text>
                    <div class="d-flex justify-space-between align-start ga-2 mb-2">
                        <p class="text-body-2 font-weight-medium mb-0" style="min-width: 0">
                            {{ formatSessionDate(session) }}
                            <span v-if="session.duration_minutes" class="text-medium-emphasis font-weight-regular"> — {{ session.duration_minutes }} min</span>
                        </p>

                        <div class="d-flex ga-1 flex-shrink-0">
                            <AppButton
                                icon="mdi-pencil"
                                severity="secondary"
                                size="small"
                                aria-label="Modifier la séance"
                                @click.stop="emit('edit-session', session)"
                            />

                            <AppButton
                                icon="mdi-delete"
                                severity="danger"
                                size="small"
                                aria-label="Supprimer la séance"
                                @click.stop="confirmDelete(session)"
                            />
                        </div>
                    </div>

                    <div v-if="session.care_items.length" class="d-flex flex-column ga-1 mb-2">
                        <div v-for="group in careItemGroups(session)" :key="group.categoryLabel">
                            <p class="text-caption text-medium-emphasis font-weight-medium mb-1">{{ group.categoryLabel }}</p>
                            <div class="d-flex flex-wrap ga-2">
                                <v-chip v-for="item in group.items" :key="item.id" size="small" variant="tonal">
                                    {{ item.label }}
                                </v-chip>
                            </div>
                        </div>
                    </div>

                    <div v-if="session.disease_progress.length" class="d-flex flex-column ga-1 mb-2">
                        <div v-for="progress in session.disease_progress" :key="progress.disease_id" class="d-flex align-center ga-1">
                            <v-icon :icon="outcomeIcon(progress.outcome)" :color="outcomeColor(progress.outcome)" size="small" />
                            <span class="text-body-2">
                                {{ progress.disease_label }} — {{ outcomeLabel(progress.outcome, progress.outcome_percentage) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="session.measurements.length" class="d-flex flex-wrap ga-2">
                        <v-chip v-for="measurement in session.measurements" :key="measurement.measurement_type_option_id" size="small" variant="tonal">
                            {{ measurement.measurement_type_label }} : {{ measurement.value }}{{ measurement.unit ? ` ${measurement.unit}` : '' }}
                        </v-chip>
                    </div>
                </v-card-text>
            </AppCard>
        </v-timeline-item>
    </v-timeline>
</template>
