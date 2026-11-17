<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import { http, HttpError } from '@/lib/http';
import { formatMoney } from '@/lib/i18n';
import { toLocalDateString } from '@/utils/date';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Invoice } from './types';

const props = defineProps<{
    visible: boolean;
    invoice: Invoice | null;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    saved: [invoice: Invoice];
}>();

const { t, locale } = useI18n();

const form = reactive({ amount: null as number | null, issued_on: new Date() as Date | null, reason: '' });
const errors = ref<Record<string, string[]>>({});
const saving = ref(false);

// What can still be credited: the invoice minus earlier credit notes.
const creditable = computed(() => (props.invoice ? props.invoice.total - props.invoice.credited_total : 0));

watch(() => props.visible, (open) => {
    if (open && props.invoice) {
        form.amount = creditable.value;
        form.issued_on = new Date();
        form.reason = '';
        errors.value = {};
    }
}, { immediate: true });

async function submit() {
    if (!props.invoice) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const invoice = await http.post<Invoice>(route('admin.invoices.credit-notes.store', props.invoice.id), {
            amount: form.amount,
            issued_on: form.issued_on ? toLocalDateString(form.issued_on) : null,
            reason: form.reason,
        });
        emit('saved', invoice);
        emit('update:visible', false);
    } catch (error) {
        errors.value = error instanceof HttpError ? error.errors : { amount: [t('common.error')] };
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <AppDialog :visible="visible" :header="invoice ? t('billing.creditNoteTitle', { number: invoice.number }) : ''" max-width="480px" @update:visible="emit('update:visible', $event)">
        <form v-if="invoice" class="d-flex flex-column ga-4" @submit.prevent="submit">
            <p class="text-body-2 text-medium-emphasis">{{ t('billing.creditNoteHint') }}</p>

            <div class="credit-left">
                <span class="text-body-2 text-medium-emphasis">{{ t('billing.creditable') }}</span>
                <span class="text-h6 font-weight-bold app-tabular">{{ formatMoney(creditable, invoice.currency, locale) }}</span>
            </div>

            <AppInputNumber v-model="form.amount" :label="`${t('billing.creditAmount')} (${invoice.currency})`" :min="0" :error="errors.amount?.[0]" />
            <AppDatePicker v-model="form.issued_on" :label="t('billing.creditDate')" :error="errors.issued_on?.[0]" />
            <AppTextarea v-model="form.reason" :label="t('billing.creditReason')" :rows="2" :error="errors.reason?.[0]" />

            <div class="d-flex justify-end ga-2 mt-2">
                <AppButton severity="secondary" :label="t('common.cancel')" @click="emit('update:visible', false)" />
                <AppButton type="submit" icon="mdi-file-undo-outline" :label="t('billing.issueCreditNote')" :loading="saving" />
            </div>
        </form>
    </AppDialog>
</template>

<style scoped>
.credit-left {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 14px;
    background: rgba(var(--v-theme-secondary), 0.1);
}
</style>
