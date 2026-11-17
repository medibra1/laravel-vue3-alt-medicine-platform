export interface InvoiceLine {
    id: number;
    label: string;
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

export type InvoiceStatus = 'unpaid' | 'partial' | 'paid' | 'cancelled';

export interface Invoice {
    id: number;
    number: string;
    center_id: number;
    patient_id: number;
    treatment_id: number | null;
    currency: string;
    issued_on: string;
    total: number;
    paid_total: number;
    balance: number;
    status: InvoiceStatus;
    notes: string | null;
    patient?: { id: number; name: string };
    center?: { id: number; name: string };
    lines?: InvoiceLine[];
    payments?: InvoicePayment[];
}

export interface SuggestedLine {
    label: string;
    quantity: number;
    unit_price: number;
    treatment_session_id: number | null;
    care_item_id: number | null;
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
};

export const INVOICE_STATUS_ICON: Record<InvoiceStatus, string> = {
    unpaid: 'mdi-clock-outline',
    partial: 'mdi-circle-half-full',
    paid: 'mdi-check-circle-outline',
    cancelled: 'mdi-cancel',
};
