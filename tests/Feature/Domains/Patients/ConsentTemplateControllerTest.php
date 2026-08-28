<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\ConsentTemplate;

test('guests are redirected to login', function () {
    $this->get(route('admin.consent-templates.index'))->assertRedirect(route('login'));
});

test('manager cannot access the consent templates list', function () {
    $manager = actingAsManagerOf(Center::factory()->create());

    $response = $this->actingAs($manager)->get(route('admin.consent-templates.index'));

    $response->assertForbidden();
});

test('super admin can list consent templates', function () {
    $superAdmin = actingAsSuperAdmin();
    ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Consentement au traitement',
        'content' => 'Texte.',
        'is_active' => true,
    ]);

    $response = $this->actingAs($superAdmin)->get(route('admin.consent-templates.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['templates'])->toHaveCount(1);
});

test('admin can create the first template of a new type', function () {
    $admin = actingAsAdmin();

    $response = $this->actingAs($admin)->post(route('admin.consent-templates.store'), [
        'type' => 'data_privacy',
        'title' => 'RGPD',
        'content' => 'Texte RGPD.',
    ]);

    $response->assertRedirect(route('admin.consent-templates.index'));
    $template = ConsentTemplate::query()->where('type', 'data_privacy')->firstOrFail();
    expect($template->version)->toBe(1);
    expect($template->is_active)->toBeTrue();
});

test('creating a template for a type that already has an active one fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    ConsentTemplate::create([
        'type' => 'image_rights',
        'version' => 1,
        'title' => 'Droit à l\'image',
        'content' => 'Texte.',
        'is_active' => true,
    ]);

    $response = $this->actingAs($superAdmin)->post(route('admin.consent-templates.store'), [
        'type' => 'image_rights',
        'title' => 'Doublon',
        'content' => 'Autre texte.',
    ]);

    $response->assertSessionHasErrors('type');
    expect(ConsentTemplate::query()->where('type', 'image_rights')->count())->toBe(1);
});

test('manager cannot create a consent template', function () {
    $manager = actingAsManagerOf(Center::factory()->create());

    $response = $this->actingAs($manager)->post(route('admin.consent-templates.store'), [
        'type' => 'treatment',
        'title' => 'Consentement',
        'content' => 'Texte.',
    ]);

    $response->assertForbidden();
    expect(ConsentTemplate::query()->count())->toBe(0);
});

test('updating a template creates a new version instead of editing the existing row', function () {
    $superAdmin = actingAsSuperAdmin();
    $template = ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Ancien titre',
        'content' => 'Ancien texte.',
        'is_active' => true,
    ]);

    $response = $this->actingAs($superAdmin)->put(route('admin.consent-templates.update', $template), [
        'title' => 'Nouveau titre',
        'content' => 'Nouveau texte.',
    ]);

    $response->assertRedirect(route('admin.consent-templates.index'));

    expect($template->fresh()->is_active)->toBeFalse();
    expect($template->fresh()->title)->toBe('Ancien titre');

    $newVersion = ConsentTemplate::query()->where('type', 'treatment')->where('version', 2)->firstOrFail();
    expect($newVersion->is_active)->toBeTrue();
    expect($newVersion->title)->toBe('Nouveau titre');
});

test('manager cannot update a consent template', function () {
    $manager = actingAsManagerOf(Center::factory()->create());
    $template = ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Titre',
        'content' => 'Texte.',
        'is_active' => true,
    ]);

    $response = $this->actingAs($manager)->put(route('admin.consent-templates.update', $template), [
        'title' => 'Nouveau titre',
        'content' => 'Nouveau texte.',
    ]);

    $response->assertForbidden();
    expect($template->fresh()->title)->toBe('Titre');
});
