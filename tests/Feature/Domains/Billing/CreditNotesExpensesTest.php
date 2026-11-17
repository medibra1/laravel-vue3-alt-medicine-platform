<?php

use App\Domains\Billing\Models\Expense;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;
use App\Domains\Practitioners\Models\Practitioner;

function paidInvoice(Center $center, $manager, float $paid = 100): Invoice
{
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $id = test()->actingAs($manager)->postJson(route('admin.patients.invoices.store', $patient), [
        'issued_on' => '2026-11-10',
        'lines' => [['label' => 'Séance', 'quantity' => 1, 'unit_price' => 100]],
        ...($paid > 0 ? ['payment' => ['amount' => $paid, 'method' => 'cash']] : []),
    ])->assertCreated()->json('id');

    return Invoice::query()->findOrFail($id);
}

function expenseCategory(): EnumOption
{
    return EnumOption::query()->create([
        'enum_type' => Expense::CATEGORY_TYPE, 'code' => 'RENT', 'label' => ['fr' => 'Loyer'], 'order' => 1, 'active' => true,
    ]);
}

test('a credit note lowers what is due and is numbered on its own sequence', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $invoice = paidInvoice($center, $manager, 0);

    $this->actingAs($manager)->postJson(route('admin.invoices.credit-notes.store', $invoice), [
        'amount' => 30, 'issued_on' => now()->toDateString(), 'reason' => 'Geste commercial',
    ])->assertCreated()
        ->assertJsonPath('credit_notes.0.number', 'AV-'.now()->year.'-00001')
        ->assertJsonPath('credited_total', 30)
        ->assertJsonPath('balance', 70)
        ->assertJsonPath('status', 'unpaid');

    // Never more than what is left on the invoice.
    $this->actingAs($manager)->postJson(route('admin.invoices.credit-notes.store', $invoice), [
        'amount' => 71, 'issued_on' => now()->toDateString(), 'reason' => 'Trop',
    ])->assertUnprocessable();

    // A credited invoice can no longer be cancelled.
    $this->actingAs($manager)->postJson(route('admin.invoices.cancel', $invoice))->assertStatus(422);
});

