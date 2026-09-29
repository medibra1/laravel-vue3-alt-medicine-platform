<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import { http } from '@/lib/http';
import type { CenterClosure } from '@/utils/centerClosure';
import { formatTimeOffPeriod } from '@/utils/timeOff';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    visible: boolean;
    center: { id: number; name: string } | null;
}>();

const emit = defineEmits<{ 'update:visible': [value: boolean] }>();

const closures = ref<CenterClosure[]>([]);
const affectedWarning = ref<number | null>(null);

const form = useForm({ starts_on: '', ends_on: '', label: '', notes: '' });

async function loadClosures() {
    if (!props.center) return;
    closures.value = await http.get<CenterClosure[]>(route('admin.centers.closures.index', { center: props.center.id, upcoming: 1 }));
}

watch(
    () => props.visible,
    (visible) => {
        if (!visible) return;
        form.reset();
        form.clearErrors();
        affectedWarning.value = null;
        loadClosures();
    },
    { immediate: true },
);

// Same single-day default as PractitionerTimeOffDialog.
watch(
    () => form.starts_on,
    (startsOn) => {
        if (startsOn && (!form.ends_on || form.ends_on < startsOn)) {
            form.ends_on = startsOn;
        }
    },
);

function submit() {
    if (!props.center) return;

    form.post(route('admin.centers.closures.store', props.center.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            // reset() here, not only on next open — useForm recalibrates its
            // defaults on success.
            form.reset();
            const flash = page.props.flash as { time_off_affected_appointments?: number | null } | undefined;
            const affected = flash?.time_off_affected_appointments ?? 0;
            affectedWarning.value = affected > 0 ? affected : null;
            loadClosures();
        },
    });
}

function destroy(closure: CenterClosure) {
    if (!confirm(`Supprimer la fermeture « ${closure.label} » ?`)) return;

    router.delete(route('admin.closures.destroy', closure.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: loadClosures,
    });
}
</script>

<template>
    <AppDialog :visible="visible" :header="`Fermetures exceptionnelles — ${center?.name ?? ''}`" @update:visible="emit('update:visible', $event)">
        <div class="d-flex flex-column ga-4">
            <v-alert v-if="affectedWarning" type="warning" variant="tonal" closable @click:close="affectedWarning = null">
                {{
                    affectedWarning > 1
                        ? `${affectedWarning} rendez-vous existants tombent dans cette période, sur tous les praticiens du centre — pensez à les reprogrammer ou les annuler.`
                        : `1 rendez-vous existant tombe dans cette période — pensez à le reprogrammer ou l'annuler.`
                }}
            </v-alert>

            <div>
                <div class="text-subtitle-2 mb-2">En cours et à venir</div>
                <p v-if="!closures.length" class="text-body-2 text-medium-emphasis">Aucune fermeture prévue.</p>
                <div v-else class="d-flex flex-wrap ga-2">
                    <v-chip
                        v-for="closure in closures"
                        :key="closure.id"
                        color="error"
                        variant="tonal"
                        prepend-icon="mdi-store-off-outline"
                        closable
                        @click:close="destroy(closure)"
                    >
                        {{ closure.label }} · {{ formatTimeOffPeriod(closure) }}
                    </v-chip>
                </div>
            </div>

            <v-divider />

            <form class="d-flex flex-column ga-4" @submit.prevent="submit">
                <div class="text-subtitle-2">Ajouter une fermeture</div>
                <v-row>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.starts_on" type="date" label="Du" :error="form.errors.starts_on" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.ends_on" type="date" label="Au (inclus)" :error="form.errors.ends_on" />
                    </v-col>
                </v-row>
                <AppInputText v-model="form.label" label="Libellé (ex. Fête nationale, Travaux)" :error="form.errors.label" />
                <AppTextarea v-model="form.notes" label="Notes" :error="form.errors.notes" />

                <p class="text-body-2 text-medium-emphasis">
                    Aucun rendez-vous ne pourra être pris dans ce centre pendant cette période, quel que soit le praticien. Les rendez-vous déjà planifiés ne sont pas annulés.
                </p>

                <div class="d-flex justify-end ga-2">
                    <AppButton type="button" label="Fermer" severity="secondary" @click="emit('update:visible', false)" />
                    <AppButton type="submit" label="Ajouter" icon="mdi-plus" :loading="form.processing" />
                </div>
            </form>
        </div>
    </AppDialog>
</template>
