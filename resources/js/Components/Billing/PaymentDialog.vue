<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import { http, HttpError } from '@/lib/http';
import { formatMoney } from '@/lib/i18n';
import { toLocalDateString } from '@/utils/date';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { PAYMENT_METHODS, type Invoice } from './types';

const props = defineProps<{
    visible: boolean;
    invoice: Invoice | null;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    saved: [invoice: Invoice];
}>();

const { t, locale } = useI18n();

const form = reactive({ amount: null as number | null, method: 'cash', paid_on: new Date() as Date | null, reference: '' });
const errors = ref<Record<string, string[]>>({});
const saving = ref(false);

const methodOptions = computed(() => PAYMENT_METHODS.map((value) => ({ value, label: t(`domain.paymentMethod.${value}`) })));

watch(() => props.visible, (open) => {
    if (open && props.invoice) {
        form.amount = props.invoice.balance;
        form.method = 'cash';
        form.paid_on = new Date();
        form.reference = '';
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
        const invoice = await http.post<Invoice>(route('admin.invoices.payments.store', props.invoice.id), {
            amount: form.amount,
            method: form.method,
            paid_on: form.paid_on ? toLocalDateString(form.paid_on) : null,
            reference: form.reference || null,
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
    <AppDialog :visible="visible" :header="invoice ? t('billing.paymentTitle', { number: invoice.number }) : ''" max-width="460px" @update:visible="emit('update:visible', $event)">
        <form v-if="invoice" class="d-flex flex-column ga-4" @submit.prevent="submit">
            <div class="payment-due">
                <span class="text-body-2 text-medium-emphasis">{{ t('billing.totalDue') }}</span>
                <span class="text-h5 font-weight-bold app-tabular">{{ formatMoney(invoice.balance, invoice.currency, locale) }}</span>
            </div>

            <AppInputNumber v-model="form.amount" :label="`${t('billing.amount')} (${invoice.currency})`" :min="0" :error="errors.amount?.[0]" />
            <AppSelect v-model="form.method" :options="methodOptions" option-label="label" option-value="value" :label="t('billing.method')" :error="errors.method?.[0]" />
            <AppDatePicker v-model="form.paid_on" :label="t('billing.paidOn')" :error="errors.paid_on?.[0]" />
            <AppInputText v-model="form.reference" :label="t('billing.reference')" :placeholder="t('billing.referenceHint')" :error="errors.reference?.[0]" />

            <div class="d-flex justify-end ga-2 mt-2">
                <AppButton severity="secondary" :label="t('common.cancel')" @click="emit('update:visible', false)" />
                <AppButton type="submit" icon="mdi-cash-check" :label="t('billing.recordPayment')" :loading="saving" />
            </div>
        </form>
    </AppDialog>
</template>

<style scoped>
.payment-due {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 14px;
    background: rgba(var(--v-theme-primary), 0.08);
}
</style>
