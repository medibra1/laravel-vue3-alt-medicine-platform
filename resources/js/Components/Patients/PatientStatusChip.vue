<script setup lang="ts">
interface DerivedStatus {
    key: string;
    label: string;
    color: string;
}

defineProps<{
    status: DerivedStatus;
    clickable: boolean;
}>();

const emit = defineEmits<{ click: [] }>();
</script>

<template>
    <v-chip size="small" variant="tonal" :color="status.color" :link="clickable" @click="clickable && emit('click')">
        {{ status.label }}
    </v-chip>
</template>
