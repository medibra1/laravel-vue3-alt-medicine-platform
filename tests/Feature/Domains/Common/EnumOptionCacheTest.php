<?php

use App\Domains\Common\Models\EnumOption;
use Illuminate\Support\Facades\Cache;

/**
 * Regression: Pest normally runs with CACHE_STORE=array (see phpunit.xml),
 * which never actually serializes anything — so this class of bug was
 * invisible to the whole suite until a real browser hit it against the
 * dev `database` cache driver. config('cache.serializable_classes') was
 * `false` (Laravel 13's secure-by-default value, meaning "no classes
 * allowed"), which makes unserialize() return __PHP_Incomplete_Class for
 * every cached Eloquent object/Collection — cachedByType() looked fine at
 * write time (no exception), only broke on the next read in a fresh PHP
 * process. Forcing the database driver here reproduces that real
 * unserialize() round-trip instead of Pest's in-memory array store.
 */
test('EnumOption::cachedByType survives a real unserialize round-trip via the database cache driver', function () {
    config(['cache.default' => 'database']);
    Cache::forgetDriver('database');

    EnumOption::query()->create([
        'enum_type' => 'test.cache_regression',
        'code' => 'A',
        'label' => ['fr' => 'A', 'en' => 'A'],
        'order' => 1,
        'active' => true,
    ]);

    // First call populates the cache (Cache::rememberForever's callback
    // runs in-process, so it would "work" even if serializable_classes
    // were misconfigured — the bug only shows up on the read below).
    EnumOption::cachedByType('test.cache_regression');

    $cached = EnumOption::cachedByType('test.cache_regression');

    expect($cached)->toHaveCount(1);
    expect($cached->first())->toBeInstanceOf(EnumOption::class);
    expect($cached->first()->code)->toBe('A');
});
