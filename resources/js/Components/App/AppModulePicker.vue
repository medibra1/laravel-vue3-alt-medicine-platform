<script setup lang="ts">
import type { AppModule } from '@/types';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        modelValue: AppModule[];
        /** Modules that can be picked at all (a user's choice is capped by the center's). */
        available: AppModule[];
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{ 'update:modelValue': [value: AppModule[]] }>();

const { t } = useI18n();

const ICONS: Record<AppModule, string> = {
    patients: 'mdi-account-heart-outline',
    treatments: 'mdi-medical-bag',
    scheduling: 'mdi-calendar-month-outline',
    practitioners: 'mdi-account-tie-outline',
    billing: 'mdi-receipt-text-outline',
};

// Mirrors App\Domains\Core\Support\Modules::dependencies().
const REQUIRES: Partial<Record<AppModule, AppModule[]>> = {
    treatments: ['patients'],
    billing: ['patients'],
};

function toggle(module: AppModule) {
    if (props.disabled) {
        return;
    }

    let next = props.modelValue.includes(module)
        ? props.modelValue.filter((value) => value !== module)
        : [...props.modelValue, module];

    const turnedOff = props.modelValue.includes(module);

    for (const [dependent, required] of Object.entries(REQUIRES) as [AppModule, AppModule[]][]) {
        if (turnedOff && required.includes(module)) {
            // Switching a module off drops whatever depended on it.
            next = next.filter((value) => value !== dependent);
        } else if (next.includes(dependent)) {
            // Switching a module on pulls what it needs.
            next = [...new Set([...next, ...required])];
        }
    }

    emit('update:modelValue', props.available.filter((value) => next.includes(value)));
}
</script>

<template>
    <div class="module-grid">
        <button
            v-for="module in available"
            :key="module"
            type="button"
            class="module-card"
            :class="{ 'module-card--on': modelValue.includes(module), 'module-card--disabled': disabled }"
            :aria-pressed="modelValue.includes(module)"
            :disabled="disabled"
            @click="toggle(module)"
        >
            <v-avatar :color="modelValue.includes(module) ? 'primary' : 'secondary'" variant="tonal" rounded="lg" size="40">
                <v-icon :icon="ICONS[module]" size="20" />
            </v-avatar>
            <span class="module-card__text">
                <span class="font-weight-bold">{{ t(`modules.items.${module}.name`) }}</span>
                <span class="text-caption text-medium-emphasis">{{ t(`modules.items.${module}.description`) }}</span>
                <span v-if="REQUIRES[module]" class="text-caption text-medium-emphasis">
                    {{ t('modules.requires', { modules: REQUIRES[module]!.map((value) => t(`modules.items.${value}.name`)).join(', ') }) }}
                </span>
            </span>
            <v-icon :icon="modelValue.includes(module) ? 'mdi-toggle-switch' : 'mdi-toggle-switch-off-outline'" :color="modelValue.includes(module) ? 'primary' : undefined" size="30" />
        </button>
    </div>
</template>

<style scoped>
.module-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
}

.module-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 1.5));
    background: rgb(var(--v-theme-surface));
    color: rgb(var(--v-theme-on-surface));
    text-align: start;
    cursor: pointer;
    transition: border-color 0.15s, background-color 0.15s;
}

.module-card:hover:not(:disabled),
.module-card:focus-visible {
    border-color: rgb(var(--v-theme-primary));
    outline: none;
}

.module-card--on {
    border-color: rgba(var(--v-theme-primary), 0.55);
    background: rgba(var(--v-theme-primary), 0.05);
}

.module-card--disabled {
    cursor: default;
    opacity: 0.7;
}

.module-card__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    line-height: 1.35;
}
</style>
