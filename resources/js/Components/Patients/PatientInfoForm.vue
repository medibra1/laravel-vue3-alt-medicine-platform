<script setup lang="ts">
import { tr } from '@/lib/i18n';
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
    { label: tr('ui.homme'), value: 'male' },
    { label: tr('ui.femme'), value: 'female' },
];

const maritalStatusOptions = [
    { label: tr('ui.celibataire'), value: 'single' },
    { label: tr('ui.marieE'), value: 'married' },
    { label: tr('ui.divorceE'), value: 'divorced' },
    { label: tr('ui.veufVeuve'), value: 'widowed' },
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
        :title="$t('ui.enregistrementImpossible')"
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
                            :label="$t('ui.centreDAccueil')"
                            :placeholder="$t('ui.choisirUnCentre')"
                            :error="fieldErrors.intake_center_id"
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.first_name" :label="$t('ui.prenom')" :error="fieldErrors.first_name" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.last_name" :label="$t('ui.nom')" :error="fieldErrors.last_name" />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppSelect
                            v-model="form.gender"
                            :options="genderOptions"
                            option-label="label"
                            option-value="value"
                            :label="$t('ui.genre')"
                            show-clear
                            :placeholder="$t('ui.nonRenseigne')"
                            :error="fieldErrors.gender"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppDatePicker v-model="birthDateBinding" :label="$t('ui.dateDeNaissance')" />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppSelect
                            v-model="form.marital_status"
                            :options="maritalStatusOptions"
                            option-label="label"
                            option-value="value"
                            :label="$t('ui.situationMatrimoniale')"
                            show-clear
                            :placeholder="$t('ui.nonRenseignee')"
                            :error="fieldErrors.marital_status"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputNumber
                            v-model="form.children_count"
                            :label="$t('ui.nombreDEnfants')"
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
                            :label="$t('ui.religion')"
                            show-clear
                            :placeholder="$t('ui.nonRenseignee')"
                            :error="fieldErrors.religion_option_id"
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.phone" :label="$t('ui.telephone')" :error="fieldErrors.phone" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.email" type="email" :label="$t('ui.email')" />
                    </v-col>

                    <v-col cols="12">
                        <AppInputText v-model="form.city" :label="$t('ui.ville')" :error="fieldErrors.city" />
                    </v-col>

                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.emergency_contact_name" :label="$t('ui.contactDUrgenceNom')" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.emergency_contact_phone" :label="$t('ui.contactDUrgenceTelephone')" />
                    </v-col>

                    <v-col cols="12">
                        <AppTextarea v-model="form.notes" :label="$t('ui.notes')" :rows="3" />
                    </v-col>
                </v-row>
                </fieldset>

                <div class="d-flex justify-end ga-2 mt-2">
                    <AppButton type="button" :label="$t('ui.retourALaListe')" severity="secondary" @click="emit('cancel')" />
                    <AppButton v-if="!readonly" type="submit" :label="$t('ui.confirmer')" :loading="confirming" />
                </div>
            </form>
        </v-card-text>
    </AppCard>
</template>
