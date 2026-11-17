<script setup lang="ts">
import { INVOICE_STATUS_COLOR, INVOICE_STATUS_ICON, type InvoiceStatus } from './types';

defineProps<{ status: InvoiceStatus }>();
</script>

<template>
    <v-chip :color="INVOICE_STATUS_COLOR[status]" :prepend-icon="INVOICE_STATUS_ICON[status]" variant="tonal" size="small">
        {{ $t(`domain.invoiceStatus.${status}`) }}
    </v-chip>
</template>
