<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCheckbox from '@/Components/App/AppCheckbox.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import { http, HttpError } from '@/lib/http';
import { formatDate, formatMoney } from '@/lib/i18n';
import { toLocalDateString } from '@/utils/date';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { PAYMENT_METHODS, type BillableSession, type Invoice, type SuggestedLine } from './types';

const props = defineProps<{
    visible: boolean;
    patientId: number;
    currency: string;
    billableSessions: BillableSession[];
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    saved: [invoice: Invoice];
}>();

const { t, locale } = useI18n();

interface DraftLine extends Omit<SuggestedLine, 'quantity' | 'unit_price'> {
    key: number;
    quantity: number | null;
    unit_price: number | null;
}

let nextKey = 1;
const lines = ref<DraftLine[]>([]);
const form = reactive({ issued_on: new Date() as Date | null, notes: '', payNow: false, method: 'cash', reference: '' });
const addedSessionIds = ref<Set<number>>(new Set());
const errors = ref<Record<string, string[]>>({});
const saving = ref(false);

const methodOptions = computed(() => PAYMENT_METHODS.map((value) => ({ value, label: t(`domain.paymentMethod.${value}`) })));
const remainingSessions = computed(() => props.billableSessions.filter((session) => !addedSessionIds.value.has(session.session_id)));
const total = computed(() => lines.value.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_price) || 0), 0));
const money = (amount: number) => formatMoney(amount, props.currency, locale.value);

function blankLine(): DraftLine {
    return { key: nextKey++, label: '', quantity: 1, unit_price: 0, treatment_session_id: null, care_item_id: null };
}

function addSession(session: BillableSession) {
    // Drop the untouched placeholder line rather than leave an empty row behind.
    lines.value = lines.value.filter((line) => line.label !== '' || line.unit_price !== 0);
    lines.value.push(...session.lines.map((line) => ({ ...line, key: nextKey++ })));
    addedSessionIds.value = new Set([...addedSessionIds.value, session.session_id]);
}

function removeLine(line: DraftLine) {
    lines.value = lines.value.filter((candidate) => candidate.key !== line.key);

    if (line.treatment_session_id !== null) {
        const next = new Set(addedSessionIds.value);
        next.delete(line.treatment_session_id);
        addedSessionIds.value = next;
    }
}

watch(() => props.visible, (open) => {
    if (open) {
        lines.value = [];
        addedSessionIds.value = new Set();
        Object.assign(form, { issued_on: new Date(), notes: '', payNow: false, method: 'cash', reference: '' });
        errors.value = {};

        // The common case: bill what was just done.
        if (props.billableSessions.length) {
            props.billableSessions.forEach(addSession);
        } else {
            lines.value = [blankLine()];
        }
    }
}, { immediate: true });

function lineError(index: number, field: string): string | undefined {
    return errors.value[`lines.${index}.${field}`]?.[0];
}

