<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import { http, HttpError } from '@/lib/http';
import { formatDate, formatMoney } from '@/lib/i18n';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CreditNoteDialog from './CreditNoteDialog.vue';
import InvoiceDialog from './InvoiceDialog.vue';
import InvoiceStatusChip from './InvoiceStatusChip.vue';
import PaymentDialog from './PaymentDialog.vue';
import type { BillableSession, Invoice } from './types';

const props = defineProps<{ patientId: number }>();

const { t, locale } = useI18n();

interface Payload {
    invoices: Invoice[];
    billable_sessions: BillableSession[];
    currency: string;
    can: { create: boolean; pay: boolean; cancel: boolean; credit: boolean; refund: boolean };
}

const data = ref<Payload | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const invoiceDialog = ref(false);
const paymentDialog = ref(false);
const payingInvoice = ref<Invoice | null>(null);
const refunding = ref(false);
const creditDialog = ref(false);
const creditingInvoice = ref<Invoice | null>(null);
const expanded = ref<number[]>([]);

const money = (amount: number, currency?: string) => formatMoney(amount, currency ?? data.value?.currency ?? 'EUR', locale.value);

const activeInvoices = computed(() => (data.value?.invoices ?? []).filter((invoice) => invoice.status !== 'cancelled'));
const totals = computed(() => ({
    billed: activeInvoices.value.reduce((sum, invoice) => sum + invoice.total - invoice.credited_total, 0),
    paid: activeInvoices.value.reduce((sum, invoice) => sum + invoice.paid_total, 0),
    due: activeInvoices.value.reduce((sum, invoice) => sum + invoice.balance, 0),
}));

async function load() {
    loading.value = true;
    error.value = null;

    try {
        data.value = await http.get<Payload>(route('admin.patients.invoices.index', props.patientId));
    } catch (exception) {
        error.value = exception instanceof HttpError ? exception.message : t('common.error');
    } finally {
        loading.value = false;
    }
}

function credit(invoice: Invoice) {
    creditingInvoice.value = invoice;
    creditDialog.value = true;
}

function pay(invoice: Invoice, refund = false) {
    refunding.value = refund;
    payingInvoice.value = invoice;
    paymentDialog.value = true;
}

async function cancel(invoice: Invoice) {
    if (!window.confirm(t('billing.confirmCancel', { number: invoice.number }))) {
        return;
    }

    try {
        await http.post(route('admin.invoices.cancel', invoice.id));
        await load();
    } catch (exception) {
        error.value = exception instanceof HttpError ? exception.message : t('common.error');
    }
}

async function deletePayment(paymentId: number) {
    if (!window.confirm(t('billing.confirmDeletePayment'))) {
        return;
    }

    await http.delete(route('admin.payments.destroy', paymentId));
    await load();
}

onMounted(load);
</script>

