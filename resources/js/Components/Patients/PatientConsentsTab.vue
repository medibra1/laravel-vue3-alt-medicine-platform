<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppFileUpload from '@/Components/App/AppFileUpload.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSignaturePad from '@/Components/App/AppSignaturePad.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type ConsentType = 'treatment' | 'data_privacy' | 'image_rights';
type ConsentSource = 'digital' | 'uploaded';

interface ConsentTemplate {
    type: ConsentType;
    title: string;
    content: string;
    version: number;
}

interface Consent {
    id: number;
    type: ConsentType;
    source: ConsentSource;
    version: number | null;
    template_version: number | null;
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

// Only meaningful for a 'digital' consent — an 'uploaded' paper has no
// template to compare against (template_version stays null), so it's
// never flagged for renewal by version drift.
function isUpToDate(type: ConsentType): boolean {
    const consent = latestConsentFor(type);
    if (!consent) return false;
    if (consent.source === 'uploaded') return true;

    const template = templateFor(type);
    return !!template && consent.template_version === template.version;
}

// Step 1 of the dialog: which type, then which source. Kept as two
// separate refs (not a single "step" enum) — the type is picked by
// clicking a specific card's button, the source choice only appears
// once a type is known.
const dialogVisible = ref(false);
const dialogType = ref<ConsentType | null>(null);
const dialogSource = ref<ConsentSource | null>(null);

const signerName = ref(props.patientFullName);
const signatureSvg = ref<string | null>(null);
const accepted = ref(false);
const acceptedAt = ref<Date | null>(null);
const files = ref<File[]>([]);
const submitting = ref(false);
const submitError = ref<string | null>(null);

const activeTemplate = computed(() => (dialogType.value ? templateFor(dialogType.value) : null));

function openDialog(type: ConsentType) {
    dialogType.value = type;
    dialogSource.value = null;
    resetForm();
    dialogVisible.value = true;
}

function chooseSource(source: ConsentSource) {
    dialogSource.value = source;
}

function resetForm() {
    signerName.value = props.patientFullName;
    signatureSvg.value = null;
    accepted.value = false;
    acceptedAt.value = null;
    files.value = [];
    submitError.value = null;
}

function submit() {
    if (!dialogType.value || !dialogSource.value) return;

    submitting.value = true;
    submitError.value = null;

    router.post(
        route('admin.patients.consents.store', props.patientId),
        {
            type: dialogType.value,
            source: dialogSource.value,
            signer_name: signerName.value,
            signature_svg: signatureSvg.value,
            accepted: accepted.value,
            accepted_at: acceptedAt.value ? acceptedAt.value.toISOString().slice(0, 10) : null,
            files: files.value,
        },
        {
            forceFormData: true,
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
                        <v-chip size="small" variant="outlined">
                            {{ latestConsentFor(type)!.source === 'uploaded' ? 'Document importé' : 'Signature électronique' }}
                        </v-chip>
                        <span class="text-body-2">
                            Signé par {{ latestConsentFor(type)!.signer_name }} le
                            {{ new Date(latestConsentFor(type)!.accepted_at).toLocaleDateString() }}
                            <template v-if="latestConsentFor(type)!.version">(v{{ latestConsentFor(type)!.version }})</template>
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
                        @click="openDialog(type)"
                    />
                </div>
            </v-card-text>
        </AppCard>

        <AppDialog v-model:visible="dialogVisible" :header="activeTemplate?.title ?? (dialogType ? typeLabels[dialogType] : undefined)" max-width="640px">
            <div v-if="dialogType" class="d-flex flex-column ga-4">
                <!-- Step 1: pick how this consent is being recorded. -->
                <div v-if="!dialogSource" class="d-flex flex-column ga-3">
                    <p class="text-body-2 text-medium-emphasis mb-0">Comment ce consentement a-t-il été recueilli ?</p>

                    <AppCard clickable variant="outlined" @click="chooseSource('digital')">
                        <v-card-text class="d-flex align-center ga-3">
                            <v-icon icon="mdi-draw" size="32" color="primary" />
                            <div>
                                <p class="text-body-1 mb-0">Signature électronique</p>
                                <p class="text-caption text-medium-emphasis mb-0">
                                    Le patient signe directement dans l'application, sur le texte du modèle actif.
                                </p>
                            </div>
                        </v-card-text>
                    </AppCard>

                    <AppCard clickable variant="outlined" @click="chooseSource('uploaded')">
                        <v-card-text class="d-flex align-center ga-3">
                            <v-icon icon="mdi-file-upload-outline" size="32" color="primary" />
                            <div>
                                <p class="text-body-1 mb-0">Document déjà signé</p>
                                <p class="text-caption text-medium-emphasis mb-0">
                                    Le patient a signé un document papier — importer une photo ou un scan.
                                </p>
                            </div>
                        </v-card-text>
                    </AppCard>
                </div>

                <!-- Step 2a: digital signature, against the active template. -->
                <template v-else-if="dialogSource === 'digital'">
                    <p v-if="!activeTemplate" class="text-body-2 text-error mb-0">
                        Aucun modèle actif pour ce type — à configurer en administration avant de pouvoir recueillir une
                        signature électronique.
                    </p>
                    <template v-else>
                        <div class="consent-text text-body-2">{{ activeTemplate.content }}</div>

                        <AppInputText v-model="signerName" label="Nom du signataire" />

                        <AppSignaturePad v-model="signatureSvg" label="Signature (optionnelle)" />

                        <AppCheckbox v-model="accepted" label="Le patient (ou son représentant) accepte les termes ci-dessus." />

                        <p v-if="submitError" class="text-caption text-error mb-0">{{ submitError }}</p>

                        <div class="d-flex justify-space-between ga-2">
                            <AppButton label="Retour" severity="secondary" @click="dialogSource = null" />
                            <AppButton label="Valider" :loading="submitting" @click="submit" />
                        </div>
                    </template>
                </template>

                <!-- Step 2b: an already-signed paper document, imported as-is. -->
                <template v-else>
                    <AppInputText v-model="signerName" label="Nom du signataire" />

                    <AppDatePicker v-model="acceptedAt" label="Date de signature du document" />

                    <AppFileUpload
                        v-model="files"
                        multiple
                        label="Document signé (plusieurs photos d'un même document sont fusionnées en un seul PDF)"
                    />

                    <AppCheckbox v-model="accepted" label="Le patient (ou son représentant) a bien accepté les termes présentés." />

                    <p v-if="submitError" class="text-caption text-error mb-0">{{ submitError }}</p>

                    <div class="d-flex justify-space-between ga-2">
                        <AppButton label="Retour" severity="secondary" @click="dialogSource = null" />
                        <AppButton label="Valider" :loading="submitting" @click="submit" />
                    </div>
                </template>
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
