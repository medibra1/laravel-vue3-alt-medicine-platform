<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterClosure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The center comes from the URL, never from the body — authorize() then
 * checks it against the user's managed/active center via the policy.
 */
class StoreCenterClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $center = $this->route('center');

        return $center instanceof Center && $this->user()->can('create', [CenterClosure::class, $center]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
