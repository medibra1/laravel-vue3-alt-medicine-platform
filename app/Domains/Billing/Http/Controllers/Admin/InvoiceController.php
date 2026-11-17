<?php

namespace App\Domains\Billing\Http\Controllers\Admin;

use App\Domains\Billing\Http\Requests\StoreCreditNoteRequest;
use App\Domains\Billing\Http\Requests\StoreInvoiceRequest;
use App\Domains\Billing\Http\Requests\StorePaymentRequest;
use App\Domains\Billing\Http\Requests\StoreRefundRequest;
use App\Domains\Billing\Http\Resources\InvoiceResource;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\BillableSessionsResolver;
use App\Domains\Billing\Services\CreateInvoiceAction;
use App\Domains\Billing\Services\IssueCreditNoteAction;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $centerId = $request->user()->isGlobalAdmin() ? null : getPermissionsTeamId();
        $scoped = fn (): Builder => Invoice::query()->when($centerId, fn ($query) => $query->where('center_id', $centerId));

        $status = $request->string('status')->value();
        $search = trim($request->string('search')->value());

        $invoices = $scoped()
            ->with(['patient', 'center'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($query) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            }))
            ->when($status === 'cancelled', fn ($query) => $query->whereNotNull('cancelled_at'))
            // Same rules as Invoice::status(), in SQL.
            ->when($status === 'refund_due', fn ($query) => $query->active()->whereRaw('paid_total > total - credited_total'))
            ->when($status === 'credited', fn ($query) => $query->active()->where('total', '>', 0)->whereColumn('credited_total', '>=', 'total')->whereRaw('paid_total <= total - credited_total'))
            ->when($status === 'paid', fn ($query) => $query->active()->whereColumn('credited_total', '<', 'total')->whereRaw('paid_total = total - credited_total'))
            ->when($status === 'partial', fn ($query) => $query->active()->where('paid_total', '>', 0)->whereRaw('paid_total < total - credited_total'))
            ->when($status === 'unpaid', fn ($query) => $query->active()->where('paid_total', '<=', 0)->whereColumn('credited_total', '<', 'total'))
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $invoices->setCollection(InvoiceResource::collection($invoices->getCollection())->collection);

        $monthStart = now()->startOfMonth()->toDateString();

        return Inertia::render('Admin/Billing/Index', [
            'invoices' => $invoices,
            'filters' => (object) $request->only(['search', 'status']),
            // Sums are per currency: centers of different countries never add up.
            'summary' => [
                'collected_this_month' => $this->amounts(
                    DB::table('billing_payments')
                        ->join('billing_invoices', 'billing_invoices.id', '=', 'billing_payments.invoice_id')
                        ->when($centerId, fn ($query) => $query->where('billing_invoices.center_id', $centerId))
                        ->where('billing_payments.paid_on', '>=', $monthStart)
                        ->groupBy('billing_invoices.currency')
                        ->selectRaw('billing_invoices.currency as currency, SUM(billing_payments.amount) as amount'),
                ),
                'outstanding' => $this->amounts(
                    DB::table('billing_invoices')
                        ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
                        ->whereNull('cancelled_at')
                        ->whereRaw('total - credited_total - paid_total > 0')
                        ->groupBy('currency')
                        ->selectRaw('currency, SUM(total - credited_total - paid_total) as amount'),
                ),
                'unpaid_count' => $scoped()->active()->whereRaw('paid_total < total - credited_total')->count(),
            ],
            'centers' => $this->exportCenters($request),
            'can' => ['export' => $request->user()->isGlobalAdmin() || $request->user()->can('accounting.export')],
        ]);
    }

    /**
     * @return array<int, array{currency: string, amount: float}>
     */
    private function amounts(QueryBuilder $query): array
    {
        return $query->get()
            ->map(fn ($row) => ['currency' => (string) $row->currency, 'amount' => round((float) $row->amount, 2)])
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * Everything the patient file's billing tab needs, as JSON.
     */
    public function forPatient(Request $request, Patient $patient, BillableSessionsResolver $billable): JsonResponse
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewAny', Invoice::class);

        $center = Center::query()->findOrFail($patient->intake_center_id);

        return response()->json([
            'invoices' => InvoiceResource::collection(
                Invoice::query()->where('patient_id', $patient->id)->with(['lines', 'payments', 'creditNotes'])->orderByDesc('issued_on')->orderByDesc('id')->get(),
            ),
            'billable_sessions' => $billable->handle($patient, $center),
            'currency' => $center->currency,
            'can' => [
                'create' => Gate::allows('create', Invoice::class),
                'pay' => $request->user()->isGlobalAdmin() || $request->user()->can('payments.create'),
                'cancel' => $request->user()->isGlobalAdmin() || $request->user()->can('invoices.cancel'),
                'credit' => $request->user()->isGlobalAdmin() || $request->user()->can('invoices.credit'),
                'refund' => $request->user()->isGlobalAdmin() || $request->user()->can('payments.refund'),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request, Patient $patient, CreateInvoiceAction $action): JsonResponse
    {
        $center = Center::query()->findOrFail($patient->intake_center_id);

        /** @var array{issued_on: string, treatment_id?: int|null, notes?: string|null, lines: array<int, array{label: string, quantity: float|int|string, unit_price: float|int|string, treatment_session_id?: int|null, care_item_id?: int|null}>} $data */
        $data = $request->safe()->except('payment');

        $invoice = $action->handle($patient, $center, $data, $request->user());

        $payment = $request->validated('payment');

        if (is_array($payment)) {
            $invoice->payments()->create([
                'amount' => min((float) $payment['amount'], (float) $invoice->total),
                'method' => $payment['method'],
                'reference' => $payment['reference'] ?? null,
                'paid_on' => now()->toDateString(),
                'received_by' => $request->user()->id,
            ]);
            $invoice->refreshTotals();
        }

        return response()->json(new InvoiceResource($invoice->load(['lines', 'payments', 'creditNotes'])), 201);
    }

    public function storePayment(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice->payments()->create([
            ...$request->validated(),
            'received_by' => $request->user()->id,
        ]);
        $invoice->refreshTotals();

        return response()->json(new InvoiceResource($invoice->load(['lines', 'payments', 'creditNotes'])), 201);
    }

    public function destroyPayment(Payment $payment): JsonResponse
    {
        $invoice = $payment->invoice;
        Gate::authorize('deletePayment', $invoice);

        $payment->delete();
        $invoice->refreshTotals();

        return response()->json(new InvoiceResource($invoice->load(['lines', 'payments', 'creditNotes'])));
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        Gate::authorize('cancel', $invoice);

        // An invoice that already moved money or was corrected is never
        // cancelled: it is credited (credit note) instead.
        abort_if($invoice->payments()->exists(), 422, __('Supprimez d\'abord les paiements de cette facture.'));
        abort_if($invoice->creditNotes()->exists(), 422, __('Cette facture a déjà un avoir.'));

        $invoice->update(['cancelled_at' => now()]);

        return response()->json(new InvoiceResource($invoice->load(['lines', 'payments', 'creditNotes'])));
    }

    /**
     * Credit note: lowers what is due on an invoice without editing it.
     */
    public function storeCreditNote(StoreCreditNoteRequest $request, Invoice $invoice, IssueCreditNoteAction $action): JsonResponse
    {
        $action->handle(
            $invoice,
            (float) $request->validated('amount'),
            (string) $request->validated('issued_on'),
            $request->validated('reason'),
            $request->user(),
        );

        return response()->json(new InvoiceResource($invoice->refresh()->load(['lines', 'payments', 'creditNotes'])), 201);
    }

    /**
     * Refund: money given back to the patient, stored as a negative
     * payment. Only what was paid beyond the amount due can go back.
     */
    public function storeRefund(StoreRefundRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice->payments()->create([
            'amount' => -abs((float) $request->validated('amount')),
            'method' => $request->validated('method'),
            'paid_on' => $request->validated('paid_on'),
            'reference' => $request->validated('reference'),
            'notes' => $request->validated('notes'),
            'received_by' => $request->user()->id,
        ]);
        $invoice->refreshTotals();

        return response()->json(new InvoiceResource($invoice->load(['lines', 'payments', 'creditNotes'])), 201);
    }

    /**
     * Every payment and refund, most recent first.
     */
    public function payments(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $centerId = $request->user()->isGlobalAdmin() ? null : getPermissionsTeamId();
        $method = $request->string('method')->value();

        $payments = Payment::query()
            ->whereHas('invoice', fn ($query) => $query->when($centerId, fn ($query) => $query->where('center_id', $centerId)))
            ->with(['invoice.patient', 'invoice.center', 'receivedBy'])
            ->when(in_array($method, Payment::METHODS, true), fn ($query) => $query->where('method', $method))
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('paid_on', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('paid_on', '<=', $to))
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        $payments->through(fn (Payment $payment) => [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'paid_on' => $payment->paid_on->toDateString(),
            'reference' => $payment->reference,
            'currency' => $payment->invoice->currency,
            'invoice' => ['id' => $payment->invoice->id, 'number' => $payment->invoice->number],
            'patient' => [
                'id' => $payment->invoice->patient_id,
                'name' => trim($payment->invoice->patient->first_name.' '.$payment->invoice->patient->last_name),
            ],
            'center' => $payment->invoice->center->name,
            'received_by' => $payment->receivedBy?->name,
        ]);

        return Inertia::render('Admin/Billing/Payments', [
            'payments' => $payments,
            'filters' => (object) $request->only(['method', 'from', 'to']),
        ]);
    }

    /**
     * Centers a global admin can pick for the accounting export (a
     * manager always exports their active center).
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function exportCenters(Request $request): array
    {
        if (! $request->user()->isGlobalAdmin()) {
            return [];
        }

        return Center::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Center $center) => ['id' => $center->id, 'name' => $center->name])->all();
    }

    public function pdf(Invoice $invoice): HttpResponse
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['lines', 'payments', 'creditNotes', 'patient', 'center.country']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->stream($invoice->number.'.pdf');
    }
}
