<script setup lang="ts">
import AppInputText from '@/Components/App/AppInputText.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';

/**
 * Edits a spatie/laravel-translatable JSON field ({fr, en}) as two
 * locale-labeled inputs side by side. First precedent in the codebase
 * for editing a translatable attribute via a form — see CLAUDE.md
 * "Introduction des API Resources" session for why Resources must
 * expose ->getTranslations() rather than the locale-resolved string
 * when the value needs to be editable.
 */
withDefaults(
    defineProps<{
        modelValue: { fr: string; en: string };
        label: string;
        multiline?: boolean;
        error?: Record<string, string | undefined> | null;
    }>(),
    {
        multiline: false,
        error: null,
    },
);

defineEmits<{ 'update:modelValue': [value: { fr: string; en: string }] }>();
</script>

<template>
    <div class="d-flex flex-column ga-2">
        <span class="text-caption text-medium-emphasis">{{ label }}</span>
        <div class="d-flex flex-wrap ga-3">
            <AppTextarea
                v-if="multiline"
                :model-value="modelValue.fr"
                label="Français"
                :error="error?.fr"
                class="flex-1-1"
                style="min-width: 240px"
                @update:model-value="$emit('update:modelValue', { ...modelValue, fr: $event })"
            />
            <AppInputText
                v-else
                :model-value="modelValue.fr"
                label="Français"
                :error="error?.fr"
                class="flex-1-1"
                style="min-width: 240px"
                @update:model-value="$emit('update:modelValue', { ...modelValue, fr: $event })"
            />

            <AppTextarea
                v-if="multiline"
                :model-value="modelValue.en"
                label="English"
                :error="error?.en"
                class="flex-1-1"
                style="min-width: 240px"
                @update:model-value="$emit('update:modelValue', { ...modelValue, en: $event })"
            />
            <AppInputText
                v-else
                :model-value="modelValue.en"
                label="English"
                :error="error?.en"
                class="flex-1-1"
                style="min-width: 240px"
                @update:model-value="$emit('update:modelValue', { ...modelValue, en: $event })"
            />
        </div>
    </div>
</template>
