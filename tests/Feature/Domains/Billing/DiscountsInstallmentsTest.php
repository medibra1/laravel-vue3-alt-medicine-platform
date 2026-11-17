<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;
use App\Domains\Patients\Models\Patient;

function discountReason(): EnumOption
{
    return EnumOption::query()->firstOrCreate(
        ['enum_type' => Invoice::DISCOUNT_REASON_TYPE, 'code' => 'SOCIAL'],
        ['label' => ['fr' => 'Cas social'], 'order' => 1, 'active' => true],
    );
}

function invoiceOf(Center $center, $user, array $overrides = [], ?Patient $patient = null)
{
    $patient ??= Patient::factory()->create(['intake_center_id' => $center->id]);

    return test()->actingAs($user)->postJson(route('admin.patients.invoices.store', $patient), [
        'issued_on' => now()->toDateString(),
        'lines' => [['label' => 'Séance', 'quantity' => 1, 'unit_price' => 90]],
        ...$overrides,
    ]);
}

test('the catalog lists the center services with their prices, by pricing mode', function () {
    $center = Center::factory()->create(['settings' => ['session_price_in_person' => 40, 'session_price_remote' => 0]]);
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $flat = CareCategory::factory()->create(['pricing_mode' => CareCategory::PRICING_CATEGORY]);
    $free = CareCategory::factory()->create(['pricing_mode' => CareCategory::PRICING_NONE]);
    $priced = CareItem::factory()->create();
    $unpriced = CareItem::factory()->create();
    $verse = CareItem::factory()->create(['care_category_id' => $free->id]);
    $center->careCategoryPrices()->create(['care_category_id' => $flat->id, 'price' => 15]);
    $center->careItemPrices()->create(['care_item_id' => $priced->id, 'price' => 8]);
    $center->careItemPrices()->create(['care_item_id' => $verse->id, 'price' => 5]);

    $catalog = collect($this->actingAs($manager)->getJson(route('admin.patients.invoices.index', $patient))->json('catalog'));

    // In-person session, flat category, priced item — no remote session
    // (no price), no unpriced item, nothing from a never-billed category.
    expect($catalog->pluck('key')->all())->toEqualCanonicalizing(['session_price_in_person', "category-{$flat->id}", "item-{$priced->id}"])
        ->and($catalog->firstWhere('key', "category-{$flat->id}")['care_category_id'])->toBe($flat->id)
        ->and($catalog->pluck('care_item_id')->contains($unpriced->id))->toBeFalse();
});

test('an invoice keeps a description per line, for catalog and free lines alike', function () {
    $center = Center::factory()->create();
    $item = CareItem::factory()->create();

    invoiceOf($center, actingAsManagerOf($center), ['lines' => [
        ['label' => 'Tisane', 'description' => 'Cure de 10 jours', 'quantity' => 1, 'unit_price' => 8, 'care_item_id' => $item->id],
        ['label' => 'Frais de déplacement', 'quantity' => 1, 'unit_price' => 12],
    ]])->assertCreated()
        ->assertJsonPath('lines.0.description', 'Cure de 10 jours')
        ->assertJsonPath('lines.1.description', null)
        ->assertJsonPath('total', 20);
});

test('a deposit can be taken when the invoice is created', function () {
    $center = Center::factory()->create();

    invoiceOf($center, actingAsManagerOf($center), ['payment' => ['amount' => 30, 'method' => 'cash']])
        ->assertCreated()->assertJsonPath('status', 'partial')->assertJsonPath('balance', 60);
});

test('a discount lowers the net to pay and needs a reason', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $reason = discountReason();

    invoiceOf($center, $manager, ['discount' => ['percent' => 50]])->assertUnprocessable();
    invoiceOf($center, $manager, ['discount' => ['amount' => 91, 'reason_option_id' => $reason->id]])->assertUnprocessable();

    invoiceOf($center, $manager, ['discount' => ['percent' => 50, 'reason_option_id' => $reason->id]])->assertCreated()
        ->assertJsonPath('subtotal', 90)->assertJsonPath('discount_amount', 45)->assertJsonPath('total', 45)
        ->assertJsonPath('discount_reason', 'Cas social')->assertJsonPath('status', 'unpaid');

    invoiceOf($center, $manager, ['discount' => ['amount' => 20, 'reason_option_id' => $reason->id]])->assertCreated()
        ->assertJsonPath('total', 70);
});

test('a fully discounted invoice is offered: nothing to collect', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);

    $id = invoiceOf($center, $manager, ['discount' => ['percent' => 100, 'reason_option_id' => discountReason()->id]])
        ->assertCreated()->assertJsonPath('status', 'offered')->assertJsonPath('balance', 0)->json('id');

    $this->actingAs($manager)->postJson(route('admin.invoices.payments.store', $id), [
        'amount' => 10, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertUnprocessable();

    $this->actingAs($manager)->get(route('admin.billing.index', ['status' => 'offered']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('invoices.data', 1)->where('summary.unpaid_count', 0));
});

test('a practitioner bills with the patient standing discount and cannot change it', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $practitioner = actingAsPractitionerOf($center);
    $reason = discountReason();
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);

    $this->actingAs($practitioner)->putJson(route('admin.patients.billing-discount.update', $patient), [
        'percent' => 100, 'reason_option_id' => $reason->id,
    ])->assertForbidden();

    // No standing discount: whatever the practitioner sends is ignored.
    invoiceOf($center, $practitioner, ['discount' => ['percent' => 100, 'reason_option_id' => $reason->id]], $patient)
        ->assertCreated()->assertJsonPath('total', 90);

    $this->actingAs($manager)->putJson(route('admin.patients.billing-discount.update', $patient), [
        'percent' => 30, 'reason_option_id' => $reason->id,
    ])->assertOk();

    invoiceOf($center, $practitioner, ['discount' => ['percent' => 0]], $patient)
        ->assertCreated()->assertJsonPath('discount_percent', 30)->assertJsonPath('total', 63);

    $this->actingAs($manager)->getJson(route('admin.patients.invoices.index', $patient))
        ->assertJsonPath('patient_discount.percent', 30);
});

