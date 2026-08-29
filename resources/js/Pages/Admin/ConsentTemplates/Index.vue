<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, { type AppDataTableColumn } from '@/Components/App/AppDataTable.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ConsentTemplate {
    id: number;
    type: string;
    version: number;
    title: string;
    content: string;
    is_active: boolean;
    created_at: string;
}

const props = defineProps<{
    templates: ConsentTemplate[];
}>();

const typeLabels: Record<string, string> = {
    treatment: 'Consentement au traitement',
    data_privacy: 'Protection des données (RGPD)',
    image_rights: "Droit à l'image",
};

const typeOptions = Object.entries(typeLabels).map(([id, label]) => ({ id, label }));

const columns: AppDataTableColumn[] = [
    { field: 'type', header: 'Type' },
    { field: 'version', header: 'Version' },
    { field: 'title', header: 'Titre' },
    { field: 'is_active', header: 'Statut' },
    { field: 'actions', header: 'Actions' },
];

// Only a type with no active version yet can be created from scratch — a
// type that already has one must go through "edit" (a new version), never
// a second store() (see StoreConsentTemplateRequest's uniqueness rule).
const typesWithoutActiveTemplate = computed(() =>
    typeOptions.filter((option) => !props.templates.some((t) => t.type === option.id && t.is_active)),
);

const isCreating = ref(false);
const editingTemplate = ref<ConsentTemplate | null>(null);

const createForm = useForm({
    type: '',
    title: '',
    content: '',
});

function openCreate() {
    createForm.reset();
    isCreating.value = true;
}

function submitCreate() {
    createForm.post(route('admin.consent-templates.store'), {
        onSuccess: () => {
            isCreating.value = false;
        },
    });
}

const editForm = useForm({
    title: '',
    content: '',
});

function openEdit(template: ConsentTemplate) {
    editingTemplate.value = template;
    editForm.reset();
    editForm.title = template.title;
    editForm.content = template.content;
}

function submitEdit() {
    if (!editingTemplate.value) return;

    editForm.put(route('admin.consent-templates.update', editingTemplate.value.id), {
        onSuccess: () => (editingTemplate.value = null),
    });
}
</script>

<template>
    <Head title="Modèles de consentement" />

    <AuthenticatedLayout>
        <AppPageHeader
            title="Modèles de consentement"
            :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Modèles de consentement' }]"
        >
            <template #actions>
                <AppButton
                    label="Nouveau modèle"
                    icon="mdi-plus"
                    :disabled="!typesWithoutActiveTemplate.length"
                    @click="openCreate"
                />
            </template>
        </AppPageHeader>

        <p v-if="!typesWithoutActiveTemplate.length" class="text-body-2 text-medium-emphasis mb-4">
            Les trois types de consentement ont déjà un modèle actif — utilisez "Modifier" pour créer une nouvelle
            version.
        </p>

        <AppCard variant="elevated" elevation="1">
            <AppDataTable :value="templates" :columns="columns" :rows="templates.length || 10" :total-records="templates.length" :page="1">
                <template #column-type="{ item }">{{ typeLabels[item.type] ?? item.type }}</template>
                <template #column-is_active="{ item }">
                    <v-chip size="small" :color="item.is_active ? 'success' : 'secondary'" variant="tonal">
                        {{ item.is_active ? 'Actif' : 'Archivé' }}
                    </v-chip>
                </template>
                <template #actions="{ item }">
                    <AppButton
                        v-if="item.is_active"
                        label="Modifier"
                        severity="secondary"
                        size="small"
                        @click="openEdit(item)"
                    />
                </template>
            </AppDataTable>
        </AppCard>

        <AppDialog v-model:visible="isCreating" header="Nouveau modèle de consentement" max-width="640px">
            <form class="d-flex flex-column ga-4" @submit.prevent="submitCreate">
                <AppSelect
                    v-model="createForm.type"
                    :options="typesWithoutActiveTemplate"
                    option-label="label"
                    option-value="id"
                    label="Type"
                    :error="createForm.errors.type"
                />

                <AppInputText v-model="createForm.title" label="Titre" :error="createForm.errors.title" />

                <AppTextarea
                    v-model="createForm.content"
                    label="Texte présenté au patient"
                    :rows="8"
                    :error="createForm.errors.content"
                />

                <div class="d-flex justify-end ga-2">
                    <AppButton type="button" label="Annuler" severity="secondary" @click="isCreating = false" />
                    <AppButton type="submit" label="Créer" :loading="createForm.processing" />
                </div>
            </form>
        </AppDialog>

        <AppDialog
            :visible="editingTemplate !== null"
            header="Modifier le modèle (nouvelle version)"
            max-width="640px"
            @update:visible="editingTemplate = null"
        >
            <form class="d-flex flex-column ga-4" @submit.prevent="submitEdit">
                <p class="text-body-2 text-medium-emphasis mb-0">
                    Enregistrer crée une nouvelle version (v{{ (editingTemplate?.version ?? 0) + 1 }}) — la version
                    actuelle est archivée, jamais modifiée en place, pour ne jamais changer un texte déjà signé par
                    un patient.
                </p>

                <AppInputText v-model="editForm.title" label="Titre" :error="editForm.errors.title" />

                <AppTextarea
                    v-model="editForm.content"
                    label="Texte présenté au patient"
                    :rows="8"
                    :error="editForm.errors.content"
                />

                <div class="d-flex justify-end ga-2">
                    <AppButton type="button" label="Annuler" severity="secondary" @click="editingTemplate = null" />
                    <AppButton type="submit" label="Enregistrer" :loading="editForm.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
