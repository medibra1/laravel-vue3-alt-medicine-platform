<script setup lang="ts">
import { computed } from 'vue';
import { VAutocomplete, VSelect } from 'vuetify/components';

const props = withDefaults(
    defineProps<{
        modelValue: unknown;
        options: object[];
        optionLabel: string;
        optionValue: string;
        placeholder?: string;
        label?: string;
        showClear?: boolean;
        multiple?: boolean;
        disabled?: boolean;
        /** Type to filter: for long lists (catalogs...). */
        searchable?: boolean;
        error?: string | null;
    }>(),
    {
        placeholder: undefined,
        label: undefined,
        showClear: false,
        multiple: false,
        disabled: false,
        searchable: false,
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
    <component
        :is="searchable ? VAutocomplete : VSelect"
        :model-value="modelValue"
        :items="items"
        item-title="title"
        item-value="value"
        :placeholder="placeholder"
        :label="label"
        :clearable="showClear"
        :multiple="multiple"
        :disabled="disabled"
        :chips="multiple"
        :error-messages="error ?? undefined"
        variant="outlined"
        hide-details="auto"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>
