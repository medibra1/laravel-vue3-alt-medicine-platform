<?php

namespace App\Domains\Practitioners\Observers;

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Services\PractitionerCodeGenerator;

class PractitionerObserver
{
    public function __construct(private readonly PractitionerCodeGenerator $codeGenerator) {}

    public function saving(Practitioner $practitioner): void
    {
        if ($practitioner->isDirty(['center_id', 'diploma_number']) || ! $practitioner->full_code) {
            $practitioner->full_code = $this->codeGenerator->generate($practitioner);
        }
    }
}