<template>
    <div>
        <div v-if="loading && !data" class="d-flex justify-center py-12">
            <v-progress-circular indeterminate color="primary" />
        </div>

        <v-alert v-if="error" type="error" variant="tonal" closable class="mb-4" @click:close="error = null">{{ error }}</v-alert>

        <template v-if="data">
            <v-row dense class="mb-4">
                <v-col cols="12" sm="4">
                    <div class="billing-figure">
                        <span class="text-caption text-medium-emphasis">{{ t('billing.billed') }}</span>
                        <span class="billing-figure__value app-tabular">{{ money(totals.billed) }}</span>
                    </div>
                </v-col>
                <v-col cols="12" sm="4">
                    <div class="billing-figure">
                        <span class="text-caption text-medium-emphasis">{{ t('billing.totalPaid') }}</span>
                        <span class="billing-figure__value app-tabular text-success">{{ money(totals.paid) }}</span>
                    </div>
                </v-col>
                <v-col cols="12" sm="4">
                    <div class="billing-figure" :class="{ 'billing-figure--due': totals.due > 0 }">
                        <span class="text-caption text-medium-emphasis">{{ t('billing.totalDue') }}</span>
                        <span class="billing-figure__value app-tabular">{{ money(totals.due) }}</span>
                    </div>
                </v-col>
            </v-row>

            <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-4">
                <v-chip v-if="data.billable_sessions.length" color="warning" variant="tonal" prepend-icon="mdi-alert-circle-outline">
                    {{ t('billing.unbilledSessions') }} · {{ data.billable_sessions.length }}
                </v-chip>
                <v-spacer />
                <AppButton v-if="data.can.create" icon="mdi-plus" :label="t('billing.newInvoice')" @click="invoiceDialog = true" />
            </div>

            <div v-if="!data.invoices.length" class="billing-empty">
                <v-icon icon="mdi-receipt-text-outline" size="40" />
                {{ t('billing.emptyPatient') }}
            </div>

            <v-expansion-panels v-model="expanded" multiple variant="accordion" class="billing-panels">
                <v-expansion-panel v-for="invoice in data.invoices" :key="invoice.id" :value="invoice.id" elevation="0">
                    <v-expansion-panel-title>
                        <div class="billing-head">
                            <div>
                                <div class="font-weight-bold">{{ invoice.number }}</div>
                                <div class="text-caption text-medium-emphasis">{{ formatDate(invoice.issued_on, locale) }}</div>
                            </div>
                            <InvoiceStatusChip :status="invoice.status" />
                            <div class="text-end">
                                <div class="font-weight-bold app-tabular">{{ money(invoice.total, invoice.currency) }}</div>
                                <div v-if="invoice.balance > 0" class="text-caption text-medium-emphasis app-tabular">
                                    {{ t('billing.balance') }} : {{ money(invoice.balance, invoice.currency) }}
                                </div>
                            </div>
                        </div>
                    </v-expansion-panel-title>

                    <v-expansion-panel-text>
                        <v-table density="compact" class="mb-4">
                            <thead>
                                <tr>
                                    <th>{{ t('billing.designation') }}</th>
                                    <th class="text-end">{{ t('billing.quantity') }}</th>
                                    <th class="text-end">{{ t('billing.unitPrice') }}</th>
                                    <th class="text-end">{{ t('billing.lineTotal') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in invoice.lines" :key="line.id">
                                    <td>{{ line.label }}</td>
                                    <td class="text-end app-tabular">{{ line.quantity }}</td>
                                    <td class="text-end app-tabular">{{ money(line.unit_price, invoice.currency) }}</td>
                                    <td class="text-end app-tabular font-weight-bold">{{ money(line.total, invoice.currency) }}</td>
                                </tr>
                            </tbody>
                        </v-table>

                        <template v-if="invoice.credit_notes?.length">
                            <div class="app-eyebrow mb-2">{{ t('billing.creditNotes') }}</div>
                            <div v-for="creditNote in invoice.credit_notes" :key="creditNote.id" class="billing-payment">
                                <v-icon icon="mdi-file-undo-outline" color="secondary" size="20" />
                                <span class="app-tabular font-weight-bold">−{{ money(creditNote.amount, invoice.currency) }}</span>
                                <span class="text-body-2 text-medium-emphasis">
                                    {{ creditNote.number }} · {{ formatDate(creditNote.issued_on, locale) }}
                                    <template v-if="creditNote.reason"> · {{ creditNote.reason }}</template>
                                </span>
                            </div>
                            <div class="mb-3" />
                        </template>

                        <template v-if="invoice.payments?.length">
                            <div class="app-eyebrow mb-2">{{ t('billing.payments') }}</div>
                            <div v-for="payment in invoice.payments" :key="payment.id" class="billing-payment">
                                <v-icon :icon="payment.amount < 0 ? 'mdi-cash-refund' : 'mdi-cash-check'" :color="payment.amount < 0 ? 'error' : 'success'" size="20" />
                                <span class="app-tabular font-weight-bold">{{ money(payment.amount, invoice.currency) }}</span>
                                <span class="text-body-2 text-medium-emphasis">
                                    <template v-if="payment.amount < 0">{{ t('billing.refundLabel') }} · </template>{{ formatDate(payment.paid_on, locale) }} · {{ t(`domain.paymentMethod.${payment.method}`) }}
                                    <template v-if="payment.reference"> · {{ payment.reference }}</template>
                                </span>
                                <v-spacer />
                                <v-btn
                                    v-if="data.can.cancel"
                                    icon="mdi-delete-outline"
                                    variant="text"
                                    size="small"
                                    color="error"
                                    :aria-label="t('billing.deletePayment')"
                                    @click="deletePayment(payment.id)"
                                />
                            </div>
                        </template>

                        <p v-if="invoice.notes" class="text-body-2 text-medium-emphasis mt-3">{{ invoice.notes }}</p>

                        <div class="d-flex flex-wrap justify-end ga-2 mt-4">
                            <AppButton
                                v-if="data.can.cancel && invoice.status !== 'cancelled' && !invoice.payments?.length && !invoice.credit_notes?.length"
                                severity="secondary"
                                size="small"
                                icon="mdi-cancel"
                                :label="t('billing.cancelInvoice')"
                                @click="cancel(invoice)"
                            />
                            <AppButton
                                v-if="data.can.credit && invoice.status !== 'cancelled' && invoice.total - invoice.credited_total > 0"
                                severity="secondary"
                                size="small"
                                icon="mdi-file-undo-outline"
                                :label="t('billing.issueCreditNote')"
                                @click="credit(invoice)"
                            />
                            <AppButton
                                v-if="data.can.refund && invoice.balance < 0"
                                severity="danger"
                                size="small"
                                icon="mdi-cash-refund"
                                :label="t('billing.refund')"
                                @click="pay(invoice, true)"
                            />
                            <AppButton
                                as="a"
                                severity="secondary"
                                size="small"
                                icon="mdi-file-pdf-box"
                                :label="t('billing.pdf')"
                                :href="route('admin.invoices.pdf', invoice.id)"
                                target="_blank"
                            />
                            <AppButton
                                v-if="data.can.pay && invoice.balance > 0"
                                size="small"
                                icon="mdi-cash-check"
                                :label="t('billing.recordPayment')"
                                @click="pay(invoice)"
                            />
                        </div>
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>

            <InvoiceDialog
                v-model:visible="invoiceDialog"
                :patient-id="patientId"
                :currency="data.currency"
                :billable-sessions="data.billable_sessions"
                @saved="load"
            />
            <PaymentDialog v-model:visible="paymentDialog" :invoice="payingInvoice" :refund="refunding" @saved="load" />
            <CreditNoteDialog v-model:visible="creditDialog" :invoice="creditingInvoice" @saved="load" />
        </template>
    </div>
</template>

<style scoped>
.billing-figure {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 14px 18px;
    border-radius: 14px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
}

.billing-figure--due {
    border-color: rgba(var(--v-theme-warning), 0.5);
    background: rgba(var(--v-theme-warning), 0.07);
}

.billing-figure__value {
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.billing-panels {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 16px;
    overflow: hidden;
}

.billing-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 16px;
    width: 100%;
    padding-inline-end: 12px;
}

.billing-payment {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 6px 0;
}

.billing-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 40px 12px;
    color: rgba(var(--v-theme-on-surface), 0.5);
}
</style>
