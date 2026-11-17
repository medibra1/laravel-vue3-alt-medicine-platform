<?php

namespace App\Domains\Core\Http\Controllers\Admin;

use App\Domains\Core\Http\Requests\UpdateCenterSettingsRequest;
use App\Domains\Core\Http\Resources\CenterOperatingHoursResource;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Support\Modules;
use App\Domains\Patients\Models\CareCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CenterSettingsController extends Controller
{
    /**
     * Shortcut for a manager: the settings of their active center.
     */
    public function current(Request $request): RedirectResponse
    {
        $centerId = getPermissionsTeamId();
        abort_if($centerId === null, 404);

        return redirect()->route('admin.centers.settings.edit', $centerId);
    }

    public function edit(Request $request, Center $center): Response
    {
        Gate::authorize('manageSettings', $center);

        $prices = $center->careItemPrices()->pluck('price', 'care_item_id');

        return Inertia::render('Admin/Centers/Settings', [
            'center' => [
                'id' => $center->id,
                'name' => $center->name,
                'city' => $center->city,
                'address' => $center->address,
                'phone' => $center->phone,
                'email' => $center->email,
                'currency' => $center->currency,
                'timezone' => $center->timezone,
                'modules' => $center->enabledModules(),
                'settings' => [
                    'session_price_in_person' => (float) $center->setting('session_price_in_person', 0),
                    'session_price_remote' => (float) $center->setting('session_price_remote', 0),
                    'default_session_duration' => (int) $center->setting('default_session_duration', 60),
                    'default_modality' => (string) $center->setting('default_modality', 'in_person'),
                ],
                'operating_hours' => CenterOperatingHoursResource::collection($center->operatingHours),
            ],
            'careCategories' => CareCategory::query()->where('active', true)->with('items')->orderBy('order')->get()
                ->map(fn (CareCategory $category) => [
                    'id' => $category->id,
                    'label' => (string) $category->label,
                    'items' => $category->items->map(fn ($item) => [
                        'id' => $item->id,
                        'label' => (string) $item->label,
                        'price' => isset($prices[$item->id]) ? (float) $prices[$item->id] : null,
                    ])->all(),
                ])->all(),
            'allModules' => Modules::all(),
            'timezones' => timezone_identifiers_list(),
            // Only global admins decide which modules a center gets.
            'canEditModules' => $request->user()->isGlobalAdmin(),
        ]);
    }

    public function update(UpdateCenterSettingsRequest $request, Center $center): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $center, $validated) {
            $attributes = [
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'currency' => strtoupper($validated['currency']),
                'timezone' => $validated['timezone'],
                'settings' => [...($center->settings ?? []), ...$validated['settings']],
            ];

            if ($request->user()->isGlobalAdmin() && array_key_exists('modules', $validated)) {
                $attributes['modules'] = Modules::normalize($validated['modules']);
            }

            $center->update($attributes);

            foreach ($validated['care_prices'] ?? [] as $row) {
                if ($row['price'] === null || $row['price'] === '') {
                    $center->careItemPrices()->where('care_item_id', $row['care_item_id'])->delete();

                    continue;
                }

                $center->careItemPrices()->updateOrCreate(
                    ['care_item_id' => $row['care_item_id']],
                    ['price' => $row['price']],
                );
            }
        });

        return back();
    }
}
