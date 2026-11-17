<script setup lang="ts">
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import { formatMoney } from '@/lib/i18n';
import { fromLocalDateString, toLocalDateString } from '@/utils/date';
import { splitInstallments, type InstallmentDraft } from '@/utils/installments';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * A payment schedule built row by row: no number of instalments to
 * decide up front. Each added row takes what is still unplanned, one
 * month after the previous one; dates and amounts stay editable.
 */
const props = defineProps<{
    modelValue: InstallmentDraft[];
    /** What the instalments must add up to. */
    total: number;
    currency: string;
    error?: string | null;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: InstallmentDraft[]] }>();

const { t, locale } = useI18n();

const round = (amount: number) => Math.round(amount * 100) / 100;
const sum = computed(() => round(props.modelValue.reduce((total, row) => total + (Number(row.amount) || 0), 0)));
const mismatch = computed(() => props.modelValue.length > 0 && Math.abs(sum.value - props.total) > 0.005);
const money = (amount: number) => formatMoney(amount, props.currency, locale.value);

/** Starting point: two equal instalments, today and in a month. */
function reset() {
    emit('update:modelValue', splitInstallments(props.total, 2, new Date(), 'monthly'));
}

function add() {
    const last = fromLocalDateString(props.modelValue[props.modelValue.length - 1]?.due_on ?? null) ?? new Date();
    const due = new Date(last.getFullYear(), last.getMonth() + (props.modelValue.length ? 1 : 0), last.getDate());

    emit('update:modelValue', [...props.modelValue, { due_on: toLocalDateString(due), amount: Math.max(0, round(props.total - sum.value)) }]);
}

function remove(index: number) {
    emit('update:modelValue', props.modelValue.filter((_, position) => position !== index));
}

function patch(index: number, changes: Partial<InstallmentDraft>) {
    emit('update:modelValue', props.modelValue.map((row, position) => (position === index ? { ...row, ...changes } : row)));
}

defineExpose({ reset });
</script>

<template>
    <div class="d-flex flex-column ga-3">
        <div v-for="(row, index) in modelValue" :key="index" class="installment-row">
            <AppDatePicker
                :model-value="fromLocalDateString(row.due_on)"
                :label="t('billing.dueOn')"
                @update:model-value="patch(index, { due_on: $event ? toLocalDateString($event) : row.due_on })"
            />
            <AppInputNumber
                :model-value="row.amount"
                :label="`${t('billing.amount')} (${currency})`"
                :min="0"
                @update:model-value="patch(index, { amount: Number($event) || 0 })"
            />
            <v-btn icon="mdi-close" variant="text" size="small" color="secondary" :aria-label="t('billing.removeInstallment')" @click="remove(index)" />
        </div>

        <div>
            <v-btn variant="text" color="primary" prepend-icon="mdi-plus" @click="add">{{ t('billing.addInstallment') }}</v-btn>
        </div>

        <div v-if="modelValue.length" class="d-flex justify-space-between text-body-2" :class="mismatch ? 'text-error font-weight-bold' : 'text-medium-emphasis'">
            <span>{{ t('billing.scheduleTotal') }}</span>
            <span class="app-tabular">{{ money(sum) }} / {{ money(total) }}</span>
        </div>
        <v-alert v-if="mismatch" type="error" variant="tonal" density="compact">{{ t('billing.scheduleMismatch', { amount: money(total) }) }}</v-alert>
        <v-alert v-else-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
    </div>
</template>

<style scoped>
.installment-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 40px;
    gap: 8px;
    align-items: center;
}

.installment-row__index {
    font-weight: 700;
    color: rgba(var(--v-theme-on-surface), 0.5);
}
</style>
