<script setup lang="ts">
export interface AppTabItem {
    title: string;
    value: string;
}

defineProps<{
    tabs: AppTabItem[];
    modelValue: string;
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <v-tabs
        :model-value="modelValue"
        class="mb-4"
        @update:model-value="$emit('update:modelValue', $event as string)"
    >
        <v-tab v-for="tab in tabs" :key="tab.value" :value="tab.value">{{ tab.title }}</v-tab>
    </v-tabs>

    <v-window :model-value="modelValue">
        <v-window-item v-for="tab in tabs" :key="tab.value" :value="tab.value">
            <slot :name="tab.value" />
        </v-window-item>
    </v-window>
</template>
