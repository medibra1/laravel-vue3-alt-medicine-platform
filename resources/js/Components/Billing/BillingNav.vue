<script setup lang="ts">
import { handleNavClick } from '@/utils/inertiaNavClick';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/** Sections of the billing module, shown on top of each of its pages. */
defineProps<{ current: 'invoices' | 'payments' | 'expenses' }>();

const { t } = useI18n();
const page = usePage();

const sections = computed(() => [
    { key: 'invoices', label: t('billing.navInvoices'), icon: 'mdi-receipt-text-outline', href: route('admin.billing.index') },
    { key: 'payments', label: t('billing.navPayments'), icon: 'mdi-cash-multiple', href: route('admin.billing.payments') },
    // Expenses are the manager's: a practitioner only bills and collects.
    ...(page.props.auth.is_global_admin || page.props.auth.is_manager
        ? [{ key: 'expenses', label: t('billing.navExpenses'), icon: 'mdi-cash-minus', href: route('admin.billing.expenses.index') }]
        : []),
]);
</script>

<template>
    <nav class="billing-nav mb-4">
        <a
            v-for="section in sections"
            :key="section.key"
            :href="section.href"
            class="billing-nav__item"
            :class="{ 'billing-nav__item--active': section.key === current }"
            :aria-current="section.key === current ? 'page' : undefined"
            @click="handleNavClick($event, section.href)"
        >
            <v-icon :icon="section.icon" size="18" />
            {{ section.label }}
        </a>
    </nav>
</template>

<style scoped>
.billing-nav {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 4px;
    border-radius: 14px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
}

.billing-nav__item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    color: rgba(var(--v-theme-on-surface), 0.7);
}

.billing-nav__item:hover {
    background: rgba(var(--v-theme-on-surface), 0.05);
}

.billing-nav__item--active {
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));
}
</style>
