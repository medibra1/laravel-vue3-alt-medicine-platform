<script setup lang="ts">
import TreatmentWizardDialog from '@/Components/Patients/TreatmentWizardDialog.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Center {
    id: number;
    name: string;
    code: string;
}

interface PatientOption {
    id: number;
    first_name: string | null;
    last_name: string | null;
}

interface PractitionerOption {
    id: number;
    first_name: string;
    last_name: string;
    full_code: string;
}

interface DiseaseOption {
    id: number;
    code: string;
    label: string;
    category_id: number;
    category_label: string;
}

interface DiseaseCategoryOption {
    id: number;
    code: string;
    label: string;
}

interface CareItemOption {
    id: number;
    code: string;
    label: string;
}

interface CareCategoryOption {
    id: number;
    code: string;
    label: string;
    items: CareItemOption[];
}

interface Treatment {
    id: number;
    client_uuid: string;
    patient_id: number;
    practitioner_id: number | null;
    center_id: number | null;
    started_at: string | null;
    ended_at: string | null;
    outcome: string | null;
    outcome_percentage: number | null;
    notes: string | null;
    disease_ids: number[];
    locked_disease_ids: number[];
}

defineProps<{
    treatment: Treatment | null;
    centers: Center[];
    patients: PatientOption[];
    practitioners: PractitionerOption[];
    diseases: DiseaseOption[];
    diseaseCategories: DiseaseCategoryOption[];
    careCategories: CareCategoryOption[];
}>();

const visible = ref(true);

// TreatmentController::confirm() redirects to the patient's own file, not
// back here — Inertia already follows that redirect and swaps the page
// before this dialog's "saved" event even fires. Only force a trip back to
// the flat list when the dialog is closed *without* having saved (the
// "Fermer" button, or an ESC/backdrop dismiss) — closing it after a
// successful confirm must not fight the navigation that already happened.
let wasSaved = false;

function onSaved() {
    wasSaved = true;
}

function onClose(value: boolean) {
    visible.value = value;

    if (!value && !wasSaved) {
        router.get(route('admin.treatments.index'));
    }
}
</script>

<template>
    <Head :title="treatment ? 'Modifier le traitement' : 'Nouveau traitement'" />

    <AuthenticatedLayout>
        <template #header>{{ treatment ? 'Modifier le traitement' : 'Nouveau traitement' }}</template>

        <TreatmentWizardDialog
            :visible="visible"
            :treatment="treatment"
            :centers="centers"
            :patients="patients"
            :practitioners="practitioners"
            :diseases="diseases"
            :disease-categories="diseaseCategories"
            :care-categories="careCategories"
            :locked-disease-ids="treatment?.locked_disease_ids ?? []"
            @update:visible="onClose"
            @saved="onSaved"
        />
    </AuthenticatedLayout>
</template>
