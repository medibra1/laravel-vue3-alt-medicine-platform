<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, { type AppDataTableColumn } from '@/Components/App/AppDataTable.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import BillingNav from '@/Components/Billing/BillingNav.vue';
import { PAYMENT_METHODS } from '@/Components/Billing/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate, formatMoney } from '@/lib/i18n';
import { fromLocalDateString, toLocalDateString } from '@/utils/date';
import { handleNavClick } from '@/utils/inertiaNavClick';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface PaymentRow {
    id: number;
    amount: number;
    method: string;
    paid_on: string;
    reference: string | null;
    currency: string;
    invoice: { id: number; number: string };
    patient: { id: number; name: string };
    center: string;
    received_by: string | null;
}

const props = defineProps<{
    payments: { data: PaymentRow[]; current_page: number; per_page: number; total: number };
    filters: { method?: string; from?: string; to?: string };
}>();

const { t, locale } = useI18n();

const method = ref<string | null>(props.filters.method ?? null);
const from = ref<Date | null>(fromLocalDateString(props.filters.from ?? null));
const to = ref<Date | null>(fromLocalDateString(props.filters.to ?? null));

const methodOptions = computed(() => PAYMENT_METHODS.map((value) => ({ value, label: t(`domain.paymentMethod.${value}`) })));

const columns = computed<AppDataTableColumn[]>(() => [
    { field: 'paid_on', header: t('billing.issuedOn') },
    { field: 'patient', header: t('billing.patient') },
    { field: 'invoice', header: t('billing.invoice') },
    { field: 'method', header: t('billing.method') },
    { field: 'received_by', header: t('billing.receivedBy') },
    { field: 'amount', header: t('billing.amount') },
]);

function visit(page = 1) {
    router.get(
        route('admin.billing.payments'),
        {
            ...(method.value ? { method: method.value } : {}),
            ...(from.value ? { from: toLocalDateString(from.value) } : {}),
            ...(to.value ? { to: toLocalDateString(to.value) } : {}),
            ...(page > 1 ? { page } : {}),
        },
        { preserveState: true, preserveScroll: true },
    );
}

function patientUrl(row: PaymentRow): string {
    return route('admin.patients.edit', { patient: row.patient.id, tab: 'billing' });
}
</script>

<template>
    <Head :title="t('billing.paymentsTitle')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="t('billing.paymentsTitle')" :subtitle="t('billing.paymentsSubtitle')" />

        <BillingNav current="payments" />

        <AppCard variant="flat" class="mb-4">
            <v-card-text>
                <form class="d-flex flex-wrap align-center ga-3" @submit.prevent="visit()">
                    <div style="flex: 1 1 180px"><AppDatePicker v-model="from" :label="t('billing.from')" /></div>
                    <div style="flex: 1 1 180px"><AppDatePicker v-model="to" :label="t('billing.to')" /></div>
                    <div style="flex: 1 1 200px">
                        <AppSelect v-model="method" :options="methodOptions" option-label="label" option-value="value" :label="t('billing.method')" show-clear />
                    </div>
                    <AppButton type="submit" icon="mdi-filter-variant" :label="t('common.search')" />
                </form>
            </v-card-text>
        </AppCard>

        <AppCard variant="flat">
            <AppDataTable
                :value="payments.data"
                :columns="columns"
                :rows="payments.per_page"
                :total-records="payments.total"
                :page="payments.current_page"
                :empty-text="t('billing.emptyPayments')"
                @page="visit"
            >
                <template #column-paid_on="{ item }">{{ formatDate(item.paid_on, locale) }}</template>
                <template #column-patient="{ item }">
                    <a :href="patientUrl(item)" class="font-weight-medium text-primary" @click="handleNavClick($event, patientUrl(item))">
                        {{ item.patient.name }}
                    </a>
                    <div v-if="$page.props.auth.is_global_admin" class="text-caption text-medium-emphasis">{{ item.center }}</div>
                </template>
                <template #column-invoice="{ item }">
                    <span class="font-weight-bold">{{ item.invoice.number }}</span>
                    <div v-if="item.reference" class="text-caption text-medium-emphasis">{{ item.reference }}</div>
                </template>
                <template #column-method="{ item }">{{ t(`domain.paymentMethod.${item.method}`) }}</template>
                <template #column-received_by="{ item }">{{ item.received_by ?? '—' }}</template>
                <template #column-amount="{ item }">
                    <span class="app-tabular font-weight-bold" :class="item.amount < 0 ? 'text-error' : ''">
                        {{ formatMoney(item.amount, item.currency, locale) }}
                    </span>
                    <v-chip v-if="item.amount < 0" size="x-small" color="error" variant="tonal" class="ms-2">{{ t('billing.refundLabel') }}</v-chip>
                </template>
            </AppDataTable>
        </AppCard>
    </AuthenticatedLayout>
</template>
