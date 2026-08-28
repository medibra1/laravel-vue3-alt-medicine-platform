<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Consent;
use App\Domains\Patients\Models\ConsentTemplate;
use App\Domains\Patients\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('guests are redirected to login', function () {
    $patient = Patient::factory()->create();

    $this->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'digital',
        'signer_name' => 'Test',
        'accepted' => true,
    ])->assertRedirect(route('login'));
});

test('a manager can record a digital consent for a patient of their own center', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();
    ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Consentement au traitement',
        'content' => 'Texte.',
        'is_active' => true,
    ]);

    $response = $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'digital',
        'signer_name' => 'Amina Benali',
        'accepted' => true,
    ]);

    $response->assertRedirect(route('admin.patients.edit', ['patient' => $patient, 'tab' => 'consent']));
    expect($patient->consents()->count())->toBe(1);
    expect($patient->consents()->first()->source)->toBe('digital');
});

test('a manager can record an uploaded consent with a scanned file', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $response = $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'uploaded',
        'signer_name' => 'Amina Benali',
        'accepted' => true,
        'accepted_at' => now()->subDay()->toDateString(),
        'files' => [UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf')],
    ]);

    $response->assertRedirect(route('admin.patients.edit', ['patient' => $patient, 'tab' => 'consent']));
    $consent = $patient->consents()->firstOrFail();
    expect($consent->source)->toBe('uploaded');
    expect($consent->consent_template_id)->toBeNull();
    expect($consent->getFirstMedia('document'))->not->toBeNull();
});

test('an uploaded consent requires at least one file', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $response = $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'uploaded',
        'signer_name' => 'Test',
        'accepted' => true,
        'accepted_at' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('files');
});

test('an uploaded consent requires accepted_at and rejects a future date', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $response = $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'uploaded',
        'signer_name' => 'Test',
        'accepted' => true,
        'files' => [UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf')],
    ]);
    $response->assertSessionHasErrors('accepted_at');

    $response = $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'uploaded',
        'signer_name' => 'Test',
        'accepted' => true,
        'accepted_at' => now()->addDay()->toDateString(),
        'files' => [UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf')],
    ]);
    $response->assertSessionHasErrors('accepted_at');
});

test('a manager cannot record a consent for a patient of another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);
    $patient = Patient::factory()->for($otherCenter, 'center')->create();
    ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Consentement au traitement',
        'content' => 'Texte.',
        'is_active' => true,
    ]);

    $response = $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'digital',
        'signer_name' => 'Test',
        'accepted' => true,
    ]);

    $response->assertForbidden();
    expect($patient->consents()->count())->toBe(0);
});

test('recording a digital consent fails when no active template exists for the requested type', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $response = $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'digital',
        'signer_name' => 'Test',
        'accepted' => true,
    ]);

    $response->assertStatus(422);
});

test('the acceptance checkbox is required', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();
    ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Consentement au traitement',
        'content' => 'Texte.',
        'is_active' => true,
    ]);

    $response = $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'digital',
        'signer_name' => 'Test',
    ]);

    $response->assertSessionHasErrors('accepted');
});

test('a manager can download a consent pdf belonging to their own center', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();
    ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Consentement au traitement',
        'content' => 'Texte.',
        'is_active' => true,
    ]);

    $this->actingAs($manager)->post(route('admin.patients.consents.store', $patient), [
        'type' => 'treatment',
        'source' => 'digital',
        'signer_name' => 'Test',
        'accepted' => true,
    ]);
    $consent = $patient->consents()->firstOrFail();

    $response = $this->actingAs($manager)->get(route('admin.patients.consents.show', [$patient, $consent]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
});

test('a consent belonging to another patient returns 404 even with a valid id', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patientA = Patient::factory()->for($center, 'center')->create();
    $patientB = Patient::factory()->for($center, 'center')->create();
    $template = ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Consentement au traitement',
        'content' => 'Texte.',
        'is_active' => true,
    ]);
    $consent = Consent::create([
        'patient_id' => $patientB->id,
        'type' => 'treatment',
        'source' => 'digital',
        'consent_template_id' => $template->id,
        'version' => 1,
        'content_snapshot' => $template->content,
        'signer_name' => 'Test',
        'accepted_at' => now(),
        'accepted_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.patients.consents.show', [$patientA, $consent]));

    $response->assertNotFound();
});
