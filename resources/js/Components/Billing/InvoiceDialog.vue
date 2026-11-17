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
import type { InstallmentDraft } from '@/utils/installments';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InstallmentsEditor from './InstallmentsEditor.vue';
import { PAYMENT_METHODS, type BillableSession, type CatalogEntry, type DiscountReason, type Invoice, type PatientDiscount, type SuggestedLine } from './types';

const props = defineProps<{
    visible: boolean;
    patientId: number;
    currency: string;
    billableSessions: BillableSession[];
    /** Services the center sells; anything else is a free line. */
    catalog: CatalogEntry[];
    discountReasons: DiscountReason[];
    /** Standing discount of the patient, pre-applied. */
    patientDiscount: PatientDiscount;
    canDiscount: boolean;
    canSchedule: boolean;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    saved: [invoice: Invoice];
}>();

const { t, locale } = useI18n();

const FREE = 'free';

interface DraftLine extends Omit<SuggestedLine, 'quantity' | 'unit_price' | 'description'> {
    key: number;
    /** Catalog key, or FREE for a hand-written line. */
    service: string;
    description: string;
    quantity: number | null;
    unit_price: number | null;
}

let nextKey = 1;
const lines = ref<DraftLine[]>([]);
const form = reactive({
    issued_on: new Date() as Date | null,
    notes: '',
    discountType: 'none' as 'none' | 'percent' | 'amount',
    discountValue: null as number | null,
    discountReason: null as number | null,
    discountNote: '',
    payNow: false,
    payAmount: null as number | null,
    method: 'cash',
    reference: '',
    scheduled: false,
});
const installments = ref<InstallmentDraft[]>([]);
const scheduleEditor = ref<InstanceType<typeof InstallmentsEditor> | null>(null);
const addedSessionIds = ref<Set<number>>(new Set());
const errors = ref<Record<string, string[]>>({});
const saving = ref(false);

const methodOptions = computed(() => PAYMENT_METHODS.map((value) => ({ value, label: t(`domain.paymentMethod.${value}`) })));
const discountTypeOptions = computed(() => [
    { value: 'none', label: t('billing.discountNone') },
    { value: 'percent', label: t('billing.discountPercent') },
    { value: 'amount', label: t('billing.discountAmount') },
]);
const serviceOptions = computed(() => [
    { value: FREE, label: t('billing.freeLine') },
    ...props.catalog.map((entry) => ({
        value: entry.key,
        label: `${entry.group === entry.label ? entry.label : `${entry.group} · ${entry.label}`} — ${money(entry.unit_price)}`,
    })),
]);
const remainingSessions = computed(() => props.billableSessions.filter((session) => !addedSessionIds.value.has(session.session_id)));

const round = (amount: number) => Math.round(amount * 100) / 100;
const subtotal = computed(() => round(lines.value.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_price) || 0), 0)));
const discount = computed(() => {
    const value = Number(form.discountValue) || 0;

    if (form.discountType === 'percent') {
        return round((subtotal.value * Math.min(value, 100)) / 100);
    }

    return form.discountType === 'amount' ? Math.min(value, subtotal.value) : 0;
});
const total = computed(() => round(subtotal.value - discount.value));
const money = (amount: number) => formatMoney(amount, props.currency, locale.value);

function catalogKeyFor(line: SuggestedLine, session?: BillableSession): string {
    const key = line.care_item_id
        ? `item-${line.care_item_id}`
        : line.care_category_id
          ? `category-${line.care_category_id}`
          : session && line.treatment_session_id
            ? session.modality === 'remote' ? 'session_price_remote' : 'session_price_in_person'
            : FREE;

    return props.catalog.some((entry) => entry.key === key) ? key : FREE;
}

/** No service yet: the user picks one from the catalog, or "Free line". */
function blankLine(): DraftLine {
    return { key: nextKey++, service: props.catalog.length ? '' : FREE, label: '', description: '', quantity: 1, unit_price: 0, treatment_session_id: null, care_item_id: null, care_category_id: null };
}

/** Picking a service fills label and price; the price stays editable. */
function onServiceChange(line: DraftLine, key: string) {
    line.service = key;
    const entry = props.catalog.find((candidate) => candidate.key === key);

    line.care_item_id = entry?.care_item_id ?? null;
    line.care_category_id = entry?.care_category_id ?? null;

    if (entry) {
        line.label = entry.label;
        line.unit_price = entry.unit_price;
    } else {
        // Back to a free line: don't keep the previous service's name.
        line.label = '';
    }
}

function addSession(session: BillableSession) {
    // Drop the untouched placeholder line rather than leave an empty row behind.
    lines.value = lines.value.filter((line) => line.label !== '' || line.unit_price !== 0);
    lines.value.push(...session.lines.map((line) => ({ ...line, key: nextKey++, service: catalogKeyFor(line, session), description: line.description ?? '' })));
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
        installments.value = [];
        Object.assign(form, {
            issued_on: new Date(),
            notes: '',
            // The patient's standing discount comes pre-applied.
            discountType: props.patientDiscount.percent ? 'percent' : 'none',
            discountValue: props.patientDiscount.percent,
            discountReason: props.patientDiscount.reason_option_id,
            discountNote: '',
            payNow: false,
            payAmount: null,
            method: 'cash',
            reference: '',
            scheduled: false,
        });
        errors.value = {};

        // The common case: bill what was just done.
        if (props.billableSessions.length) {
            props.billableSessions.forEach(addSession);
        } else {
            lines.value = [blankLine()];
        }
    }
}, { immediate: true });

// The amount collected follows the net total until the user types a deposit.
watch(total, (next, previous) => {
    if (form.payAmount === null || form.payAmount === previous || form.payAmount > next) {
        form.payAmount = next;
    }

});