async function submit() {
    saving.value = true;
    errors.value = {};

    try {
        const invoice = await http.post<Invoice>(route('admin.patients.invoices.store', props.patientId), {
            issued_on: form.issued_on ? toLocalDateString(form.issued_on) : null,
            notes: form.notes || null,
            lines: lines.value.map(({ label, quantity, unit_price, treatment_session_id, care_item_id }) => ({ label, quantity, unit_price, treatment_session_id, care_item_id })),
            ...(form.payNow && total.value > 0 ? { payment: { amount: total.value, method: form.method, reference: form.reference || null } } : {}),
        });
        emit('saved', invoice);
        emit('update:visible', false);
    } catch (error) {
        errors.value = error instanceof HttpError ? error.errors : { lines: [t('common.error')] };
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <AppDialog :visible="visible" :header="t('billing.newInvoice')" max-width="860px" @update:visible="emit('update:visible', $event)">
        <form class="d-flex flex-column ga-5" @submit.prevent="submit">
            <v-row dense>
                <v-col cols="12" sm="5">
                    <AppDatePicker v-model="form.issued_on" :label="t('billing.issuedOn')" :error="errors.issued_on?.[0]" />
                </v-col>
            </v-row>

            <div v-if="billableSessions.length" class="invoice-suggest">
                <div class="text-body-2 font-weight-bold">{{ t('billing.unbilledSessions') }}</div>
                <div class="text-caption text-medium-emphasis mb-2">{{ t('billing.unbilledHint') }}</div>
                <div v-if="remainingSessions.length" class="d-flex flex-wrap ga-2">
                    <v-chip
                        v-for="session in remainingSessions"
                        :key="session.session_id"
                        prepend-icon="mdi-plus"
                        color="primary"
                        variant="outlined"
                        @click="addSession(session)"
                    >
                        {{ t('billing.addSession', { date: formatDate(session.session_date, locale) }) }}
                    </v-chip>
                </div>
                <div v-else class="text-caption text-success d-flex align-center ga-1">
                    <v-icon icon="mdi-check" size="16" /> {{ t('billing.allAdded') }}
                </div>
            </div>

            <div>
                <div class="app-eyebrow mb-2">{{ t('billing.lines') }}</div>
                <v-alert v-if="errors.lines" type="error" variant="tonal" density="compact" class="mb-3">{{ errors.lines[0] }}</v-alert>

                <div v-for="(line, index) in lines" :key="line.key" class="invoice-line">
                    <div class="invoice-line__label">
                        <AppInputText v-model="line.label" :label="t('billing.designation')" :error="lineError(index, 'label')" />
                    </div>
                    <div class="invoice-line__qty">
                        <AppInputNumber v-model="line.quantity" :label="t('billing.quantity')" :min="0" :error="lineError(index, 'quantity')" />
                    </div>
                    <div class="invoice-line__price">
                        <AppInputNumber v-model="line.unit_price" :label="t('billing.unitPrice')" :min="0" :error="lineError(index, 'unit_price')" />
                    </div>
                    <div class="invoice-line__total app-tabular">{{ money((Number(line.quantity) || 0) * (Number(line.unit_price) || 0)) }}</div>
                    <v-btn icon="mdi-close" variant="text" size="small" color="secondary" :aria-label="t('billing.removeLine')" @click="removeLine(line)" />
                </div>

                <v-btn variant="text" color="primary" prepend-icon="mdi-plus" @click="lines.push(blankLine())">{{ t('billing.addLine') }}</v-btn>
            </div>

            <AppTextarea v-model="form.notes" :label="t('common.notes')" :rows="2" />

            <div class="invoice-total">
                <span class="text-body-1 font-weight-medium">{{ t('common.total') }}</span>
                <span class="text-h5 font-weight-bold app-tabular">{{ money(total) }}</span>
            </div>

            <div>
                <AppCheckbox v-model="form.payNow" :label="t('billing.payNow')" />
                <v-expand-transition>
                    <v-row v-if="form.payNow" dense class="mt-2">
                        <v-col cols="12" sm="6">
                            <AppSelect v-model="form.method" :options="methodOptions" option-label="label" option-value="value" :label="t('billing.method')" />
                        </v-col>
                        <v-col cols="12" sm="6">
                            <AppInputText v-model="form.reference" :label="t('billing.reference')" :placeholder="t('billing.referenceHint')" />
                        </v-col>
                    </v-row>
                </v-expand-transition>
            </div>

            <div class="d-flex justify-end ga-2">
                <AppButton severity="secondary" :label="t('common.cancel')" @click="emit('update:visible', false)" />
                <AppButton type="submit" icon="mdi-receipt-text-check-outline" :label="t('billing.createInvoice')" :loading="saving" :disabled="!lines.length" />
            </div>
        </form>
    </AppDialog>
</template>

<style scoped>
.invoice-suggest {
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px dashed rgba(var(--v-theme-primary), 0.45);
    background: rgba(var(--v-theme-primary), 0.04);
}

.invoice-line {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    align-items: start;
    padding-bottom: 12px;
    margin-bottom: 12px;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

@media (min-width: 720px) {
    .invoice-line {
        grid-template-columns: minmax(0, 1fr) 90px 140px 130px 40px;
        border-bottom: 0;
        padding-bottom: 0;
    }
}

.invoice-line__total {
    align-self: center;
    text-align: end;
    font-weight: 700;
}

.invoice-total {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 14px 18px;
    border-radius: 14px;
    background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
