<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, { type AppDataTableColumn } from '@/Components/App/AppDataTable.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Practitioner {
    id: number;
    first_name: string;
    last_name: string;
    full_code: string;
}

interface Availability {
    id: number;
    practitioner_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    practitioner?: Practitioner;
}

const props = defineProps<{
    availabilities: Availability[];
    practitioners: Practitioner[];
}>();

const dayLabels = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const dayOptions = dayLabels.map((label, id) => ({ id, name: label }));

const practitionerOptions = props.practitioners.map((practitioner) => ({
    id: practitioner.id,
    name: `${practitioner.first_name} ${practitioner.last_name} (${practitioner.full_code})`,
}));

const columns: AppDataTableColumn[] = [
    { field: 'practitioner', header: 'Praticien' },
    { field: 'day_of_week', header: 'Jour' },
    { field: 'start_time', header: 'Début' },
    { field: 'end_time', header: 'Fin' },
    { field: 'actions', header: 'Actions' },
];

function practitionerLabel(availability: Availability): string {
    const practitioner = availability.practitioner;
    return practitioner ? `${practitioner.first_name} ${practitioner.last_name} (${practitioner.full_code})` : '';
}

const isCreating = ref(false);

const createForm = useForm({
    practitioner_id: null as number | null,
    day_of_week: null as number | null,
    start_time: '',
    end_time: '',
});

function openCreate() {
    createForm.reset();
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.availabilities.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

function destroy(availability: Availability) {
    if (!confirm('Supprimer ce créneau de disponibilité ?')) {
        return;
    }

    router.delete(route('admin.availabilities.destroy', availability.id));
}
</script>

<template>
    <Head title="Disponibilités" />

    <AuthenticatedLayout>
        <AppPageHeader title="Disponibilités" :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Disponibilités' }]">
            <template #actions>
                <AppButton label="Nouveau créneau" icon="mdi-plus" @click="openCreate" />
            </template>
        </AppPageHeader>

        <AppCard variant="elevated" elevation="1">
            <AppDataTable :value="availabilities" :columns="columns" :rows="availabilities.length" :total-records="availabilities.length" :page="1">
                <template #column-practitioner="{ item }">{{ practitionerLabel(item) }}</template>
                <template #column-day_of_week="{ item }">{{ dayLabels[item.day_of_week] }}</template>
                <template #column-start_time="{ item }">{{ item.start_time }}</template>
                <template #column-end_time="{ item }">{{ item.end_time }}</template>
                <template #actions="{ item }">
                    <AppButton label="Supprimer" severity="danger" size="small" @click="destroy(item)" />
                </template>
            </AppDataTable>
        </AppCard>

        <AppDialog v-model:visible="isCreating" header="Nouveau créneau de disponibilité">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppSelect
                    v-model="createForm.practitioner_id"
                    :options="practitionerOptions"
                    option-label="name"
                    option-value="id"
                    label="Praticien"
                    :error="createForm.errors.practitioner_id"
                />

                <AppSelect
                    v-model="createForm.day_of_week"
                    :options="dayOptions"
                    option-label="name"
                    option-value="id"
                    label="Jour de la semaine"
                    :error="createForm.errors.day_of_week"
                />

                <v-row>
                    <v-col cols="6">
                        <AppInputText v-model="createForm.start_time" type="time" label="Heure de début" :error="createForm.errors.start_time" />
                    </v-col>
                    <v-col cols="6">
                        <AppInputText v-model="createForm.end_time" type="time" label="Heure de fin" :error="createForm.errors.end_time" />
                    </v-col>
                </v-row>

                <div class="d-flex justify-end ga-2">
                    <AppButton type="button" label="Annuler" severity="secondary" @click="isCreating = false" />
                    <AppButton type="submit" label="Créer" :loading="createForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