test('crediting a paid invoice makes a refund due, and the refund settles it', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $invoice = paidInvoice($center, $manager, 100);

    // Nothing to give back while the invoice is simply paid.
    $this->actingAs($manager)->postJson(route('admin.invoices.refunds.store', $invoice), [
        'amount' => 10, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertUnprocessable();

    $this->actingAs($manager)->postJson(route('admin.invoices.credit-notes.store', $invoice), [
        'amount' => 100, 'issued_on' => now()->toDateString(), 'reason' => 'Séance annulée',
    ])->assertCreated()->assertJsonPath('status', 'refund_due')->assertJsonPath('balance', -100);

    $this->actingAs($manager)->postJson(route('admin.invoices.refunds.store', $invoice), [
        'amount' => 101, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertUnprocessable();

    $this->actingAs($manager)->postJson(route('admin.invoices.refunds.store', $invoice), [
        'amount' => 100, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertCreated()
        ->assertJsonPath('status', 'credited')
        ->assertJsonPath('balance', 0)
        ->assertJsonPath('paid_total', 0);
});

test('a practitioner can neither credit nor refund', function () {
    $center = Center::factory()->create();
    $invoice = paidInvoice($center, actingAsManagerOf($center), 100);
    $practitioner = actingAsPractitionerOf($center);

    $this->actingAs($practitioner)->postJson(route('admin.invoices.credit-notes.store', $invoice), [
        'amount' => 10, 'issued_on' => now()->toDateString(), 'reason' => 'x',
    ])->assertForbidden();
    $this->actingAs($practitioner)->postJson(route('admin.invoices.refunds.store', $invoice), [
        'amount' => 10, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertForbidden();
    $this->actingAs($practitioner)->get(route('admin.billing.expenses.index'))->assertForbidden();
});

test('a manager records, edits and deletes an expense of their own center only', function () {
    $center = Center::factory()->create(['currency' => 'XOF']);
    $other = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $category = expenseCategory();

    $payload = [
        'category_option_id' => $category->id, 'label' => 'Loyer novembre', 'amount' => 250,
        'spent_on' => now()->toDateString(), 'method' => 'bank_transfer',
        // Ignored: a manager's expense always goes to their own center.
        'center_id' => $other->id,
    ];

    $this->actingAs($manager)->post(route('admin.billing.expenses.store'), $payload)->assertRedirect();

    $expense = Expense::query()->sole();
    expect($expense->center_id)->toBe($center->id)->and($expense->currency)->toBe('XOF');

    $this->actingAs($manager)->put(route('admin.billing.expenses.update', $expense), [...$payload, 'amount' => 300])->assertRedirect();
    expect((float) $expense->refresh()->amount)->toBe(300.0);

    $this->actingAs($manager)->get(route('admin.billing.expenses.index'))->assertOk();

    $foreign = Expense::query()->create([...$expense->only(['category_option_id', 'label', 'amount', 'currency', 'spent_on', 'method']), 'center_id' => $other->id]);
    $this->actingAs($manager)->delete(route('admin.billing.expenses.destroy', $foreign))->assertForbidden();
    $this->actingAs($manager)->delete(route('admin.billing.expenses.destroy', $expense))->assertRedirect();
    expect(Expense::query()->whereKey($expense->id)->exists())->toBeFalse();
});

test('the accounting export is a balanced journal scoped to the manager center', function () {
    $center = Center::factory()->create();
    $other = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $invoice = paidInvoice($center, $manager, 100);
    paidInvoice($other, actingAsManagerOf($other), 100);

    $this->actingAs($manager)->postJson(route('admin.invoices.credit-notes.store', $invoice), [
        'amount' => 40, 'issued_on' => now()->toDateString(), 'reason' => 'Remise',
    ])->assertCreated();
    $this->actingAs($manager)->postJson(route('admin.invoices.refunds.store', $invoice), [
        'amount' => 40, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertCreated();
    Expense::query()->create([
        'center_id' => $center->id, 'category_option_id' => expenseCategory()->id, 'label' => 'Loyer', 'amount' => 25,
        'currency' => 'EUR', 'spent_on' => now()->toDateString(), 'method' => 'cash',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.billing.export', ['from' => '2026-01-01', 'to' => now()->toDateString()]));
    $response->assertOk();

    $rows = collect(explode("\n", trim($response->streamedContent())))->skip(1)->map(fn (string $line) => str_getcsv($line, ';'));

    // invoice, credit note, payment, refund, expense: two rows each.
    expect($rows)->toHaveCount(10)
        ->and(round($rows->sum(fn (array $row) => (float) $row[6]), 2))->toBe(round($rows->sum(fn (array $row) => (float) $row[7]), 2))
        ->and($rows->pluck(9)->unique()->all())->toBe([$center->name]);
});

test('care pricing follows the category mode: none, flat per session, or per item', function () {
    $center = Center::factory()->create(['settings' => ['session_price_in_person' => 40]]);
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $treatment = Treatment::factory()->for($center, 'center')->create(['patient_id' => $patient->id]);
    $session = TreatmentSession::factory()->create(['treatment_id' => $treatment->id, 'modality' => 'in_person']);

    $free = CareCategory::factory()->create(['pricing_mode' => CareCategory::PRICING_NONE]);
    $flat = CareCategory::factory()->create(['pricing_mode' => CareCategory::PRICING_CATEGORY]);
    $verse = CareItem::factory()->create(['care_category_id' => $free->id]);
    $zoneA = CareItem::factory()->create(['care_category_id' => $flat->id]);
    $zoneB = CareItem::factory()->create(['care_category_id' => $flat->id]);
    $session->careItems()->sync([$verse->id, $zoneA->id, $zoneB->id]);

    // A stray item price on a never-billed category is ignored.
    $center->careItemPrices()->create(['care_item_id' => $verse->id, 'price' => 9]);
    $center->careCategoryPrices()->create(['care_category_id' => $flat->id, 'price' => 15]);

    $lines = $this->actingAs($manager)->getJson(route('admin.patients.invoices.index', $patient))->json('billable_sessions.0.lines');

    // Session + one flat line for the two zones, nothing for the verse.
    expect($lines)->toHaveCount(2)->and($lines[1]['unit_price'])->toEqual(15);
});

test('a manager narrows the modules of a practitioner on their own center only', function () {
    $center = Center::factory()->create();
    $other = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $practitioner = actingAsPractitionerOf($center, $other);
    $foreignPractitioner = actingAsPractitionerOf($other);
    $otherManager = actingAsManagerOf($center);
    Practitioner::factory()->create(['center_id' => $center->id, 'user_id' => $practitioner->id]);

    $this->actingAs($manager)->put(route('admin.users.center-modules.update', $practitioner), ['modules' => ['patients']])->assertRedirect();

    // Narrowed here, untouched on the practitioner's other center.
    expect($practitioner->enabledModules($center->id))->toBe(['patients'])
        ->and($practitioner->enabledModules($other->id))->toContain('billing')
        ->and($practitioner->refresh()->modules)->toBeNull();

    $this->actingAs($manager)->put(route('admin.users.center-modules.update', $foreignPractitioner), ['modules' => ['patients']])->assertForbidden();
    $this->actingAs($manager)->put(route('admin.users.center-modules.update', $otherManager), ['modules' => ['patients']])->assertForbidden();
    $this->actingAs($practitioner)->put(route('admin.users.center-modules.update', $practitioner), ['modules' => null])->assertForbidden();
    // The account-wide restriction stays with the admins.
    $this->actingAs($manager)->put(route('admin.users.modules.update', $practitioner), ['modules' => ['patients']])->assertForbidden();

    $this->actingAs($manager)->get(route('admin.practitioners.index'))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('practitioners.data.0.account.can_manage_modules', true)
            ->where('practitioners.data.0.account.modules', ['patients']));

    // Lifting it gives the center's modules back.
    $this->actingAs($manager)->put(route('admin.users.center-modules.update', $practitioner), ['modules' => null])->assertRedirect();
    expect($practitioner->enabledModules($center->id))->toContain('billing');
});
