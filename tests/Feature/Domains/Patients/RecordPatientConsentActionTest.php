<?php

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\ConsentTemplate;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Services\MergeImagesIntoPdfAction;
use App\Domains\Patients\Services\RecordPatientConsentAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Storage::fake('local');
});

test('recording a digital consent snapshots the active template content and attaches a pdf', function () {
    $patient = Patient::factory()->create(['first_name' => 'Amina', 'last_name' => 'Benali']);
    $template = ConsentTemplate::create([
        'type' => 'treatment',
        'version' => 1,
        'title' => 'Consentement au traitement',
        'content' => 'Je consens à recevoir le traitement proposé.',
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    $consent = (new RecordPatientConsentAction)->digital(
        $patient,
        'treatment',
        ['signer_name' => 'Amina Benali', 'signature_svg' => null],
        $user,
        '127.0.0.1',
    );

    expect($consent->type)->toBe('treatment');
    expect($consent->source)->toBe('digital');
    expect($consent->consent_template_id)->toBe($template->id);
    expect($consent->version)->toBe(1);
    expect($consent->content_snapshot)->toBe($template->content);
    expect($consent->accepted_by)->toBe($user->id);
    expect($consent->ip_address)->toBe('127.0.0.1');

    $media = $consent->getFirstMedia('document');
    expect($media)->not->toBeNull();
    expect($media->mime_type)->toBe('application/pdf');
});

test('a later template edit never changes an already-recorded digital snapshot', function () {
    $patient = Patient::factory()->create();
    $template = ConsentTemplate::create([
        'type' => 'data_privacy',
        'version' => 1,
        'title' => 'RGPD',
        'content' => 'Texte original.',
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    $consent = (new RecordPatientConsentAction)->digital(
        $patient,
        'data_privacy',
        ['signer_name' => 'Test', 'signature_svg' => null],
        $user,
        null,
    );

    $template->update(['content' => 'Texte modifié après coup.']);

    expect($consent->fresh()->content_snapshot)->toBe('Texte original.');
});

test('an inactive template is never selected for a digital consent', function () {
    $patient = Patient::factory()->create();
    ConsentTemplate::create([
        'type' => 'image_rights',
        'version' => 1,
        'title' => 'Droit à l\'image',
        'content' => 'Ancien texte.',
        'is_active' => false,
    ]);
    $user = User::factory()->create();

    (new RecordPatientConsentAction)->digital(
        $patient,
        'image_rights',
        ['signer_name' => 'Test', 'signature_svg' => null],
        $user,
        null,
    );
})->throws(HttpException::class);

test('recording an uploaded consent has no template and attaches the uploaded file as-is', function () {
    $patient = Patient::factory()->create();
    $user = User::factory()->create();
    $acceptedAt = now()->subDays(3);

    $consent = (new RecordPatientConsentAction)->uploaded(
        $patient,
        'treatment',
        ['signer_name' => 'Test', 'signature_svg' => null, 'accepted_at' => $acceptedAt],
        [UploadedFile::fake()->image('signed.jpg', 800, 600)],
        $user,
        '127.0.0.1',
        new MergeImagesIntoPdfAction,
    );

    expect($consent->type)->toBe('treatment');
    expect($consent->source)->toBe('uploaded');
    expect($consent->consent_template_id)->toBeNull();
    expect($consent->version)->toBeNull();
    expect($consent->content_snapshot)->toBeNull();
    expect($consent->accepted_at->isSameDay($acceptedAt))->toBeTrue();

    $media = $consent->getFirstMedia('document');
    expect($media)->not->toBeNull();
    expect($media->mime_type)->toBe('image/jpeg');
});

test('uploading multiple photos for an uploaded consent merges them into a single pdf', function () {
    $patient = Patient::factory()->create();
    $user = User::factory()->create();

    $consent = (new RecordPatientConsentAction)->uploaded(
        $patient,
        'treatment',
        ['signer_name' => 'Test', 'signature_svg' => null, 'accepted_at' => now()],
        [
            UploadedFile::fake()->image('front.jpg', 800, 600),
            UploadedFile::fake()->image('back.jpg', 800, 600),
        ],
        $user,
        null,
        new MergeImagesIntoPdfAction,
    );

    $media = $consent->getMedia('document');
    expect($media)->toHaveCount(1);
    expect($media->first()->mime_type)->toBe('application/pdf');
});
