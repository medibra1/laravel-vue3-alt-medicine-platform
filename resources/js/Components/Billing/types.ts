export interface InvoiceLine {
    id: number;
    label: string;
    description: string | null;
    quantity: number;
    unit_price: number;
    total: number;
    treatment_session_id: number | null;
}

export interface InvoicePayment {
    id: number;
    amount: number;
    method: string;
    paid_on: string;
    reference: string | null;
}

export interface InvoiceCreditNote {
    id: number;
    number: string;
    amount: number;
    issued_on: string;
    reason: string | null;
}

export type InstallmentStatus = 'paid' | 'overdue' | 'partial' | 'upcoming';

export interface InvoiceInstallment {
    id: number;
    due_on: string;
    amount: number;
    paid: number;
    status: InstallmentStatus;
}

export const INSTALLMENT_STATUS_COLOR: Record<InstallmentStatus, string> = {
    paid: 'success',
    overdue: 'error',
    partial: 'info',
    upcoming: 'secondary',
};

/** A service the center sells, with its own price (see BillableCatalogResolver). */
export interface CatalogEntry {
    key: string;
    label: string;
    group: string;
    unit_price: number;
    care_item_id: number | null;
    care_category_id: number | null;
}

export interface DiscountReason {
    id: number;
    label: string;
}

export interface PatientDiscount {
    percent: number | null;
    reason_option_id: number | null;
}

export type InvoiceStatus = 'unpaid' | 'partial' | 'paid' | 'cancelled' | 'credited' | 'refund_due' | 'offered';

export interface Invoice {
    id: number;
    number: string;
    center_id: number;
    patient_id: number;
    treatment_id: number | null;
    currency: string;
    issued_on: string;
    subtotal: number;
    discount_amount: number;
    discount_percent: number | null;
    discount_reason?: string | null;
    discount_note: string | null;
    total: number;
    paid_total: number;
    credited_total: number;
    balance: number;
    status: InvoiceStatus;
    notes: string | null;
    patient?: { id: number; name: string };
    center?: { id: number; name: string };
    lines?: InvoiceLine[];
    payments?: InvoicePayment[];
    credit_notes?: InvoiceCreditNote[];
    installments?: InvoiceInstallment[];
    overdue_amount: number;
}

export interface SuggestedLine {
    label: string;
    quantity: number;
    unit_price: number;
    treatment_session_id: number | null;
    care_item_id: number | null;
    care_category_id: number | null;
}

export interface BillableSession {
    session_id: number;
    treatment_id: number;
    session_date: string;
    modality: string;
    lines: SuggestedLine[];
}

export const PAYMENT_METHODS = ['cash', 'card', 'mobile_money', 'bank_transfer', 'cheque', 'other'] as const;

export const INVOICE_STATUS_COLOR: Record<InvoiceStatus, string> = {
    unpaid: 'warning',
    partial: 'info',
    paid: 'success',
    cancelled: 'secondary',
    credited: 'secondary',
    refund_due: 'error',
    offered: 'primary',
};

export const INVOICE_STATUS_ICON: Record<InvoiceStatus, string> = {
    unpaid: 'mdi-clock-outline',
    partial: 'mdi-circle-half-full',
    paid: 'mdi-check-circle-outline',
    cancelled: 'mdi-cancel',
    credited: 'mdi-file-undo-outline',
    refund_due: 'mdi-cash-refund',
    offered: 'mdi-hand-heart-outline',
};
