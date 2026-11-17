<script setup lang="ts">
import AppButton from '@/Components/App/AppButton.vue';
import AppCard from '@/Components/App/AppCard.vue';
import AppDataTable, { type AppDataTableColumn } from '@/Components/App/AppDataTable.vue';
import AppDatePicker from '@/Components/App/AppDatePicker.vue';
import AppDialog from '@/Components/App/AppDialog.vue';
import AppInputNumber from '@/Components/App/AppInputNumber.vue';
import AppInputText from '@/Components/App/AppInputText.vue';
import AppPageHeader from '@/Components/App/AppPageHeader.vue';
import AppSelect from '@/Components/App/AppSelect.vue';
import AppStatCard from '@/Components/App/AppStatCard.vue';
import AppTextarea from '@/Components/App/AppTextarea.vue';
import BillingNav from '@/Components/Billing/BillingNav.vue';
import { PAYMENT_METHODS } from '@/Components/Billing/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate, formatMoney } from '@/lib/i18n';
import { fromLocalDateString, toLocalDateString } from '@/utils/date';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Expense {
    id: number;
    center_id: number;
    center: string;
    category_option_id: number | null;
    category: string | null;
    label: string;
    supplier: string | null;
    amount: number;
    currency: string;
    spent_on: string;
    method: string;
    reference: string | null;
    notes: string | null;
}

const props = defineProps<{
    expenses: { data: Expense[]; current_page: number; per_page: number; total: number };
    filters: { search?: string; category?: string };
    categories: { id: number; label: string }[];
    centers: { id: number; name: string }[];
    summary: { this_month: { currency: string; amount: number }[] };
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const { t, locale } = useI18n();

const search = ref(props.filters.search ?? '');
const category = ref<number | null>(props.filters.category ? Number(props.filters.category) : null);

const methodOptions = computed(() => PAYMENT_METHODS.map((value) => ({ value, label: t(`domain.paymentMethod.${value}`) })));

const columns = computed<AppDataTableColumn[]>(() => [
    { field: 'spent_on', header: t('billing.spentOn') },
    { field: 'label', header: t('billing.expenseLabel') },
    { field: 'category', header: t('billing.category') },
    { field: 'method', header: t('billing.method') },
    { field: 'amount', header: t('billing.amount') },
    { field: 'actions', header: '' },
]);

// Never summed across currencies: one figure per currency.
const monthTotal = computed(() =>
    props.summary.this_month.length
        ? props.summary.this_month.map((row) => formatMoney(row.amount, row.currency, locale.value)).join(' · ')
        : '—',
);

function visit(page = 1) {
    router.get(
        route('admin.billing.expenses.index'),
        { ...(search.value ? { search: search.value } : {}), ...(category.value ? { category: category.value } : {}), ...(page > 1 ? { page } : {}) },
        { preserveState: true, preserveScroll: true },
    );
}

const dialog = ref(false);
const editing = ref<Expense | null>(null);

const form = useForm({
    center_id: null as number | null,
    category_option_id: null as number | null,
    label: '',
    supplier: '',
    amount: null as number | null,
    spent_on: toLocalDateString(new Date()) as string | null,
    method: 'cash',
    reference: '',
    notes: '',
});

const spentOn = computed<Date | null>({
    get: () => fromLocalDateString(form.spent_on),
    set: (value) => (form.spent_on = value ? toLocalDateString(value) : null),
});

function open(expense: Expense | null) {
    editing.value = expense;
    form.clearErrors();
    form.center_id = expense?.center_id ?? null;
    form.category_option_id = expense?.category_option_id ?? null;
    form.label = expense?.label ?? '';
    form.supplier = expense?.supplier ?? '';
    form.amount = expense?.amount ?? null;
    form.spent_on = expense?.spent_on ?? toLocalDateString(new Date());
    form.method = expense?.method ?? 'cash';
    form.reference = expense?.reference ?? '';
    form.notes = expense?.notes ?? '';
    dialog.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (dialog.value = false) };

    if (editing.value) {
        form.put(route('admin.billing.expenses.update', editing.value.id), options);
    } else {
        form.post(route('admin.billing.expenses.store'), options);
    }
}

