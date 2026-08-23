<script setup lang="ts">
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
}

const props = defineProps<{
    sessions: TreatmentSessionSummary[];
    treatmentStatus: string;
}>();

defineEmits<{ 'edit-session': [session: TreatmentSessionSummary] }>();

// Only the most recent session (chronologically, not the last array entry —
// sessions aren't guaranteed sorted) gets the "primary" dot; the rest are
// "muted" once a treatment has moved on. An ongoing treatment with a single
// session still highlights it, since it's also the most recent one.
function mostRecentSessionId(sessions: TreatmentSessionSummary[]): number | null {
    let latest: TreatmentSessionSummary | null = null;

    for (const session of sessions) {
        if (!session.session_date) {
            continue;
        }

        if (!latest || !latest.session_date || session.session_date > latest.session_date) {
            latest = session;
        }
    }

    return latest?.id ?? sessions[sessions.length - 1]?.id ?? null;
}

function statusColor(session: TreatmentSessionSummary): string {
    return session.id === mostRecentSessionId(props.sessions) ? 'primary' : 'muted';
}

function formatSessionDate(session: TreatmentSessionSummary): string {
    return session.session_date ? new Date(session.session_date).toLocaleDateString() : '—';
}
</script>

<template>
    <v-timeline density="compact" side="end">
        <v-timeline-item v-for="session in sessions" :key="session.id" :dot-color="statusColor(session)" size="small">
            <template #opposite>
                <span class="text-body-2 text-medium-emphasis">{{ formatSessionDate(session) }}</span>
            </template>

            <AppCard variant="tonal" clickable @click="$emit('edit-session', session)">
                <v-card-text>
                    <p class="text-body-2 font-weight-medium mb-2">
                        {{ formatSessionDate(session) }}
                        <span v-if="session.duration_minutes" class="text-medium-emphasis font-weight-regular"> — {{ session.duration_minutes }} min</span>
                    </p>

                    <div v-if="session.care_items.length" class="d-flex flex-wrap ga-2 mb-2">
                        <v-chip v-for="item in session.care_items" :key="item.id" size="small" variant="tonal">
                            {{ item.label }}
                        </v-chip>
                    </div>

                    <div v-if="session.disease_progress.length" class="d-flex flex-column ga-1">
                        <div v-for="progress in session.disease_progress" :key="progress.disease_id" class="d-flex align-center ga-1">
                            <v-icon :icon="outcomeIcon(progress.outcome)" :color="outcomeColor(progress.outcome)" size="small" />
                            <span class="text-body-2">
                                {{ progress.disease_label }} — {{ outcomeLabel(progress.outcome, progress.outcome_percentage) }}
                            </span>
                        </div>
                    </div>
                </v-card-text>
            </AppCard>
        </v-timeline-item>
    </v-timeline>
</template>
