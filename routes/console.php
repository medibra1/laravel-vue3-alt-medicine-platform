<?php

use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:seed-demo', function () {
    $this->call('db:seed', ['--class' => DemoSeeder::class]);
})->purpose('Seed the demo dataset (refused in production)');
