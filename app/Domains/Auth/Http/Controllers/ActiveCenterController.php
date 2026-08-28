<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lets a multi-center practitioner switch their active center — see
 * EnsureCenterAccess, which reads session('active_center_id') back on
 * every subsequent request.
 */
class ActiveCenterController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $centerId = $request->integer('center_id');

        if (! in_array($centerId, $user->accessibleCenterIds(), true)) {
            throw ValidationException::withMessages([
                'center_id' => __('Vous n\'avez pas accès à ce centre.'),
            ]);
        }

        $request->session()->put('active_center_id', $centerId);

        return back();
    }
}
