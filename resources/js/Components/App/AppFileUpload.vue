<script setup lang="ts">
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: File[];
        multiple?: boolean;
        accept?: string;
        label?: string;
        error?: string | null;
    }>(),
    {
        multiple: false,
        accept: 'image/jpeg,image/png,application/pdf',
        label: undefined,
        error: null,
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: File[]] }>();

const cameraInput = ref<HTMLInputElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

function onCameraChange(event: Event) {
    addFiles((event.target as HTMLInputElement).files);
    if (cameraInput.value) cameraInput.value.value = '';
}

function onFileChange(event: Event) {
    addFiles((event.target as HTMLInputElement).files);
    if (fileInput.value) fileInput.value.value = '';
}

function addFiles(fileList: FileList | null) {
    if (!fileList || fileList.length === 0) return;
    emit('update:modelValue', [...props.modelValue, ...Array.from(fileList)]);
}

function removeAt(files: File[], index: number) {
    emit('update:modelValue', files.filter((_, i) => i !== index));
}

function isImage(file: File): boolean {
    return file.type.startsWith('image/');
}

function previewUrl(file: File): string {
    return URL.createObjectURL(file);
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}
</script>

<template>
    <div class="d-flex flex-column ga-2">
        <p v-if="label" class="text-body-2 text-medium-emphasis mb-0">{{ label }}</p>

        <div class="d-flex ga-2 flex-wrap">
            <v-btn variant="tonal" prepend-icon="mdi-camera" size="small" @click="cameraInput?.click()">
                Prendre une photo
            </v-btn>
            <v-btn variant="tonal" prepend-icon="mdi-file-upload-outline" size="small" @click="fileInput?.click()">
                Choisir un fichier{{ multiple ? '(s)' : '' }}
            </v-btn>
        </div>

        <input
            ref="cameraInput"
            type="file"
            accept="image/*"
            capture="environment"
            class="d-none"
            @change="onCameraChange($event)"
        />
        <input
            ref="fileInput"
            type="file"
            :accept="accept"
            :multiple="multiple"
            class="d-none"
            @change="onFileChange($event)"
        />

        <div v-if="modelValue.length" class="d-flex ga-2 flex-wrap">
            <div
                v-for="(file, index) in modelValue"
                :key="`${file.name}-${index}`"
                class="d-flex flex-column align-center pa-2 file-preview"
            >
                <v-img
                    v-if="isImage(file)"
                    :src="previewUrl(file)"
                    width="72"
                    height="72"
                    cover
                    rounded
                />
                <v-icon v-else icon="mdi-file-pdf-box" size="56" color="error" />

                <span class="text-caption text-truncate mt-1" style="max-width: 88px">{{ file.name }}</span>
                <span class="text-caption text-medium-emphasis">{{ formatSize(file.size) }}</span>

                <v-btn
                    icon="mdi-close"
                    size="x-small"
                    variant="text"
                    class="mt-1"
                    @click="removeAt(modelValue, index)"
                />
            </div>
        </div>

        <p v-if="error" class="text-caption text-error mb-0">{{ error }}</p>
    </div>
</template>

<style scoped>
.file-preview {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 8px;
    width: 96px;
}
</style>
