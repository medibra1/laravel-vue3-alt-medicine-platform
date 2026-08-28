<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('local');
});

test('guests are redirected to login', function () {
    $patient = Patient::factory()->create();

    $this->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'other',
        'files' => [UploadedFile::fake()->image('doc.jpg')],
    ])->assertRedirect(route('login'));
});

test('a manager can upload a single document into a collection', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $response = $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'identity',
        'files' => [UploadedFile::fake()->image('id-card.jpg')],
    ]);

    $response->assertRedirect(route('admin.patients.edit', ['patient' => $patient, 'tab' => 'documents']));
    expect($patient->getMedia('identity'))->toHaveCount(1);
});

test('uploading multiple images at once into identity merges them into a single pdf', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    // 800x600, not the fake()->image() default 10x10: Ghostscript (used
    // for the 'thumb' conversion on the merged PDF) fails to rasterize a
    // page built from a near-empty 10x10 JPEG — reproduced in isolation
    // outside Laravel, a Ghostscript quirk unrelated to this feature. A
    // real uploaded photo is never 10x10.
    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'identity',
        'files' => [
            UploadedFile::fake()->image('front.jpg', 800, 600),
            UploadedFile::fake()->image('back.jpg', 800, 600),
        ],
    ])->assertRedirect();

    $media = $patient->getMedia('identity');
    expect($media)->toHaveCount(1);
    expect($media->first()->mime_type)->toBe('application/pdf');
});

test('uploading multiple images at once into medical merges them into a single pdf', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    // See the identity merge test above for why these use 800x600 rather
    // than fake()->image()'s 10x10 default.
    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'medical',
        'files' => [
            UploadedFile::fake()->image('page1.jpg', 800, 600),
            UploadedFile::fake()->image('page2.jpg', 800, 600),
        ],
    ])->assertRedirect();

    expect($patient->getMedia('medical'))->toHaveCount(1);
    expect($patient->getMedia('medical')->first()->mime_type)->toBe('application/pdf');
});

test('uploading multiple images into other does not merge them', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'other',
        'files' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ],
    ])->assertRedirect();

    expect($patient->getMedia('other'))->toHaveCount(2);
});

test('uploading a new identity document replaces the previous one', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'identity',
        'files' => [UploadedFile::fake()->image('old.jpg')],
    ]);
    $firstMediaId = $patient->getFirstMedia('identity')->id;

    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'identity',
        'files' => [UploadedFile::fake()->image('new.jpg')],
    ]);

    expect($patient->getMedia('identity'))->toHaveCount(1);
    expect(Media::query()->find($firstMediaId))->toBeNull();
});

test('a manager from another center cannot upload a document', function () {
    $center = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $manager = actingAsManagerOf($otherCenter);
    $patient = Patient::factory()->for($center, 'center')->create();

    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'other',
        'files' => [UploadedFile::fake()->image('doc.jpg')],
    ])->assertForbidden();

    expect($patient->getMedia('other'))->toHaveCount(0);
});

test('rejects a disallowed file type', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'other',
        'files' => [UploadedFile::fake()->create('malware.exe', 10)],
    ])->assertSessionHasErrors('files.0');
});

test('rejects more than 10 files in a single upload', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();

    $files = collect(range(1, 11))->map(fn ($i) => UploadedFile::fake()->image("doc{$i}.jpg"))->all();

    $this->actingAs($manager)->post(route('admin.patients.documents.store', $patient), [
        'collection' => 'other',
        'files' => $files,
    ])->assertSessionHasErrors('files');
});

test('a manager can delete a document belonging to their center patient', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();
    $patient->addMedia(UploadedFile::fake()->image('doc.jpg'))->toMediaCollection('other');
    $media = $patient->getFirstMedia('other');

    $this->actingAs($manager)
        ->delete(route('admin.patients.documents.destroy', [$patient, $media]))
        ->assertRedirect(route('admin.patients.edit', ['patient' => $patient, 'tab' => 'documents']));

    expect(Media::query()->find($media->id))->toBeNull();
});

test('deleting a media that belongs to a different patient than the url returns 404', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();
    $otherPatient = Patient::factory()->for($center, 'center')->create();
    $otherPatient->addMedia(UploadedFile::fake()->image('doc.jpg'))->toMediaCollection('other');
    $media = $otherPatient->getFirstMedia('other');

    $this->actingAs($manager)
        ->delete(route('admin.patients.documents.destroy', [$patient, $media]))
        ->assertNotFound();

    expect(Media::query()->find($media->id))->not->toBeNull();
});

test('a manager can download a document belonging to their center patient', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    $patient = Patient::factory()->for($center, 'center')->create();
    $patient->addMedia(UploadedFile::fake()->image('doc.jpg'))->toMediaCollection('other');
    $media = $patient->getFirstMedia('other');

    $this->actingAs($manager)
        ->get(route('admin.patients.documents.show', [$patient, $media]))
        ->assertOk();
});

test('a manager from another center cannot view or download a document', function () {
    $center = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $manager = actingAsManagerOf($otherCenter);
    $patient = Patient::factory()->for($center, 'center')->create();
    $patient->addMedia(UploadedFile::fake()->image('doc.jpg'))->toMediaCollection('other');
    $media = $patient->getFirstMedia('other');

    $this->actingAs($manager)
        ->get(route('admin.patients.documents.show', [$patient, $media]))
        ->assertForbidden();
});