test('a schedule must add up to the net total with dates in order', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $day = fn (int $offset) => now()->addDays($offset)->toDateString();

    // Does not add up: the invoice itself is not created either.
    invoiceOf($center, $manager, ['installments' => [['due_on' => $day(1), 'amount' => 30], ['due_on' => $day(30), 'amount' => 30]]])
        ->assertUnprocessable();
    expect(Invoice::query()->count())->toBe(0);

    invoiceOf($center, $manager, ['installments' => [['due_on' => $day(30), 'amount' => 45], ['due_on' => $day(1), 'amount' => 45]]])
        ->assertUnprocessable();
    invoiceOf($center, $manager, ['installments' => [['due_on' => $day(1), 'amount' => 90]]])->assertUnprocessable();

    // Against the NET total, discount included.
    invoiceOf($center, $manager, [
        'discount' => ['amount' => 30, 'reason_option_id' => discountReason()->id],
        'installments' => [['due_on' => $day(1), 'amount' => 30], ['due_on' => $day(30), 'amount' => 30]],
    ])->assertCreated()->assertJsonCount(2, 'installments')->assertJsonPath('number', 'F-'.now()->year.'-00001');
});

test('payments settle the oldest instalment first and a passed one is overdue', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);

    $id = invoiceOf($center, $manager)->json('id');

    $this->actingAs($manager)->putJson(route('admin.invoices.installments.update', $id), ['installments' => [
        ['due_on' => now()->subDays(20)->toDateString(), 'amount' => 30],
        ['due_on' => now()->subDays(5)->toDateString(), 'amount' => 30],
        ['due_on' => now()->addDays(20)->toDateString(), 'amount' => 30],
    ]])->assertOk()->assertJsonPath('overdue_amount', 60);

    $this->actingAs($manager)->postJson(route('admin.invoices.payments.store', $id), [
        'amount' => 40, 'method' => 'cash', 'paid_on' => now()->toDateString(),
    ])->assertCreated()
        ->assertJsonPath('installments.0.status', 'paid')
        ->assertJsonPath('installments.1.status', 'overdue')
        ->assertJsonPath('installments.1.paid', 10)
        ->assertJsonPath('installments.2.status', 'upcoming')
        ->assertJsonPath('overdue_amount', 20);

    $this->actingAs($manager)->get(route('admin.billing.index', ['status' => 'overdue']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('invoices.data', 1)
            ->where('invoices.data.0.overdue_amount', 20)
            ->where('summary.overdue.0.amount', 20));

    // Removing the schedule: nothing is late any more.
    $this->actingAs($manager)->putJson(route('admin.invoices.installments.update', $id), ['installments' => []])
        ->assertOk()->assertJsonPath('overdue_amount', 0);
});

test('a credit note shrinks the last instalments first', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $id = invoiceOf($center, $manager)->json('id');

    $this->actingAs($manager)->putJson(route('admin.invoices.installments.update', $id), ['installments' => [
        ['due_on' => now()->addDays(10)->toDateString(), 'amount' => 30],
        ['due_on' => now()->addDays(40)->toDateString(), 'amount' => 30],
        ['due_on' => now()->addDays(70)->toDateString(), 'amount' => 30],
    ]])->assertOk();

    $this->actingAs($manager)->postJson(route('admin.invoices.credit-notes.store', $id), [
        'amount' => 40, 'issued_on' => now()->toDateString(), 'reason' => 'Séance annulée',
    ])->assertCreated()
        ->assertJsonCount(2, 'installments')
        ->assertJsonPath('installments.0.amount', 30)
        ->assertJsonPath('installments.1.amount', 20);
});

test('a practitioner cannot schedule, and a manager only on their center', function () {
    $center = Center::factory()->create();
    $other = Center::factory()->create();
    $id = invoiceOf($center, actingAsManagerOf($center))->json('id');
    $rows = ['installments' => [
        ['due_on' => now()->addDays(1)->toDateString(), 'amount' => 45],
        ['due_on' => now()->addDays(30)->toDateString(), 'amount' => 45],
    ]];

    $this->actingAs(actingAsPractitionerOf($center))->putJson(route('admin.invoices.installments.update', $id), $rows)->assertForbidden();
    $this->actingAs(actingAsManagerOf($other))->putJson(route('admin.invoices.installments.update', $id), $rows)->assertForbidden();
    invoiceOf($center, actingAsPractitionerOf($center), $rows)->assertForbidden();
});
