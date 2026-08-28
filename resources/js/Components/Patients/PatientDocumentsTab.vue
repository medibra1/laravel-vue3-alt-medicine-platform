<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppFileUpload from '@/Components/App/AppFileUpload.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface PatientDocument {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    download_url: string;
    thumb_url: string | null;
    created_at: string;
}

const props = defineProps<{
    patientId: number;
    identity: PatientDocument | null;
    medical: PatientDocument[];
    other: PatientDocument[];
    readonly: boolean;
}>();

const emit = defineEmits<{ saved: [] }>();

const pendingFiles = ref<Record<'identity' | 'medical' | 'other', File[]>>({
    identity: [],
    medical: [],
    other: [],
});

const uploading = ref<Record<'identity' | 'medical' | 'other', boolean>>({
    identity: false,
    medical: false,
    other: false,
});

const uploadErrors = ref<Record<'identity' | 'medical' | 'other', string | null>>({
    identity: null,
    medical: null,
    other: null,
});

function upload(collection: 'identity' | 'medical' | 'other') {
    if (pendingFiles.value[collection].length === 0) return;

    uploading.value[collection] = true;
    uploadErrors.value[collection] = null;

    router.post(
        route('admin.patients.documents.store', props.patientId),
        {
            collection,
            files: pendingFiles.value[collection],
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                pendingFiles.value[collection] = [];
                emit('saved');
            },
            onError: (errors) => {
                uploadErrors.value[collection] = Object.values(errors)[0] as string;
            },
            onFinish: () => {
                uploading.value[collection] = false;
            },
        },
    );
}

function destroy(document: PatientDocument) {
    if (!confirm(`Supprimer "${document.name}" ?`)) return;

    router.delete(route('admin.patients.documents.destroy', [props.patientId, document.id]), {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}
</script>

<template>
    <div class="d-flex flex-column ga-6">
        <!-- Identity document -->
        <AppCard variant="elevated" elevation="1" title="Pièce d'identité">
            <v-card-text class="d-flex flex-column ga-3">
                <div v-if="identity" class="d-flex align-center ga-3">
                    <v-avatar v-if="identity.thumb_url" size="56" rounded>
                        <v-img :src="identity.thumb_url" cover />
                    </v-avatar>
                    <v-icon v-else icon="mdi-file-pdf-box" size="40" color="error" />

                    <div class="flex-grow-1">
                        <p class="text-body-2 mb-0">{{ identity.name }}</p>
                        <p class="text-caption text-medium-emphasis mb-0">{{ formatSize(identity.size) }}</p>
                    </div>

                    <AppButton
                        as="a"
                        :href="identity.download_url"
                        target="_blank"
                        icon="mdi-download"
                        severity="secondary"
                        size="small"
                    />
                    <AppButton
                        v-if="!readonly"
                        icon="mdi-delete"
                        severity="danger"
                        size="small"
                        @click="destroy(identity)"
                    />
                </div>
                <p v-else class="text-body-2 text-medium-emphasis mb-0">Aucune pièce d'identité enregistrée.</p>

                <template v-if="!readonly">
                    <AppFileUpload
                        v-model="pendingFiles.identity"
                        :error="uploadErrors.identity"
                        label="Remplacer par une nouvelle pièce d'identité"
                    />
                    <AppButton
                        v-if="pendingFiles.identity.length"
                        label="Enregistrer"
                        :loading="uploading.identity"
                        @click="upload('identity')"
                    />
                </template>
            </v-card-text>
        </AppCard>

        <!-- Medical documents -->
        <AppCard variant="elevated" elevation="1" title="Documents médicaux">
            <v-card-text class="d-flex flex-column ga-3">
                <p v-if="!medical.length" class="text-body-2 text-medium-emphasis mb-0">
                    Aucun document médical enregistré.
                </p>

                <div v-for="document in medical" :key="document.id" class="d-flex align-center ga-3">
                    <v-avatar v-if="document.thumb_url" size="56" rounded>
                        <v-img :src="document.thumb_url" cover />
                    </v-avatar>
                    <v-icon v-else icon="mdi-file-pdf-box" size="40" color="error" />

                    <div class="flex-grow-1">
                        <p class="text-body-2 mb-0">{{ document.name }}</p>
                        <p class="text-caption text-medium-emphasis mb-0">{{ formatSize(document.size) }}</p>
                    </div>

                    <AppButton
                        as="a"
                        :href="document.download_url"
                        target="_blank"
                        icon="mdi-download"
                        severity="secondary"
                        size="small"
                    />
                    <AppButton
                        v-if="!readonly"
                        icon="mdi-delete"
                        severity="danger"
                        size="small"
                        @click="destroy(document)"
                    />
                </div>

                <template v-if="!readonly">
                    <AppFileUpload
                        v-model="pendingFiles.medical"
                        multiple
                        :error="uploadErrors.medical"
                        label="Ajouter des documents médicaux (plusieurs photos d'un même document sont fusionnées en un seul PDF)"
                    />
                    <AppButton
                        v-if="pendingFiles.medical.length"
                        label="Enregistrer"
                        :loading="uploading.medical"
                        @click="upload('medical')"
                    />
                </template>
            </v-card-text>
        </AppCard>

        <!-- Other documents -->
        <AppCard variant="elevated" elevation="1" title="Autres documents">
            <v-card-text class="d-flex flex-column ga-3">
                <p v-if="!other.length" class="text-body-2 text-medium-emphasis mb-0">
                    Aucun autre document enregistré.
                </p>

                <div v-for="document in other" :key="document.id" class="d-flex align-center ga-3">
                    <v-avatar v-if="document.thumb_url" size="56" rounded>
                        <v-img :src="document.thumb_url" cover />
                    </v-avatar>
                    <v-icon v-else icon="mdi-file-pdf-box" size="40" color="error" />

                    <div class="flex-grow-1">
                        <p class="text-body-2 mb-0">{{ document.name }}</p>
                        <p class="text-caption text-medium-emphasis mb-0">{{ formatSize(document.size) }}</p>
                    </div>

                    <AppButton
                        as="a"
                        :href="document.download_url"
                        target="_blank"
                        icon="mdi-download"
                        severity="secondary"
                        size="small"
                    />
                    <AppButton
                        v-if="!readonly"
                        icon="mdi-delete"
                        severity="danger"
                        size="small"
                        @click="destroy(document)"
                    />
                </div>

                <template v-if="!readonly">
                    <AppFileUpload v-model="pendingFiles.other" multiple :error="uploadErrors.other" label="Ajouter un document" />
                    <AppButton
                        v-if="pendingFiles.other.length"
                        label="Enregistrer"
                        :loading="uploading.other"
                        @click="upload('other')"
                    />
                </template>
            </v-card-text>
        </AppCard>
    </div>
</template>
