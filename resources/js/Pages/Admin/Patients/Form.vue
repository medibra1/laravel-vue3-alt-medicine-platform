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

const { form, serverId, saving, lastSavedAt, scheduleSave, flush } =
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
    await flush();

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
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ patient ? 'Modifier le patient' : 'Nouveau patient' }}
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-500">{{ savedLabel }}</p>

                <form class="flex flex-col gap-4 rounded-lg bg-white p-6 shadow" @submit.prevent="confirmPatient">
                    <div v-if="centers.length" class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Centre d'accueil</label>
                        <AppSelect
                            v-model="form.intake_center_id"
                            :options="centers"
                            option-label="name"
                            option-value="id"
                            placeholder="Choisir un centre"
                            :error="confirmErrors.intake_center_id"
                        />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Prénom</label>
                        <AppInputText v-model="form.first_name" :error="confirmErrors.first_name" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Nom</label>
                        <AppInputText v-model="form.last_name" :error="confirmErrors.last_name" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Genre</label>
                        <AppSelect
                            v-model="form.gender"
                            :options="genderOptions"
                            option-label="label"
                            option-value="value"
                            show-clear
                            placeholder="Non renseigné"
                            :error="confirmErrors.gender"
                        />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Date de naissance</label>
                        <AppDatePicker v-model="birthDateBinding" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Téléphone</label>
                        <AppInputText v-model="form.phone" :error="confirmErrors.phone" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Email</label>
                        <AppInputText v-model="form.email" type="email" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Ville</label>
                        <AppInputText v-model="form.city" :error="confirmErrors.city" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Contact d'urgence — nom</label>
                        <AppInputText v-model="form.emergency_contact_name" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Contact d'urgence — téléphone</label>
                        <AppInputText v-model="form.emergency_contact_phone" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600">Notes</label>
                        <AppTextarea v-model="form.notes" :rows="3" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <AppButton
                            type="button"
                            label="Retour à la liste"
                            severity="secondary"
                            @click="router.get(route('admin.patients.index'))"
                        />
                        <AppButton type="submit" label="Confirmer" :loading="confirming" />
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
