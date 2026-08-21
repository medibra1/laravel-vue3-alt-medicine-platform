<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
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

        // This is an Inertia SPA backend, not a JSON:API — no consumer
        // expects the `{"data": [...]}` envelope Resource::collection()
        // adds by default. Left on, every top-level Inertia prop built
        // from a Resource::collection() (not nested inside another
        // Resource's own toArray(), where Laravel already auto-unwraps)
        // would silently wrap, breaking frontend code that expects a
        // plain array — caught in browser verification of the Patients/
        // Treatments Resource refactor (2026-08-20).
        JsonResource::withoutWrapping();

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

        // Same story for policies: App\Domains\{Domain}\Models\{Model} ->
        // App\Domains\{Domain}\Policies\{Model}Policy. Laravel's built-in
        // guesser only handles a bare "\Models\" segment, not one nested
        // this deep under Domains.
        Gate::guessPolicyNamesUsing(function (string $modelName) {
            $path = Str::of($modelName)
                ->after('App\\Domains\\')
                ->replace('\\Models\\', '\\Policies\\');

            return "App\\Domains\\{$path}Policy";
        });
    }
}
