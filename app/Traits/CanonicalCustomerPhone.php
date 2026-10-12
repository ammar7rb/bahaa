<?php

namespace App\Traits;

trait CanonicalCustomerPhone
{
    public function setPhoneAttribute($value): void
    {
        $phones = app(\App\Services\EgyptPhoneService::class);
        if ($value !== null && $value !== '' && $phones->isValid($value)) {
            $local = $phones->normalizeLocal($value);
            $this->attributes['phone'] = $local;
            $this->attributes['canonical_phone'] = $local;
        } else {
            // Historical invalid numbers are audited, never guessed.
            $this->attributes['phone'] = $value;
        }
    }
}
