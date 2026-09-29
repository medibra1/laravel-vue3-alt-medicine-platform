<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import { type TimeOffReason, timeOffReasonOptions } from '@/utils/timeOff';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    visible: boolean;
    practitioner: { id: number; label: string } | null;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    /** Number of existing appointments that fall inside the new time off. */
    created: [affectedAppointments: number];
}>();

const form = useForm({
    starts_on: '',
    ends_on: '',
    reason: 'vacation' as TimeOffReason | null,
    notes: '',
});

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            form.reset();
            form.clearErrors();
        }
    },
);

// A single-day time off is the common case: mirror the start date until
// the end date is set explicitly.
watch(
    () => form.starts_on,
    (startsOn) => {
        if (startsOn && (!form.ends_on || form.ends_on < startsOn)) {
            form.ends_on = startsOn;
        }
    },
);

function submit() {
    if (!props.practitioner) return;

    form.post(route('admin.practitioners.time-offs.store', props.practitioner.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            // reset() here, not only on next open — see useForm's defaults
            // recalibration on success.
            form.reset();
            const flash = page.props.flash as { time_off_affected_appointments?: number | null } | undefined;
            emit('created', flash?.time_off_affected_appointments ?? 0);
            emit('update:visible', false);
        },
    });
}
</script>

<template>
    <AppDialog :visible="visible" :header="`Nouveau congé — ${practitioner?.label ?? ''}`" @update:visible="emit('update:visible', $event)">
        <form class="d-flex flex-column ga-4" @submit.prevent="submit">
            <v-row>
                <v-col cols="12" md="6">
                    <AppInputText v-model="form.starts_on" type="date" label="Du" :error="form.errors.starts_on" />
                </v-col>
                <v-col cols="12" md="6">
                    <AppInputText v-model="form.ends_on" type="date" label="Au (inclus)" :error="form.errors.ends_on" />
                </v-col>
            </v-row>

            <AppSelect
                v-model="form.reason"
                :options="timeOffReasonOptions"
                option-label="label"
                option-value="value"
                label="Motif"
                :error="form.errors.reason ?? (form.errors as Record<string, string | undefined>).practitioner_id"
            />

            <AppTextarea v-model="form.notes" label="Notes" :error="form.errors.notes" />

            <p class="text-body-2 text-medium-emphasis">
                Aucun rendez-vous ne pourra être pris pendant cette période. Les rendez-vous déjà planifiés ne sont pas annulés.
            </p>

            <div class="d-flex justify-end ga-2">
                <AppButton type="button" label="Annuler" severity="secondary" @click="emit('update:visible', false)" />
                <AppButton type="submit" label="Enregistrer" :loading="form.processing" />
            </div>
        </form>
    </AppDialog>
</template>
