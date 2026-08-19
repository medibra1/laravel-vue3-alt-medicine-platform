<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue: number | null;
        min?: number;
        max?: number;
        label?: string;
        error?: string | null;
    }>(),
    {
        min: undefined,
        max: undefined,
        label: undefined,
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
        :label="label"
        :error-messages="error ?? undefined"
        variant="outlined"
        hide-details="auto"
        @update:model-value="onInput"
    />
</template>
