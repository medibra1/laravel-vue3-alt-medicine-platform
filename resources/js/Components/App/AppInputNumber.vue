<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue: number | null;
        min?: number;
        max?: number;
        error?: string | null;
    }>(),
    {
        min: undefined,
        max: undefined,
        error: null,
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>();

function onInput(value: string | number) {
    if (value === '' || value === null || value === undefined) {
        emit('update:modelValue', null);
        return;
    }

    emit('update:modelValue', Number(value));
}
</script>

<template>
    <v-text-field
        type="number"
        :model-value="modelValue"
        :min="min"
        :max="max"
        :error-messages="error ?? undefined"
        density="compact"
        variant="outlined"
        hide-details="auto"
        @update:model-value="onInput"
    />
</template>
