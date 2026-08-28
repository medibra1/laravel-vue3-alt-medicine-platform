<?php

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\ConsentTemplate;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Services\RecordPatientConsentAction;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Storage::fake('local');
});

test('recording a consent snapshots the active template content and attaches a pdf', function () {
    $patient = Patient::factory()->create(['first_name' => 'Amina', 'last_name' => 'Benali']);
    $template = ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Consentement au traitement',
        'content' => 'Je consens à recevoir le traitement proposé.',
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    $consent = (new RecordPatientConsentAction)(
        $patient,
        'treatment',
        ['signer_name' => 'Amina Benali', 'signature_svg' => null],
        $user,
        '127.0.0.1',
    );

    expect($consent->consent_template_id)->toBe($template->id);
    expect($consent->version)->toBe(1);
    expect($consent->content_snapshot)->toBe($template->content);
    expect($consent->accepted_by)->toBe($user->id);
    expect($consent->ip_address)->toBe('127.0.0.1');

    $media = $consent->getFirstMedia('document');
    expect($media)->not->toBeNull();
    expect($media->mime_type)->toBe('application/pdf');
});

test('a later template edit never changes an already-recorded snapshot', function () {
    $patient = Patient::factory()->create();
    $template = ConsentTemplate::create([
        'type' => 'data_privacy',
        'version' => 1,
        'title' => 'RGPD',
        'content' => 'Texte original.',
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    $consent = (new RecordPatientConsentAction)(
        $patient,
        'data_privacy',
        ['signer_name' => 'Test', 'signature_svg' => null],
        $user,
        null,
    );

    $template->update(['content' => 'Texte modifié après coup.']);

    expect($consent->fresh()->content_snapshot)->toBe('Texte original.');
});

test('an inactive template is never selected', function () {
    $patient = Patient::factory()->create();
    ConsentTemplate::create([
        'type' => 'image_rights',
        'version' => 1,
        'title' => 'Droit à l\'image',
        'content' => 'Ancien texte.',
        'is_active' => false,
    ]);
    $user = User::factory()->create();

    (new RecordPatientConsentAction)(
        $patient,
        'image_rights',
        ['signer_name' => 'Test', 'signature_svg' => null],
        $user,
        null,
    );
})->throws(HttpException::class);
