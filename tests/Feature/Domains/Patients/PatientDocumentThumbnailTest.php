<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('uploading a jpg identity document generates a real thumb conversion', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'identity',
        'files' => [UploadedFile::fake()->image('id-card.jpg', 800, 600)],
    ])->assertRedirect();

    $media = $patient->getFirstMedia('identity');
    expect($media->hasGeneratedConversion('thumb'))->toBeTrue();

    $response = $this->actingAs($manager)->get(route('admin.patients.documents.thumb', [$patient, $media]));
    $response->assertOk();
});

/**
 * Dimensions matter here: UploadedFile::fake()->image() defaults to a
 * 10x10 JPEG, which Ghostscript (used by spatie/pdf-to-image under the
 * hood for the 'thumb' conversion) fails to rasterize from inside a PDF
 * ("Page drawing error... Could not draw this page at all") — reproduced
 * in isolation outside Laravel entirely, so it's a Ghostscript quirk with
 * near-empty raster content, not a bug in MergeImagesIntoPdfAction or in
 * this test's setup. A real photo is never 10x10, so 800x600 here is
 * representative of the actual golden path, not a workaround for a real
 * defect.
 */
test('a multi-image upload merged into a pdf still generates a thumb conversion', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'medical',
        'files' => [
            UploadedFile::fake()->image('page1.jpg', 800, 600),
            UploadedFile::fake()->image('page2.jpg', 800, 600),
        ],
    ])->assertRedirect();

    $media = $patient->getMedia('medical')->first();
    expect($media->mime_type)->toBe('application/pdf');
    expect($media->hasGeneratedConversion('thumb'))->toBeTrue();

    $this->actingAs($manager)
        ->get(route('admin.patients.documents.thumb', [$patient, $media]))
        ->assertOk();
});
