<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useResilientForm } from '@/composables/useResilientForm';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Center {
    id: number;
    name: string;
    code: string;
}

interface Patient {
    id: number;
    client_uuid: string;
    first_name: string | null;
    last_name: string | null;
    gender: string | null;
    birth_date: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    intake_center_id: number;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
}

const props = defineProps<{
    patient: Patient | null;
    centers: Center[];
}>();

const genderOptions = [
    { label: 'Homme', value: 'male' },
    { label: 'Femme', value: 'female' },
];

const { form, serverId, saving, lastSavedAt, saveErrors, scheduleSave, flush } =
    useResilientForm(
        'patients',
        {
            client_uuid: props.patient?.client_uuid,
            id: props.patient?.id ?? null,
            first_name: props.patient?.first_name ?? null,
            last_name: props.patient?.last_name ?? null,
            gender: props.patient?.gender ?? null,
            birth_date: props.patient?.birth_date ?? null,
            phone: props.patient?.phone ?? null,
            email: props.patient?.email ?? null,
            city: props.patient?.city ?? null,
            intake_center_id: props.patient?.intake_center_id ?? null,
            emergency_contact_name: props.patient?.emergency_contact_name ?? null,
            emergency_contact_phone: props.patient?.emergency_contact_phone ?? null,
            notes: props.patient?.notes ?? null,
        },
        {
            create: route('admin.patients.draft.store'),
            update: (id) => route('admin.patients.draft.update', id),
        },
    );

watch(form, () => scheduleSave(), { deep: true });

const birthDateBinding = computed<Date | null>({
    get: () => (form.birth_date ? new Date(form.birth_date as string) : null),
    set: (value) => {
        form.birth_date = value ? value.toISOString().slice(0, 10) : null;
    },
});

const fieldErrors = computed<Record<string, string>>(() => ({
    ...Object.fromEntries(
        Object.entries(saveErrors.value).map(([field, messages]) => [field, messages[0]]),
    ),
    ...confirmErrors.value,
}));

const savedLabel = computed(() => {
    if (saving.value) {
        return 'Enregistrement…';
    }

    if (!lastSavedAt.value) {
        return 'Pas encore enregistré';
    }

    const time = new Date(lastSavedAt.value).toLocaleTimeString();

    return `Brouillon enregistré à ${time}`;
});

const confirming = ref(false);
const confirmErrors = ref<Record<string, string>>({});

async function confirmPatient() {
    try {
        await flush();
    } catch {
        // saveErrors (from useResilientForm) already carries the detail;
        // stop here instead of navigating to confirm with a not-yet-saved draft.
        return;
    }

    if (serverId.value === null) {
        return;
    }

    confirming.value = true;
    confirmErrors.value = {};

    router.post(
        route('admin.patients.confirm', serverId.value),
        { ...form },
        {
            onError: (errors) => {
                confirmErrors.value = errors as Record<string, string>;
            },
            onFinish: () => {
                confirming.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="patient ? 'Modifier le patient' : 'Nouveau patient'" />

    <AuthenticatedLayout>
        <template #header>{{ patient ? 'Modifier le patient' : 'Nouveau patient' }}</template>

        <div class="mx-auto" style="max-width: 640px">
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

            <v-card>
                <v-card-text>
                    <form class="d-flex flex-column ga-4" @submit.prevent="confirmPatient">
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

                        <AppInputText v-model="form.first_name" label="Prénom" :error="fieldErrors.first_name" />

                        <AppInputText v-model="form.last_name" label="Nom" :error="fieldErrors.last_name" />

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

                        <AppDatePicker v-model="birthDateBinding" label="Date de naissance" />

                        <AppInputText v-model="form.phone" label="Téléphone" :error="fieldErrors.phone" />

                        <AppInputText v-model="form.email" type="email" label="Email" />

                        <AppInputText v-model="form.city" label="Ville" :error="fieldErrors.city" />

                        <AppInputText v-model="form.emergency_contact_name" label="Contact d'urgence — nom" />

                        <AppInputText v-model="form.emergency_contact_phone" label="Contact d'urgence — téléphone" />

                        <AppTextarea v-model="form.notes" label="Notes" :rows="3" />

                        <div class="d-flex justify-end ga-2">
                            <AppButton
                                type="button"
                                label="Retour à la liste"
                                severity="secondary"
                                @click="router.get(route('admin.patients.index'))"
                            />
                            <AppButton type="submit" label="Confirmer" :loading="confirming" />
                        </div>
                    </form>
                </v-card-text>
            </v-card>
        </div>
    </AuthenticatedLayout>
</template>
