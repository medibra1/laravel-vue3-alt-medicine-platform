<?php

use App\Domains\Auth\Models\User;

test('the language choice is saved on the account and applied', function () {
    $user = User::factory()->create(['locale' => 'fr']);

    $this->actingAs($user)->post(route('locale.update'), ['locale' => 'ar'])->assertRedirect();

    expect($user->refresh()->locale)->toBe('ar');

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('locale', 'ar'))
        ->assertSee('dir="rtl"', false);
});

test('a guest language choice is kept in session', function () {
    $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

test('an unsupported language is rejected', function () {
    $this->post(route('locale.update'), ['locale' => 'de'])->assertSessionHasErrors('locale');
});