watch(() => form.payNow, (on) => on && (form.payAmount = total.value));
watch(() => form.scheduled, (on) => {
    if (on) {
        nextTick(() => scheduleEditor.value?.reset());
    } else {
        installments.value = [];
    }
});

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
            lines: lines.value.map(({ label, description, quantity, unit_price, treatment_session_id, care_item_id, care_category_id }) => ({
                label, description: description || null, quantity, unit_price, treatment_session_id, care_item_id, care_category_id,
            })),
            ...(props.canDiscount && form.discountType !== 'none' && discount.value > 0
                ? {
                      discount: {
                          [form.discountType]: form.discountValue,
                          reason_option_id: form.discountReason,
                          note: form.discountNote || null,
                      },
                  }
                : {}),
            ...(form.payNow && total.value > 0 && (form.payAmount ?? 0) > 0
                ? { payment: { amount: form.payAmount, method: form.method, reference: form.reference || null } }
                : {}),
            ...(form.scheduled && installments.value.length ? { installments: installments.value } : {}),
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
                    <div class="invoice-line__label d-flex flex-column ga-2">
                        <AppSelect
                            :model-value="line.service"
                            :options="serviceOptions"
                            option-label="label"
                            option-value="value"
                            :label="t('billing.service')"
                            :placeholder="t('billing.chooseService')"
                            :error="line.service === '' && lineError(index, 'label') ? t('billing.chooseServiceFirst') : undefined"
                            searchable
                            @update:model-value="onServiceChange(line, String($event ?? FREE))"
                        />
                        <AppInputText
                            v-if="line.service === FREE"
                            v-model="line.label"
                            :label="t('billing.designation')"
                            :error="lineError(index, 'label')"
                        />
                        <AppInputText v-model="line.description" :label="t('billing.lineDescription')" :error="lineError(index, 'description')" />
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

            <div v-if="canDiscount">
                <div class="app-eyebrow mb-2">{{ t('billing.discount') }}</div>
                <v-row dense>
                    <v-col cols="12" sm="4">
                        <AppSelect v-model="form.discountType" :options="discountTypeOptions" option-label="label" option-value="value" :label="t('billing.discountType')" />
                    </v-col>
                    <template v-if="form.discountType !== 'none'">
                        <v-col cols="12" sm="3">
                            <AppInputNumber
                                v-model="form.discountValue"
                                :label="form.discountType === 'percent' ? '%' : currency"
                                :min="0"
                                :max="form.discountType === 'percent' ? 100 : undefined"
                                :error="errors['discount.percent']?.[0] ?? errors['discount.amount']?.[0]"
                            />
                        </v-col>
                        <v-col cols="12" sm="5">
                            <AppSelect
                                v-model="form.discountReason"
                                :options="discountReasons"
                                option-label="label"
                                option-value="id"
                                :label="t('billing.discountReason')"
                                :error="errors['discount.reason_option_id']?.[0]"
                            />
                        </v-col>
                        <v-col cols="12">
                            <AppInputText v-model="form.discountNote" :label="t('billing.discountNote')" />
                        </v-col>
                    </template>
                </v-row>
            </div>
            <v-alert v-else-if="patientDiscount.percent" type="info" variant="tonal" density="compact">
                {{ t('billing.patientDiscountApplied', { percent: patientDiscount.percent }) }}
            </v-alert>

            <div class="invoice-total">
                <template v-if="discount > 0">
                    <div class="invoice-total__row text-medium-emphasis">
                        <span>{{ t('billing.subtotal') }}</span><span class="app-tabular">{{ money(subtotal) }}</span>
                    </div>
                    <div class="invoice-total__row text-medium-emphasis">
                        <span>{{ t('billing.discount') }}</span><span class="app-tabular">−{{ money(discount) }}</span>
                    </div>
                </template>
                <div class="invoice-total__row">
                    <span class="text-body-1 font-weight-medium">{{ t(discount > 0 ? 'billing.netToPay' : 'common.total') }}</span>
                    <span class="text-h5 font-weight-bold app-tabular">{{ money(total) }}</span>
                </div>
            </div>

            <div v-if="total > 0">
                <AppCheckbox v-model="form.payNow" :label="t('billing.payNow')" />
                <v-expand-transition>
                    <v-row v-if="form.payNow" dense class="mt-2">
                        <v-col cols="12">
                            <AppInputNumber
                                v-model="form.payAmount"
                                :label="`${t('billing.payAmount')} (${currency})`"
                                :min="0"
                                :max="total"
                                :error="errors['payment.amount']?.[0]"
                            />
                            <p class="text-caption text-medium-emphasis mt-1">{{ t('billing.depositHint') }}</p>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <AppSelect v-model="form.method" :options="methodOptions" option-label="label" option-value="value" :label="t('billing.method')" />
                        </v-col>
                        <v-col cols="12" sm="6">
                            <AppInputText v-model="form.reference" :label="t('billing.reference')" :placeholder="t('billing.referenceHint')" />
                        </v-col>
                    </v-row>
                </v-expand-transition>
            </div>

            <div v-if="canSchedule && total > 0">
                <AppCheckbox v-model="form.scheduled" :label="t('billing.payInInstallments')" />
                <v-expand-transition>
                    <div v-if="form.scheduled" class="mt-2">
                        <InstallmentsEditor ref="scheduleEditor" v-model="installments" :total="total" :currency="currency" :error="errors.installments?.[0]" />
                    </div>
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
    align-self: start;
    padding-top: 16px;
    text-align: end;
    font-weight: 700;
}

.invoice-total__row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}

.invoice-total {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px 18px;
    border-radius: 14px;
    background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
