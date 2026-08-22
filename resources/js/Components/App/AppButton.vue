<script setup lang="ts">
type Severity = 'primary' | 'secondary' | 'danger';

const props = withDefaults(
    defineProps<{
        label?: string;
        type?: 'button' | 'submit' | 'reset';
        severity?: Severity;
        size?: 'small' | 'default' | 'large';
        loading?: boolean;
        disabled?: boolean;
        as?: string;
        /** MDI icon shown before the label (e.g. 'mdi-plus'). */
        icon?: string;
    }>(),
    {
        label: undefined,
        type: 'button',
        severity: 'primary',
        size: 'default',
        loading: false,
        disabled: false,
        as: 'button',
        icon: undefined,
    },
);

const severityToColor: Record<Severity, string> = {
    primary: 'primary',
    secondary: 'secondary',
    danger: 'error',
};

const severityToVariant: Record<Severity, 'flat' | 'tonal'> = {
    primary: 'flat',
    secondary: 'tonal',
    danger: 'flat',
};
</script>

<template>
    <v-btn
        :tag="as"
        :type="as === 'button' ? type : undefined"
        :color="severityToColor[severity]"
        :variant="severityToVariant[severity]"
        :size="size"
        :loading="loading"
        :disabled="disabled"
        :prepend-icon="icon"
    >
        <slot>{{ label }}</slot>
    </v-btn>
</template>
