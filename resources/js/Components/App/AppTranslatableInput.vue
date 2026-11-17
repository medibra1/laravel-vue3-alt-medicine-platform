<script setup lang="ts">
import AppInputText from '@/Components/App/AppInputText.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';

/**
 * Edits a spatie/laravel-translatable JSON field as one input per
 * language. French and English are the required pair; Arabic is
 * optional and simply left out of the payload when empty, so the
 * backend falls back to French for it.
 */
export interface TranslatableValue {
    fr: string;
    en: string;
    ar?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: TranslatableValue;
        label: string;
        multiline?: boolean;
        error?: Record<string, string | undefined> | null;
    }>(),
    {
        multiline: false,
        error: null,
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: TranslatableValue] }>();

const LOCALES = [
    { code: 'fr', label: 'Français', dir: 'ltr' },
    { code: 'en', label: 'English', dir: 'ltr' },
    { code: 'ar', label: 'العربية', dir: 'rtl' },
] as const;

function update(code: 'fr' | 'en' | 'ar', value: string) {
    const next: TranslatableValue = { ...props.modelValue, [code]: value };

    if (code === 'ar' && value === '') {
        delete next.ar;
    }

    emit('update:modelValue', next);
}
</script>

<template>
    <div class="d-flex flex-column ga-2">
        <span class="text-caption text-medium-emphasis">{{ label }}</span>
        <div class="d-flex flex-wrap ga-3">
            <component
                :is="multiline ? AppTextarea : AppInputText"
                v-for="locale in LOCALES"
                :key="locale.code"
                :model-value="modelValue[locale.code] ?? ''"
                :label="locale.label"
                :error="error?.[locale.code]"
                :dir="locale.dir"
                class="flex-1-1"
                style="min-width: 200px"
                @update:model-value="update(locale.code, $event)"
            />
        </div>
    </div>
</template>
