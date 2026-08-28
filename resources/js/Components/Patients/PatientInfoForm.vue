<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import { fromLocalDateString, toLocalDateString } from '@/utils/date';
import { computed } from 'vue';

interface Center {
    id: number;
    name: string;
    code: string;
}

interface ReligionOption {
    id: number;
    code: string;
    label: string;
}

interface PatientForm {
    intake_center_id: number | null;
    first_name: string | null;
    last_name: string | null;
    gender: string | null;
    marital_status: string | null;
    children_count: number | null;
    religion_option_id: number | null;
    birth_date: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
}

const props = withDefaults(
    defineProps<{
        form: PatientForm;
        centers: Center[];
        religionOptions?: ReligionOption[];
        fieldErrors: Record<string, string>;
        savedLabel: string;
        saveErrors: Record<string, string[]>;
        confirming: boolean;
        /** A read-only practitioner can view but never edit — see CLAUDE.md. */
        readonly?: boolean;
    }>(),
    { readonly: false, religionOptions: () => [] },
);

const emit = defineEmits<{ confirm: []; cancel: [] }>();

const genderOptions = [
    { label: 'Homme', value: 'male' },
    { label: 'Femme', value: 'female' },
];

const maritalStatusOptions = [
    { label: 'Célibataire', value: 'single' },
    { label: 'Marié(e)', value: 'married' },
    { label: 'Divorcé(e)', value: 'divorced' },
    { label: 'Veuf/Veuve', value: 'widowed' },
];

const birthDateBinding = computed<Date | null>({
    get: () => fromLocalDateString(props.form.birth_date),
    set: (value) => {
        props.form.birth_date = value ? toLocalDateString(value) : null;
    },
});
</script>

<template>
    <p class="text-body-2 text-medium-emphasis mb-4">{{ savedLabel }}</p>

    <v-alert
        v-if="Object.keys(saveErrors).length"
        type="error"
        variant="tonal"
        class="mb-4"
        title="Enregistrement impossible"
    >
        <ul class="ps-4">
            <li v-for="(messages, field) in saveErrors" :key="field">{{ messages[0] }}</li>
        </ul>
    </v-alert>

    <AppCard variant="elevated" elevation="1">
        <v-card-text>
            <form @submit.prevent="emit('confirm')">
                <fieldset :disabled="readonly" style="border: none; padding: 0; margin: 0">
                <v-row>
                    <v-col cols="12">
                        <AppSelect
                            v-if="centers.length"
                            v-model="form.intake_center_id"
                            :options="centers"
                            option-label="name"
                            option-value="id"
                            label="Centre d'accueil"
                            placeholder="Choisir un centre"
                            :error="fieldErrors.intake_center_id"
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.first_name" label="Prénom" :error="fieldErrors.first_name" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.last_name" label="Nom" :error="fieldErrors.last_name" />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppSelect
                            v-model="form.gender"
                            :options="genderOptions"
                            option-label="label"
                            option-value="value"
                            label="Genre"
                            show-clear
                            placeholder="Non renseigné"
                            :error="fieldErrors.gender"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppDatePicker v-model="birthDateBinding" label="Date de naissance" />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppSelect
                            v-model="form.marital_status"
                            :options="maritalStatusOptions"
                            option-label="label"
                            option-value="value"
                            label="Situation matrimoniale"
                            show-clear
                            placeholder="Non renseignée"
                            :error="fieldErrors.marital_status"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputNumber
                            v-model="form.children_count"
                            label="Nombre d'enfants"
                            :min="0"
                            :error="fieldErrors.children_count"
                        />
                    </v-col>

                    <v-col cols="12">
                        <AppSelect
                            v-model="form.religion_option_id"
                            :options="religionOptions"
                            option-label="label"
                            option-value="id"
                            label="Religion"
                            show-clear
                            placeholder="Non renseignée"
                            :error="fieldErrors.religion_option_id"
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.phone" label="Téléphone" :error="fieldErrors.phone" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.email" type="email" label="Email" />
                    </v-col>

                    <v-col cols="12">
                        <AppInputText v-model="form.city" label="Ville" :error="fieldErrors.city" />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.emergency_contact_name" label="Contact d'urgence — nom" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.emergency_contact_phone" label="Contact d'urgence — téléphone" />
                    </v-col>

                    <v-col cols="12">
                        <AppTextarea v-model="form.notes" label="Notes" :rows="3" />
                    </v-col>
                </v-row>
                </fieldset>

                <div class="d-flex justify-end ga-2 mt-2">
                    <AppButton type="button" label="Retour à la liste" severity="secondary" @click="emit('cancel')" />
                    <AppButton v-if="!readonly" type="submit" label="Confirmer" :loading="confirming" />
                </div>
            </form>
        </v-card-text>
    </AppCard>
</template>
