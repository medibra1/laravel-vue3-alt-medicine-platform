<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareItem;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;

function billablePatient(Center $center): Patient
{
    return Patient::factory()->create(['intake_center_id' => $center->id]);
}

function invoicePayload(array $overrides = []): array
{
    return [
        'issued_on' => '2026-11-10',
        'lines' => [
            ['label' => 'Séance', 'quantity' => 1, 'unit_price' => 50],
            ['label' => 'Tisane', 'quantity' => 2, 'unit_price' => 7.5],
        ],
        ...$overrides,
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('admin.billing.index'))->assertRedirect(route('login'));
});

test('a manager creates an invoice numbered per center with computed totals', function () {
    $center = Center::factory()->create(['currency' => 'XOF']);
    $manager = actingAsManagerOf($center);
    $patient = billablePatient($center);

    $response = $this->actingAs($manager)->postJson(route('admin.patients.invoices.store', $patient), invoicePayload());

    $response->assertCreated()
        ->assertJsonPath('number', 'F-2026-00001')
        ->assertJsonPath('currency', 'XOF')
        ->assertJsonPath('total', 65)
        ->assertJsonPath('status', 'unpaid');

    $this->actingAs($manager)->postJson(route('admin.patients.invoices.store', $patient), invoicePayload())
        ->assertJsonPath('number', 'F-2026-00002');
});

test('an invoice can be settled on the spot', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);

    $this->actingAs($manager)
        ->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload([
            'payment' => ['amount' => 65, 'method' => 'cash'],
        ]))
        ->assertCreated()
        ->assertJsonPath('status', 'paid')
        ->assertJsonPath('balance', 0);
});

test('a manager cannot bill a patient of another center', function () {
    $manager = actingAsManagerOf(Center::factory()->create());
    $patient = billablePatient(Center::factory()->create());

    $this->actingAs($manager)->postJson(route('admin.patients.invoices.store', $patient), invoicePayload())->assertForbidden();
});

test('an invoice needs at least one line', function () {
    $center = Center::factory()->create();

    $this->actingAs(actingAsManagerOf($center))
        ->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload(['lines' => []]))
        ->assertJsonValidationErrors('lines');
});

test('payments move an invoice from unpaid to partial to paid', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $invoiceId = $this->actingAs($manager)
        ->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload())->json('id');

    $pay = fn (float $amount) => $this->actingAs($manager)->postJson(route('admin.invoices.payments.store', $invoiceId), [
        'amount' => $amount, 'method' => 'mobile_money', 'paid_on' => now()->toDateString(),
    ]);

    $pay(20)->assertCreated()->assertJsonPath('status', 'partial')->assertJsonPath('balance', 45);
    $pay(45)->assertCreated()->assertJsonPath('status', 'paid');
});

test('a payment cannot exceed the balance due', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $invoiceId = $this->actingAs($manager)
        ->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload())->json('id');

    $this->actingAs($manager)->postJson(route('admin.invoices.payments.store', $invoiceId), [
        'amount' => 65.01, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertJsonValidationErrors('amount');
});

test('an invoice with payments cannot be cancelled until they are removed', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $invoice = $this->actingAs($manager)
        ->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload([
            'payment' => ['amount' => 10, 'method' => 'cash'],
        ]))->json();

    $this->actingAs($manager)->postJson(route('admin.invoices.cancel', $invoice['id']))->assertStatus(422);

    $this->actingAs($manager)->deleteJson(route('admin.payments.destroy', $invoice['payments'][0]['id']))
        ->assertOk()->assertJsonPath('status', 'unpaid');

    $this->actingAs($manager)->postJson(route('admin.invoices.cancel', $invoice['id']))
        ->assertOk()->assertJsonPath('status', 'cancelled');
});

test('a practitioner can bill and collect but not cancel', function () {
    $center = Center::factory()->create();
    $practitioner = actingAsPractitionerOf($center);
    $invoiceId = $this->actingAs($practitioner)
        ->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload())
        ->assertCreated()->json('id');

    $this->actingAs($practitioner)->postJson(route('admin.invoices.payments.store', $invoiceId), [
        'amount' => 5, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertCreated();

    $this->actingAs($practitioner)->postJson(route('admin.invoices.cancel', $invoiceId))->assertForbidden();
});

test('unbilled sessions are suggested with the center prices, then disappear once billed', function () {
    $center = Center::factory()->create(['settings' => ['session_price_in_person' => 40, 'session_price_remote' => 25]]);
    $manager = actingAsManagerOf($center);
    $patient = billablePatient($center);
    $treatment = Treatment::factory()->for($center, 'center')->create(['patient_id' => $patient->id]);
    $session = TreatmentSession::factory()->create(['treatment_id' => $treatment->id, 'modality' => 'remote']);
    $priced = CareItem::factory()->create();
    $free = CareItem::factory()->create();
    $session->careItems()->sync([$priced->id, $free->id]);
    $center->careItemPrices()->create(['care_item_id' => $priced->id, 'price' => 12]);

    $billable = $this->actingAs($manager)->getJson(route('admin.patients.invoices.index', $patient))
        ->assertOk()->json('billable_sessions');

    expect($billable)->toHaveCount(1)
        ->and($billable[0]['lines'])->toHaveCount(2)
        ->and($billable[0]['lines'][0]['unit_price'])->toEqual(25)
        ->and($billable[0]['lines'][0]['treatment_session_id'])->toBe($session->id)
        ->and($billable[0]['lines'][1]['unit_price'])->toEqual(12);

    $this->actingAs($manager)->postJson(route('admin.patients.invoices.store', $patient), invoicePayload(['lines' => $billable[0]['lines']]))
        ->assertCreated();

    $this->actingAs($manager)->getJson(route('admin.patients.invoices.index', $patient))
        ->assertJsonCount(0, 'billable_sessions')
        ->assertJsonCount(1, 'invoices');
});

test('the billing list is scoped to the manager center and filterable by status', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $this->actingAs($manager)->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload());
    $this->actingAs($manager)->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload([
        'payment' => ['amount' => 65, 'method' => 'cash'],
    ]));

    $other = Center::factory()->create();
    Invoice::create(['center_id' => $other->id, 'patient_id' => billablePatient($other)->id, 'number' => 'F-2026-00001', 'currency' => 'EUR', 'issued_on' => '2026-11-01', 'total' => 10]);

    $this->actingAs($manager)->get(route('admin.billing.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/Billing/Index')->has('invoices.data', 2)->where('summary.unpaid_count', 1));

    $this->actingAs($manager)->get(route('admin.billing.index', ['status' => 'paid']))
        ->assertInertia(fn ($page) => $page->has('invoices.data', 1));
});

test('the invoice pdf is served to its center only', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $invoiceId = $this->actingAs($manager)
        ->postJson(route('admin.patients.invoices.store', billablePatient($center)), invoicePayload())->json('id');

    $this->actingAs($manager)->get(route('admin.invoices.pdf', $invoiceId))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs(actingAsManagerOf(Center::factory()->create()))->get(route('admin.invoices.pdf', $invoiceId))->assertForbidden();
});
