<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: unknown;
        options: object[];
        optionLabel: string;
        optionValue: string;
        placeholder?: string;
        showClear?: boolean;
        error?: string | null;
    }>(),
    {
        placeholder: undefined,
        showClear: false,
        error: null,
    },
);

defineEmits<{ 'update:modelValue': [value: unknown] }>();

const items = computed(() =>
    props.options.map((option) => ({
        title: String((option as Record<string, unknown>)[props.optionLabel]),
        value: (option as Record<string, unknown>)[props.optionValue],
    })),
);
</script>

<template>
    <v-select
        :model-value="modelValue"
        :items="items"
        item-title="title"
        item-value="value"
        :placeholder="placeholder"
        :clearable="showClear"
        :error-messages="error ?? undefined"
        density="compact"
        variant="outlined"
        hide-details="auto"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>
