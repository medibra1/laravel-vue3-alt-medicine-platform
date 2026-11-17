<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import { formatMoney } from '@/lib/i18n';
import { fromLocalDateString, toLocalDateString } from '@/utils/date';
import { splitInstallments, type InstallmentDraft, type InstallmentInterval } from '@/utils/installments';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Builds a payment schedule: a generator (count, first date, frequency)
 * fills the rows, each of which stays editable afterwards.
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

const count = ref<number | null>(props.modelValue.length || 3);
const first = ref<Date | null>(fromLocalDateString(props.modelValue[0]?.due_on ?? null) ?? new Date());
const interval = ref<InstallmentInterval>('monthly');

const intervalOptions = computed(() =>
    (['monthly', 'biweekly', 'weekly'] as const).map((value) => ({ value, label: t(`billing.intervals.${value}`) })),
);

const sum = computed(() => Math.round(props.modelValue.reduce((total, row) => total + (Number(row.amount) || 0), 0) * 100) / 100);
const mismatch = computed(() => props.modelValue.length > 0 && Math.abs(sum.value - props.total) > 0.005);
const money = (amount: number) => formatMoney(amount, props.currency, locale.value);

function generate() {
    emit('update:modelValue', splitInstallments(props.total, Math.min(Math.max(Number(count.value) || 2, 2), 36), first.value ?? new Date(), interval.value));
}

function patch(index: number, changes: Partial<InstallmentDraft>) {
    emit('update:modelValue', props.modelValue.map((row, position) => (position === index ? { ...row, ...changes } : row)));
}

defineExpose({ generate });
</script>

<template>
    <div class="d-flex flex-column ga-3">
        <v-row dense align="center">
            <v-col cols="6" sm="3">
                <AppInputNumber v-model="count" :label="t('billing.installmentCount')" :min="2" :max="36" />
            </v-col>
            <v-col cols="6" sm="4">
                <AppDatePicker v-model="first" :label="t('billing.firstDue')" />
            </v-col>
            <v-col cols="8" sm="3">
                <AppSelect v-model="interval" :options="intervalOptions" option-label="label" option-value="value" :label="t('billing.interval')" />
            </v-col>
            <v-col cols="4" sm="2">
                <AppButton severity="secondary" icon="mdi-call-split" :label="t('billing.regenerate')" @click="generate" />
            </v-col>
        </v-row>

        <div v-for="(row, index) in modelValue" :key="index" class="installment-row">
            <span class="installment-row__index app-tabular">{{ index + 1 }}</span>
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
        </div>

        <div v-if="modelValue.length" class="d-flex justify-space-between text-body-2" :class="mismatch ? 'text-error font-weight-bold' : 'text-medium-emphasis'">
            <span>{{ t('billing.scheduleTotal') }}</span>
            <span class="app-tabular">{{ money(sum) }}</span>
        </div>
        <v-alert v-if="mismatch" type="error" variant="tonal" density="compact">{{ t('billing.scheduleMismatch', { amount: money(total) }) }}</v-alert>
        <v-alert v-else-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
    </div>
</template>

<style scoped>
.installment-row {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr) minmax(0, 1fr);
    gap: 8px;
    align-items: center;
}

.installment-row__index {
    font-weight: 700;
    color: rgba(var(--v-theme-on-surface), 0.5);
}
</style>