function destroy(expense: Expense) {
    if (window.confirm(t('billing.confirmDeleteExpense', { label: expense.label }))) {
        router.delete(route('admin.billing.expenses.destroy', expense.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('billing.expensesTitle')" />

    <AuthenticatedLayout>
        <AppPageHeader :title="t('billing.expensesTitle')" :subtitle="t('billing.expensesSubtitle')">
            <template v-if="can.create" #actions>
                <AppButton icon="mdi-plus" :label="t('billing.newExpense')" @click="open(null)" />
            </template>
        </AppPageHeader>

        <BillingNav current="expenses" />

        <v-row dense class="mb-4">
            <v-col cols="12" md="4">
                <AppStatCard :label="t('billing.expensesMonth')" :value="monthTotal" icon="mdi-cash-minus" color="warning" />
            </v-col>
        </v-row>

        <AppCard variant="flat" class="mb-4">
            <v-card-text>
                <form class="d-flex flex-wrap align-center ga-3" @submit.prevent="visit()">
                    <div style="flex: 1 1 260px"><AppInputText v-model="search" :label="t('billing.searchExpense')" /></div>
                    <div style="flex: 0 1 240px">
                        <AppSelect
                            v-model="category"
                            :options="categories"
                            option-label="label"
                            option-value="id"
                            :label="t('billing.category')"
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
                :value="expenses.data"
                :columns="columns"
                :rows="expenses.per_page"
                :total-records="expenses.total"
                :page="expenses.current_page"
                :empty-text="t('billing.emptyExpenses')"
                @page="visit"
            >
                <template #column-spent_on="{ item }">{{ formatDate(item.spent_on, locale) }}</template>
                <template #column-label="{ item }">
                    <span class="font-weight-medium">{{ item.label }}</span>
                    <div v-if="item.supplier || $page.props.auth.is_global_admin" class="text-caption text-medium-emphasis">
                        {{ [item.supplier, $page.props.auth.is_global_admin ? item.center : null].filter(Boolean).join(' · ') }}
                    </div>
                </template>
                <template #column-category="{ item }">{{ item.category ?? '—' }}</template>
                <template #column-method="{ item }">{{ t(`domain.paymentMethod.${item.method}`) }}</template>
                <template #column-amount="{ item }">
                    <span class="app-tabular font-weight-bold">{{ formatMoney(item.amount, item.currency, locale) }}</span>
                </template>
                <template #column-actions="{ item }">
                    <div class="d-flex justify-end ga-1">
                        <v-btn v-if="can.update" icon="mdi-pencil-outline" variant="text" size="small" :aria-label="t('common.edit')" @click="open(item)" />
                        <v-btn v-if="can.delete" icon="mdi-delete-outline" variant="text" size="small" color="error" :aria-label="t('common.delete')" @click="destroy(item)" />
                    </div>
                </template>
            </AppDataTable>
        </AppCard>

        <AppDialog v-model:visible="dialog" :header="t(editing ? 'billing.editExpense' : 'billing.newExpense')" max-width="560px">
            <form class="d-flex flex-column ga-4" @submit.prevent="submit">
                <AppSelect
                    v-if="centers.length && !editing"
                    v-model="form.center_id"
                    :options="centers"
                    option-label="name"
                    option-value="id"
                    :label="t('billing.center')"
                    :error="form.errors.center_id"
                />
                <AppInputText v-model="form.label" :label="t('billing.expenseLabel')" :error="form.errors.label" />
                <v-row dense>
                    <v-col cols="12" md="6">
                        <AppSelect
                            v-model="form.category_option_id"
                            :options="categories"
                            option-label="label"
                            option-value="id"
                            :label="t('billing.category')"
                            :error="form.errors.category_option_id"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputNumber v-model="form.amount" :label="t('billing.amount')" :min="0" :error="form.errors.amount" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppDatePicker v-model="spentOn" :label="t('billing.spentOn')" :error="form.errors.spent_on" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppSelect v-model="form.method" :options="methodOptions" option-label="label" option-value="value" :label="t('billing.method')" :error="form.errors.method" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.supplier" :label="t('billing.supplier')" :error="form.errors.supplier" />
                    </v-col>
                    <v-col cols="12" md="6">
                        <AppInputText v-model="form.reference" :label="t('billing.reference')" :error="form.errors.reference" />
                    </v-col>
                </v-row>
                <AppTextarea v-model="form.notes" :label="t('billing.notes')" :rows="2" :error="form.errors.notes" />

                <div class="d-flex justify-end ga-2">
                    <AppButton severity="secondary" :label="t('common.cancel')" @click="dialog = false" />
                    <AppButton type="submit" :label="t('common.save')" :loading="form.processing" />
                </div>
            </form>
        </AppDialog>
    </AuthenticatedLayout>
</template>
