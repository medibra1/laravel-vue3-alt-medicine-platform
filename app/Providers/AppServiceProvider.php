<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Models live in App\Domains\{Domain}\Models, factories in
        // Database\Factories\Domains\{Domain}\{Model}Factory — Laravel's
        // default App\Models\ <-> Database\Factories\ mapping doesn't apply,
        // so both directions of the guess need overriding.
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            $path = Str::of($modelName)
                ->after('App\\Domains\\')
                ->replace('\\Models\\', '\\');

            return "Database\\Factories\\Domains\\{$path}Factory";
        });

        Factory::guessModelNamesUsing(function (Factory $factory) {
            $segments = Str::of($factory::class)
                ->after('Database\\Factories\\Domains\\')
                ->replaceLast('Factory', '')
                ->explode('\\');

            $model = $segments->pop();
            $domain = $segments->implode('\\');

            return "App\\Domains\\{$domain}\\Models\\{$model}";
        });
    }
}
