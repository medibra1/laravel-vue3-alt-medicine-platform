<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, { type AppDataTableColumn } from '@/Components/App/AppDataTable.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppStatCard from '@/Components/App/AppStatCard.vue';
import BillingNav from '@/Components/Billing/BillingNav.vue';
import InvoiceStatusChip from '@/Components/Billing/InvoiceStatusChip.vue';
import PaymentDialog from '@/Components/Billing/PaymentDialog.vue';
import type { Invoice } from '@/Components/Billing/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate, formatMoney } from '@/lib/i18n';
import { toLocalDateString } from '@/utils/date';
import { handleNavClick } from '@/utils/inertiaNavClick';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Amount {
    currency: string;
    amount: number;
}

const props = defineProps<{
    invoices: {
        data: Invoice[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { search?: string; status?: string };
    summary: { collected_this_month: Amount[]; outstanding: Amount[]; unpaid_count: number };
    centers: { id: number; name: string }[];
    can: { export: boolean };
}>();

const { t, locale } = useI18n();

const search = ref(props.filters.search ?? '');
const status = ref<string | null>(props.filters.status ?? null);
const paymentDialog = ref(false);
const payingInvoice = ref<Invoice | null>(null);

const statusOptions = computed(() =>
    ['unpaid', 'partial', 'paid', 'refund_due', 'credited', 'cancelled'].map((value) => ({ value, label: t(`domain.invoiceStatus.${value}`) })),
);

const columns = computed<AppDataTableColumn[]>(() => [
    { field: 'number', header: t('billing.number') },
    { field: 'issued_on', header: t('billing.issuedOn') },
    { field: 'patient', header: t('billing.patient') },
    { field: 'total', header: t('billing.amount') },
    { field: 'balance', header: t('billing.balance') },
    { field: 'status', header: t('common.status') },
    { field: 'actions', header: '' },
]);

// Amounts are never summed across currencies: one figure per currency.
function sum(amounts: Amount[]): string {
    return amounts.length ? amounts.map((row) => formatMoney(row.amount, row.currency, locale.value)).join(' · ') : '—';
}

function visit(page = 1) {
    router.get(
        route('admin.billing.index'),
        { ...(search.value ? { search: search.value } : {}), ...(status.value ? { status: status.value } : {}), ...(page > 1 ? { page } : {}) },
        { preserveState: true, preserveScroll: true },
    );
}

function pay(invoice: Invoice) {
    payingInvoice.value = invoice;
    paymentDialog.value = true;
}

// Accounting export: a plain file download, so a link rather than a visit.
const exportDialog = ref(false);
const today = new Date();
const exportFrom = ref<Date | null>(new Date(today.getFullYear(), today.getMonth(), 1));
const exportTo = ref<Date | null>(today);
const exportCenter = ref<number | null>(null);

const exportUrl = computed(() =>
    exportFrom.value && exportTo.value
        ? route('admin.billing.export', {
              from: toLocalDateString(exportFrom.value),
              to: toLocalDateString(exportTo.value),
              ...(exportCenter.value ? { center_id: exportCenter.value } : {}),
          })
        : null,
);

function patientUrl(invoice: Invoice): string {
    return route('admin.patients.edit', { patient: invoice.patient_id, tab: 'billing' });
}
</script>

<template>
    <Head :title="t('billing.title')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="t('billing.title')" :subtitle="t('billing.subtitle')">
            <template v-if="can.export" #actions>
                <AppButton severity="secondary" icon="mdi-file-export-outline" :label="t('billing.export')" @click="exportDialog = true" />
            </template>
        </AppPageHeader>

        <BillingNav current="invoices" />

        <v-row dense class="mb-4">
            <v-col cols="12" md="4">
                <AppStatCard :label="t('billing.collectedMonth')" :value="sum(summary.collected_this_month)" icon="mdi-cash-check" color="success" />
            </v-col>
            <v-col cols="12" md="4">
                <AppStatCard :label="t('billing.outstanding')" :value="sum(summary.outstanding)" icon="mdi-cash-clock" color="warning" />
            </v-col>
            <v-col cols="12" md="4">
                <AppStatCard :label="t('billing.unpaidCount')" :value="summary.unpaid_count" icon="mdi-receipt-text-clock-outline" color="info" />
            </v-col>
        </v-row>

        <AppCard variant="flat" class="mb-4">
            <v-card-text>
                <form class="d-flex flex-wrap align-center ga-3" @submit.prevent="visit()">
                    <div style="flex: 1 1 260px">
                        <AppInputText v-model="search" :label="t('billing.searchPlaceholder')" />
                    </div>
                    <div style="flex: 0 1 220px">
                        <AppSelect
                            v-model="status"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            :label="t('common.status')"
                            show-clear
                            @update:model-value="visit()"
                        />
                    </div>
                    <AppButton type="submit" icon="mdi-magnify" :label="t('common.search')" />
                </form>
            </v-card-text>
        </AppCard>

        <AppCard variant="flat">
            <AppDataTable
                :value="invoices.data"
                :columns="columns"
                :rows="invoices.per_page"
                :total-records="invoices.total"
                :page="invoices.current_page"
                :empty-text="t('billing.empty')"
                @page="visit"
            >
                <template #column-number="{ item }">
                    <span class="font-weight-bold">{{ item.number }}</span>
                </template>
                <template #column-issued_on="{ item }">{{ formatDate(item.issued_on, locale) }}</template>
                <template #column-patient="{ item }">
                    <a :href="patientUrl(item)" class="font-weight-medium text-primary" @click="handleNavClick($event, patientUrl(item))">
                        {{ item.patient?.name }}
                    </a>
                    <div v-if="$page.props.auth.is_global_admin" class="text-caption text-medium-emphasis">{{ item.center?.name }}</div>
                </template>
                <template #column-total="{ item }">
                    <span class="app-tabular">{{ formatMoney(item.total, item.currency, locale) }}</span>
                </template>
                <template #column-balance="{ item }">
                    <span class="app-tabular" :class="item.balance > 0 ? 'font-weight-bold' : 'text-medium-emphasis'">
                        {{ formatMoney(item.balance, item.currency, locale) }}
                    </span>
                </template>
                <template #column-status="{ item }"><InvoiceStatusChip :status="item.status" /></template>
                <template #column-actions="{ item }">
                    <div class="d-flex justify-end ga-1">
                        <v-btn
                            v-if="item.balance > 0"
                            icon="mdi-cash-check"
                            variant="text"
                            size="small"
                            color="primary"
                            :aria-label="t('billing.recordPayment')"
                            @click="pay(item)"
                        />
                        <v-btn
                            icon="mdi-file-pdf-box"
                            variant="text"
                            size="small"
                            :href="route('admin.invoices.pdf', item.id)"
                            target="_blank"
                            :aria-label="t('billing.pdf')"
                        />
                    </div>
                </template>
            </AppDataTable>
        </AppCard>

        <AppDialog v-model:visible="exportDialog" :header="t('billing.exportTitle')" max-width="480px">
            <div class="d-flex flex-column ga-4">
                <p class="text-body-2 text-medium-emphasis">{{ t('billing.exportHint') }}</p>
                <AppDatePicker v-model="exportFrom" :label="t('billing.from')" />
                <AppDatePicker v-model="exportTo" :label="t('billing.to')" />
                <AppSelect
                    v-if="centers.length"
                    v-model="exportCenter"
                    :options="centers"
                    option-label="name"
                    option-value="id"
                    :label="t('billing.center')"
                    :placeholder="t('billing.allCenters')"
                    show-clear
                />
                <div class="d-flex justify-end ga-2">
                    <AppButton severity="secondary" :label="t('common.cancel')" @click="exportDialog = false" />
                    <AppButton
                        as="a"
                        icon="mdi-download"
                        :label="t('billing.download')"
                        :href="exportUrl ?? undefined"
                        :disabled="!exportUrl"
                        @click="exportDialog = false"
                    />
                </div>
            </div>
        </AppDialog>

        <PaymentDialog v-model:visible="paymentDialog" :invoice="payingInvoice" @saved="router.reload()" />
    </AuthenticatedLayout>
</template>
