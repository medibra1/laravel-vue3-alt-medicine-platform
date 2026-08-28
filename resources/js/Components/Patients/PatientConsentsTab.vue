<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSignaturePad from '@/Components/App/AppSignaturePad.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type ConsentType = 'treatment' | 'data_privacy' | 'image_rights';

interface ConsentTemplate {
    type: ConsentType;
    title: string;
    content: string;
    version: number;
}

interface Consent {
    id: number;
    type: ConsentType;
    version: number;
    template_version: number;
    signer_name: string;
    accepted_at: string;
    accepted_by: string;
    download_url: string;
}

const props = defineProps<{
    patientId: number;
    patientFullName: string;
    consents: Consent[];
    consentTemplates: ConsentTemplate[];
    readonly: boolean;
}>();

const emit = defineEmits<{ saved: [] }>();

const typeLabels: Record<ConsentType, string> = {
    treatment: 'Consentement au traitement',
    data_privacy: 'Protection des données (RGPD)',
    image_rights: "Droit à l'image",
};

const types: ConsentType[] = ['treatment', 'data_privacy', 'image_rights'];

function latestConsentFor(type: ConsentType): Consent | null {
    return props.consents.find((c) => c.type === type) ?? null;
}

function templateFor(type: ConsentType): ConsentTemplate | null {
    return props.consentTemplates.find((t) => t.type === type) ?? null;
}

function isUpToDate(type: ConsentType): boolean {
    const consent = latestConsentFor(type);
    const template = templateFor(type);
    return !!consent && !!template && consent.template_version === template.version;
}

const dialogVisible = ref(false);
const dialogType = ref<ConsentType | null>(null);
const signerName = ref(props.patientFullName);
const signatureSvg = ref<string | null>(null);
const accepted = ref(false);
const submitting = ref(false);
const submitError = ref<string | null>(null);

const activeTemplate = computed(() => (dialogType.value ? templateFor(dialogType.value) : null));

function openDialog(type: ConsentType) {
    dialogType.value = type;
    signerName.value = props.patientFullName;
    signatureSvg.value = null;
    accepted.value = false;
    submitError.value = null;
    dialogVisible.value = true;
}

function submit() {
    if (!dialogType.value) return;

    submitting.value = true;
    submitError.value = null;

    router.post(
        route('admin.patients.consents.store', props.patientId),
        {
            type: dialogType.value,
            signer_name: signerName.value,
            signature_svg: signatureSvg.value,
            accepted: accepted.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                dialogVisible.value = false;
                emit('saved');
            },
            onError: (errors) => {
                submitError.value = Object.values(errors)[0] as string;
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="d-flex flex-column ga-4">
        <AppCard v-for="type in types" :key="type" variant="elevated" elevation="1" :title="typeLabels[type]">
            <v-card-text class="d-flex flex-column ga-3">
                <template v-if="latestConsentFor(type)">
                    <div class="d-flex align-center ga-2 flex-wrap">
                        <v-chip size="small" :color="isUpToDate(type) ? 'success' : 'warning'" variant="tonal">
                            {{ isUpToDate(type) ? 'À jour' : 'À renouveler' }}
                        </v-chip>
                        <span class="text-body-2">
                            Signé par {{ latestConsentFor(type)!.signer_name }} le
                            {{ new Date(latestConsentFor(type)!.accepted_at).toLocaleDateString() }}
                            (v{{ latestConsentFor(type)!.version }})
                        </span>
                    </div>

                    <div>
                        <AppButton
                            as="a"
                            :href="latestConsentFor(type)!.download_url"
                            target="_blank"
                            label="Télécharger le PDF"
                            icon="mdi-download"
                            severity="secondary"
                            size="small"
                        />
                    </div>
                </template>
                <p v-else class="text-body-2 text-medium-emphasis mb-0">Aucun consentement recueilli pour l'instant.</p>

                <div v-if="!readonly">
                    <AppButton
                        :label="latestConsentFor(type) ? 'Renouveler le consentement' : 'Recueillir le consentement'"
                        icon="mdi-file-sign"
                        severity="secondary"
                        size="small"
                        :disabled="!templateFor(type)"
                        @click="openDialog(type)"
                    />
                    <p v-if="!templateFor(type)" class="text-caption text-medium-emphasis mt-1 mb-0">
                        Aucun modèle actif pour ce type — à configurer en administration.
                    </p>
                </div>
            </v-card-text>
        </AppCard>

        <AppDialog v-model:visible="dialogVisible" :header="activeTemplate?.title" max-width="640px">
            <div v-if="activeTemplate" class="d-flex flex-column ga-4">
                <div class="consent-text text-body-2">{{ activeTemplate.content }}</div>

                <AppInputText v-model="signerName" label="Nom du signataire" />

                <AppSignaturePad v-model="signatureSvg" label="Signature (optionnelle)" />

                <AppCheckbox v-model="accepted" label="Le patient (ou son représentant) accepte les termes ci-dessus." />

                <p v-if="submitError" class="text-caption text-error mb-0">{{ submitError }}</p>

                <div class="d-flex justify-end ga-2">
                    <AppButton label="Annuler" severity="secondary" @click="dialogVisible = false" />
                    <AppButton label="Valider" :loading="submitting" @click="submit" />
                </div>
            </div>
        </AppDialog>
    </div>
</template>

<style scoped>
.consent-text {
    white-space: pre-wrap;
    max-height: 240px;
    overflow-y: auto;
    padding: 12px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 8px;
}
</style>
