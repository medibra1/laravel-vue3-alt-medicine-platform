<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import TreatmentTimeline from '@/Components/Patients/TreatmentTimeline.vue';
import { outcomeColor, outcomeLabel } from '@/utils/diseaseOutcome';

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

interface TreatmentSummary {
    id: number;
    client_uuid: string;
    practitioner_id: number | null;
    center_id: number | null;
    started_at: string | null;
    ended_at: string | null;
    outcome: string | null;
    outcome_percentage: number | null;
    notes: string | null;
    status: string | null;
    closure_reason: string | null;
    practitioner: { id: number; first_name: string; last_name: string; full_code: string } | null;
    diseases: TreatmentDisease[];
    sessions: TreatmentSessionSummary[];
    locked_disease_ids: number[];
    latest_known_outcomes: Record<number, { outcome: string | null; outcome_percentage: number | null; notes: string | null }>;
}

const props = defineProps<{
    treatment: TreatmentSummary;
}>();

const emit = defineEmits<{
    edit: [treatment: TreatmentSummary];
    'add-session': [treatment: TreatmentSummary];
    close: [treatment: TreatmentSummary];
    reopen: [treatment: TreatmentSummary];
    'edit-session': [treatment: TreatmentSummary, session: TreatmentSessionSummary];
    'delete-session': [treatment: TreatmentSummary, session: TreatmentSessionSummary];
}>();

const treatmentStatusLabels: Record<string, string> = {
    draft: 'Brouillon',
    confirmed: 'Confirmé',
    ongoing: 'En cours',
    closed: 'Fermé',
};

const closureReasonLabels: Record<string, string> = {
    resolved: 'toutes les maladies résolues',
    lost_to_follow_up: 'perdu de vue',
    closed_manually: 'clôture manuelle',
};

function statusLabel(): string {
    const base = props.treatment.status ? (treatmentStatusLabels[props.treatment.status] ?? props.treatment.status) : '—';

    if (props.treatment.status === 'closed' && props.treatment.closure_reason) {
        return `${base} (${closureReasonLabels[props.treatment.closure_reason] ?? props.treatment.closure_reason})`;
    }

    return base;
}

function statusColor(): string {
    if (props.treatment.status === 'ongoing') return 'primary';
    if (props.treatment.status === 'closed') return props.treatment.closure_reason === 'resolved' ? 'success' : 'warning';

    return 'secondary';
}

// The disease chip at the top of the card is the single current-status
// source (same color/label mapping as TreatmentTimeline below it, which is
// only the session-by-session history that led here) — not two separate
// notions of "disease status".
function diseaseStatusColor(diseaseId: number): string {
    const latest = props.treatment.latest_known_outcomes[diseaseId];

    return latest ? outcomeColor(latest.outcome) : 'secondary';
}

function diseaseStatusLabel(diseaseId: number): string {
    const latest = props.treatment.latest_known_outcomes[diseaseId];

    return latest ? outcomeLabel(latest.outcome, latest.outcome_percentage) : 'Pas encore de suivi';
}
</script>

<template>
    <AppCard variant="outlined">
        <v-card-text>
            <div class="d-flex justify-space-between align-start mb-2">
                <div>
                    <div class="d-flex align-center ga-2 mb-1">
                        <p class="text-subtitle-1 mb-0">
                            Début : {{ treatment.started_at ? new Date(treatment.started_at).toLocaleDateString() : '—' }}
                        </p>
                        <v-chip size="small" :color="statusColor()" variant="tonal">{{ statusLabel() }}</v-chip>
                    </div>
                    <p class="text-body-2 text-medium-emphasis">
                        Praticien :
                        {{
                            treatment.practitioner
                                ? `${treatment.practitioner.first_name} ${treatment.practitioner.last_name} (${treatment.practitioner.full_code})`
                                : '—'
                        }}
                    </p>
                </div>
                <div class="d-flex ga-2">
                    <AppButton label="Modifier" severity="secondary" size="small" @click="emit('edit', treatment)" />
                    <AppButton
                        v-if="treatment.status === 'ongoing'"
                        label="Ajouter une séance"
                        size="small"
                        @click="emit('add-session', treatment)"
                    />
                    <AppButton
                        v-if="treatment.status === 'ongoing'"
                        label="Clôturer"
                        severity="secondary"
                        size="small"
                        @click="emit('close', treatment)"
                    />
                    <AppButton
                        v-if="treatment.status === 'closed'"
                        label="Rouvrir"
                        severity="secondary"
                        size="small"
                        @click="emit('reopen', treatment)"
                    />
                </div>
            </div>

            <div class="d-flex flex-wrap ga-2 mb-3">
                <v-chip
                    v-for="disease in treatment.diseases"
                    :key="disease.id"
                    size="small"
                    variant="tonal"
                    :color="diseaseStatusColor(disease.id)"
                >
                    {{ disease.code }} — {{ disease.label }} · {{ diseaseStatusLabel(disease.id) }}
                </v-chip>
            </div>

            <div v-if="treatment.sessions.length">
                <p class="text-body-2 font-weight-medium mb-2">Séances</p>
                <TreatmentTimeline
                    :sessions="treatment.sessions"
                    :treatment-status="treatment.status ?? ''"
                    @edit-session="(session) => emit('edit-session', treatment, session)"
                    @delete-session="(session) => emit('delete-session', treatment, session)"
                />
            </div>
        </v-card-text>
    </AppCard>
</template>
