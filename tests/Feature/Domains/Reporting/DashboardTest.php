<?php

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Expense;
use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;

test('a manager sees the figures of their center only', function () {
    $center = Center::factory()->create();
    Patient::factory()->count(2)->create(['intake_center_id' => $center->id]);
    Patient::factory()->create(['intake_center_id' => Center::factory()->create()->id]);

    $this->actingAs(actingAsManagerOf($center))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('Dashboard')
            ->where('stats.patients.total', 2)
            ->has('stats.months', 12)
            ->has('stats.patients.series', 12)
            ->has('stats.billing')
            ->missing('stats.centers'));
});

test('a global admin sees every center and can narrow to one', function () {
    $center = Center::factory()->create();
    Patient::factory()->count(2)->create(['intake_center_id' => $center->id]);
    Patient::factory()->create(['intake_center_id' => Center::factory()->create()->id]);
    $admin = actingAsSuperAdmin();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.patients.total', 3)->has('stats.centers', 2));

    $this->actingAs($admin)->get(route('dashboard', ['center_id' => $center->id]))
        ->assertInertia(fn ($page) => $page->where('stats.patients.total', 2)->where('selectedCenterId', $center->id));
});

test('blocks of a disabled module are left out', function () {
    $center = Center::factory()->create(['modules' => ['patients']]);

    $this->actingAs(actingAsManagerOf($center))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('stats.patients')->missing('stats.billing')->missing('stats.appointments'));
});

test('an account attached to no center cannot open the dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertForbidden();
});

test('the dashboard shows late payments, discounts, expenses and the month result to a manager only', function () {
    $center = Center::factory()->create(['currency' => 'EUR']);
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->create(['intake_center_id' => $center->id]);
    $reason = EnumOption::query()->create([
        'enum_type' => 'invoice.discount_reason', 'code' => 'SOCIAL', 'label' => ['fr' => 'Cas social'], 'order' => 1, 'active' => true,
    ]);

    // 100 billed, 20 discount, 30 paid; 40 was due ten days ago.
    $this->actingAs($manager)->postJson(route('admin.patients.invoices.store', $patient), [
        'issued_on' => now()->toDateString(),
        'lines' => [['label' => 'Séance', 'quantity' => 1, 'unit_price' => 100]],
        'discount' => ['amount' => 20, 'reason_option_id' => $reason->id],
        'payment' => ['amount' => 30, 'method' => 'cash'],
        'installments' => [
            ['due_on' => now()->subDays(10)->toDateString(), 'amount' => 40],
            ['due_on' => now()->addDays(20)->toDateString(), 'amount' => 40],
        ],
    ])->assertCreated();

    Expense::query()->create([
        'center_id' => $center->id, 'label' => 'Loyer', 'amount' => 12, 'currency' => 'EUR',
        'spent_on' => now()->toDateString(), 'method' => 'cash',
    ]);

    $this->actingAs($manager)->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('stats.billing.shows_expenses', true)
        ->where('stats.billing.currencies.0.overdue', 10)
        ->where('stats.billing.currencies.0.discounts_this_month', 20)
        ->where('stats.billing.currencies.0.expenses_this_month', 12)
        ->where('stats.billing.currencies.0.net_this_month', 18));

    // A practitioner bills and collects but never sees money going out.
    $this->actingAs(actingAsPractitionerOf($center))->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('stats.billing.shows_expenses', false)
        ->where('stats.billing.currencies.0.overdue', 10)
        ->missing('stats.billing.currencies.0.expenses_this_month')
        ->missing('stats.billing.currencies.0.net_this_month'));
});
