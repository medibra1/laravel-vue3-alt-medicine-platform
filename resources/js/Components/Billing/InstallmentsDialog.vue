<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import { http, HttpError } from '@/lib/http';
import { splitInstallments, type InstallmentDraft } from '@/utils/installments';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InstallmentsEditor from './InstallmentsEditor.vue';
import type { Invoice } from './types';

/** Sets, changes or removes the payment schedule of an existing invoice. */
const props = defineProps<{
    visible: boolean;
    invoice: Invoice | null;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    saved: [invoice: Invoice];
}>();

const { t } = useI18n();

const rows = ref<InstallmentDraft[]>([]);
const error = ref<string | null>(null);
const saving = ref(false);

// The schedule covers the whole net amount, paid part included.
const net = computed(() => (props.invoice ? props.invoice.total - props.invoice.credited_total : 0));

watch(() => props.visible, (open) => {
    if (open && props.invoice) {
        rows.value = (props.invoice.installments ?? []).map(({ due_on, amount }) => ({ due_on, amount }));
        error.value = null;

        if (!rows.value.length) {
            rows.value = splitInstallments(net.value, 2, new Date(), 'monthly');
        }
    }
}, { immediate: true });

async function save(installments: InstallmentDraft[]) {
    if (!props.invoice) {
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        const invoice = await http.put<Invoice>(route('admin.invoices.installments.update', props.invoice.id), { installments });
        emit('saved', invoice);
        emit('update:visible', false);
    } catch (exception) {
        error.value = exception instanceof HttpError ? (Object.values(exception.errors)[0]?.[0] ?? exception.message) : t('common.error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <AppDialog :visible="visible" :header="invoice ? t('billing.scheduleTitle', { number: invoice.number }) : ''" max-width="620px" @update:visible="emit('update:visible', $event)">
        <form v-if="invoice" class="d-flex flex-column ga-4" @submit.prevent="save(rows)">
            <InstallmentsEditor v-model="rows" :total="net" :currency="invoice.currency" :error="error" />

            <div class="d-flex flex-wrap justify-end ga-2 mt-2">
                <AppButton
                    v-if="invoice.installments?.length"
                    severity="danger"
                    icon="mdi-calendar-remove-outline"
                    :label="t('billing.removeSchedule')"
                    :loading="saving"
                    @click="save([])"
                />
                <v-spacer />
                <AppButton severity="secondary" :label="t('common.cancel')" @click="emit('update:visible', false)" />
                <AppButton type="submit" icon="mdi-calendar-check-outline" :label="t('common.save')" :loading="saving" :disabled="rows.length < 2" />
            </div>
        </form>
    </AppDialog>
</template>
