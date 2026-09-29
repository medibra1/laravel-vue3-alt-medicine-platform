<?php

use App\Domains\Core\Models\Center;
use App\Domains\Core\Services\SyncCenterOperatingHoursAction;
use Illuminate\Database\QueryException;

test('replaces all opening hours of the center', function () {
    $center = Center::factory()->create();
    $center->operatingHours()->create(['day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '11:00']);

    app(SyncCenterOperatingHoursAction::class)->handle($center, [
        ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
        ['day_of_week' => 1, 'start_time' => '14:00', 'end_time' => '18:00'],
    ]);

    expect($center->operatingHours()->pluck('day_of_week')->all())->toBe([1, 1]);
});

test('an empty slot list closes the center every day', function () {
    $center = Center::factory()->create();
    $center->operatingHours()->create(['day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '11:00']);

    app(SyncCenterOperatingHoursAction::class)->handle($center, []);

    expect($center->operatingHours()->count())->toBe(0);
});

test('a failure mid-sync rolls back to the previous hours', function () {
    $center = Center::factory()->create();
    $center->operatingHours()->create(['day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '11:00']);

    expect(fn () => app(SyncCenterOperatingHoursAction::class)->handle($center, [
        ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => null],
    ]))->toThrow(QueryException::class);

    expect($center->operatingHours()->pluck('day_of_week')->all())->toBe([5]);
});
