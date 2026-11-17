<?php

namespace App\Domains\Billing\Http\Controllers\Admin;

use App\Domains\Billing\Http\Requests\SaveInstallmentsRequest;
use App\Domains\Billing\Http\Requests\StoreCreditNoteRequest;
use App\Domains\Billing\Http\Requests\StoreInvoiceRequest;
use App\Domains\Billing\Http\Requests\StorePaymentRequest;
use App\Domains\Billing\Http\Requests\StoreRefundRequest;
use App\Domains\Billing\Http\Resources\InvoiceResource;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\BillableCatalogResolver;
use App\Domains\Billing\Services\BillableSessionsResolver;
use App\Domains\Billing\Services\CreateInvoiceAction;
use App\Domains\Billing\Services\IssueCreditNoteAction;
use App\Domains\Billing\Services\ScheduleInstallmentsAction;
use App\Domains\Common\Models\EnumOption;
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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    /**
     * What the schedule says should be paid by a given day. Payments
     * are allocated to the oldest instalment first, so an invoice is
     * late exactly when this exceeds what was paid.
     */
    private const OVERDUE_SQL = '(SELECT COALESCE(SUM(i.amount), 0) FROM billing_invoice_installments i WHERE i.invoice_id = billing_invoices.id AND i.due_on < ?)';

    private const RELATIONS = ['lines', 'payments', 'creditNotes', 'installments', 'discountReason'];

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
            ->when($status === 'offered', fn ($query) => $query->active()->where('subtotal', '>', 0)->where('total', '<=', 0))
            ->when($status === 'overdue', fn ($query) => $query->active()->whereRaw(self::OVERDUE_SQL.' > paid_total', [now()->toDateString()]))
            ->when($status === 'cancelled', fn ($query) => $query->whereNotNull('cancelled_at'))
            // Same rules as Invoice::status(), in SQL.
            ->when($status === 'refund_due', fn ($query) => $query->active()->whereRaw('paid_total > total - credited_total'))
            ->when($status === 'credited', fn ($query) => $query->active()->where('total', '>', 0)->whereColumn('credited_total', '>=', 'total')->whereRaw('paid_total <= total - credited_total'))
            ->when($status === 'paid', fn ($query) => $query->active()->whereColumn('credited_total', '<', 'total')->whereRaw('paid_total = total - credited_total'))
            ->when($status === 'partial', fn ($query) => $query->active()->where('paid_total', '>', 0)->whereRaw('paid_total < total - credited_total'))
            ->when($status === 'unpaid', fn ($query) => $query->active()->where('paid_total', '<=', 0)->whereColumn('credited_total', '<', 'total'))
            ->withSum(['installments as overdue_scheduled' => fn ($query) => $query->whereDate('due_on', '<', now()->toDateString())], 'amount')
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
                // Scheduled amounts whose date has passed and are still unpaid.
                'overdue' => $this->amounts(
                    DB::table('billing_invoices')
                        ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
                        ->whereNull('cancelled_at')
                        ->whereRaw(self::OVERDUE_SQL.' > paid_total', [now()->toDateString()])
                        ->groupBy('currency')
                        ->selectRaw('currency, SUM('.self::OVERDUE_SQL.' - paid_total) as amount', [now()->toDateString()]),
                ),
                'discounts_this_month' => $this->amounts(
                    DB::table('billing_invoices')
                        ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
                        ->whereNull('cancelled_at')
                        ->where('issued_on', '>=', $monthStart)
                        ->groupBy('currency')
                        ->selectRaw('currency, SUM(discount_amount) as amount'),
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
    public function forPatient(Request $request, Patient $patient, BillableSessionsResolver $billable, BillableCatalogResolver $catalog): JsonResponse
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewAny', Invoice::class);

        $center = Center::query()->findOrFail($patient->intake_center_id);

        return response()->json([
            'invoices' => InvoiceResource::collection(
                Invoice::query()->where('patient_id', $patient->id)->with(self::RELATIONS)->orderByDesc('issued_on')->orderByDesc('id')->get(),
            ),
            'billable_sessions' => $billable->handle($patient, $center),
            'currency' => $center->currency,
            'catalog' => $catalog->handle($center),
            'discount_reasons' => EnumOption::query()->where('enum_type', Invoice::DISCOUNT_REASON_TYPE)->where('active', true)->orderBy('order')->get()
                ->map(fn (EnumOption $option) => ['id' => $option->id, 'label' => $option->localizedLabel()])->all(),
            // Standing discount of the patient, pre-applied to new invoices.
            'patient_discount' => [
                'percent' => $patient->discount_percent !== null ? (float) $patient->discount_percent : null,
                'reason_option_id' => $patient->discount_reason_option_id,
            ],
            'can' => [
                'discount' => $this->allowed($request, 'invoices.discount'),
                'schedule' => $this->allowed($request, 'invoices.schedule'),
                'create' => Gate::allows('create', Invoice::class),
                'pay' => $request->user()->isGlobalAdmin() || $request->user()->can('payments.create'),
                'cancel' => $request->user()->isGlobalAdmin() || $request->user()->can('invoices.cancel'),
                'credit' => $request->user()->isGlobalAdmin() || $request->user()->can('invoices.credit'),
                'refund' => $request->user()->isGlobalAdmin() || $request->user()->can('payments.refund'),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request, Patient $patient, CreateInvoiceAction $action, ScheduleInstallmentsAction $schedule): JsonResponse
    {
        $center = Center::query()->findOrFail($patient->intake_center_id);

        /** @var array{issued_on: string, treatment_id?: int|null, notes?: string|null, discount?: array{percent?: float|int|string|null, amount?: float|int|string|null, reason_option_id?: int|null, note?: string|null}|null, lines: array<int, array{label: string, quantity: float|int|string, unit_price: float|int|string, treatment_session_id?: int|null, care_item_id?: int|null}>} $data */
        $data = $request->safe()->except(['payment', 'installments']);

        // Granting a discount is a manager's decision. Anyone else bills
        // with the patient's standing discount, whatever was submitted.
        if (! $this->allowed($request, 'invoices.discount')) {
            $data['discount'] = $patient->discount_percent !== null
                ? ['percent' => (float) $patient->discount_percent, 'reason_option_id' => $patient->discount_reason_option_id]
                : null;
        }

        $installments = $request->validated('installments') ?? [];
        abort_if($installments !== [] && ! $this->allowed($request, 'invoices.schedule'), 403);

        // One transaction: a schedule that does not add up rolls the
        // invoice (and its number) back.
        $invoice = DB::transaction(function () use ($request, $action, $schedule, $patient, $center, $data, $installments) {
            $invoice = $action->handle($patient, $center, $data, $request->user());
            $payment = $request->validated('payment');

            if (is_array($payment) && (float) $invoice->total > 0) {
                $invoice->payments()->create([
                    'amount' => min((float) $payment['amount'], (float) $invoice->total),
                    'method' => $payment['method'],
                    'reference' => $payment['reference'] ?? null,
                    'paid_on' => now()->toDateString(),
                    'received_by' => $request->user()->id,
                ]);
                $invoice->refreshTotals();
            }

            if ($installments !== []) {
                $schedule->handle($invoice, $installments);
            }

            return $invoice;
        });

        return response()->json(new InvoiceResource($invoice->load(self::RELATIONS)), 201);
    }

    /**
     * Sets, replaces or (empty list) removes the payment schedule.
     */
    public function updateInstallments(SaveInstallmentsRequest $request, Invoice $invoice, ScheduleInstallmentsAction $schedule): JsonResponse
    {
        $schedule->handle($invoice, $request->validated('installments'));

        return response()->json(new InvoiceResource($invoice->refresh()->load(self::RELATIONS)));
    }

    /**
     * Standing discount of a patient (social rate), applied to their
     * next invoices. Null percent removes it.
     */
    public function updatePatientDiscount(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);
        abort_unless($this->allowed($request, 'invoices.discount'), 403);

        $validated = $request->validate([
            'percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'reason_option_id' => [
                'required_with:percent', 'nullable', 'integer',
                Rule::exists('enum_options', 'id')->where('enum_type', Invoice::DISCOUNT_REASON_TYPE),
            ],
        ]);

        $percent = $validated['percent'] ?? null;

        $patient->update([
            'discount_percent' => $percent,
            'discount_reason_option_id' => $percent !== null ? $validated['reason_option_id'] : null,
        ]);

        return response()->json(['percent' => $percent !== null ? (float) $percent : null, 'reason_option_id' => $patient->discount_reason_option_id]);
    }

    private function allowed(Request $request, string $permission): bool
    {
        return $request->user()->isGlobalAdmin() || $request->user()->can($permission);
    }

    public function storePayment(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice->payments()->create([
            ...$request->validated(),
            'received_by' => $request->user()->id,
        ]);
        $invoice->refreshTotals();

        return response()->json(new InvoiceResource($invoice->load(self::RELATIONS)), 201);
    }

    public function destroyPayment(Payment $payment): JsonResponse
    {
        $invoice = $payment->invoice;
        Gate::authorize('deletePayment', $invoice);

        $payment->delete();
        $invoice->refreshTotals();

        return response()->json(new InvoiceResource($invoice->load(self::RELATIONS)));
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        Gate::authorize('cancel', $invoice);

        // An invoice that already moved money or was corrected is never
        // cancelled: it is credited (credit note) instead.
        abort_if($invoice->payments()->exists(), 422, __('Supprimez d\'abord les paiements de cette facture.'));
        abort_if($invoice->creditNotes()->exists(), 422, __('Cette facture a déjà un avoir.'));

        $invoice->update(['cancelled_at' => now()]);

        return response()->json(new InvoiceResource($invoice->load(self::RELATIONS)));
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

        return response()->json(new InvoiceResource($invoice->refresh()->load(self::RELATIONS)), 201);
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

        return response()->json(new InvoiceResource($invoice->load(self::RELATIONS)), 201);
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

        $invoice->load([...self::RELATIONS, 'patient', 'center.country']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->stream($invoice->number.'.pdf');
    }
}
