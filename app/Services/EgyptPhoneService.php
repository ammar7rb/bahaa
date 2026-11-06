<?php

namespace App\Services;

class EgyptPhoneService
{
    /**
     * International adapter for SMS providers and historical integrations.
     * Customer persistence uses normalizeLocal() and the canonical identity.
     */
    public function normalize(mixed $phone): string
    {
        $local = $this->normalizeLocal($phone);
        return preg_match('/^01[0125][0-9]{8}$/', $local) === 1
            ? '+20' . substr($local, 1) : $local;
    }

    public function normalizeLocal(mixed $phone): string
    {
        $value = strtr(trim((string) $phone), array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789')
        ));
        $value = preg_replace('/[\s()\-\x{200e}\x{200f}]/u', '', $value) ?? $value;
        if (str_starts_with($value, '+20')) {
            $value = '0' . substr($value, 3);
        } elseif (str_starts_with($value, '0020')) {
            $value = '0' . substr($value, 4);
        } elseif (str_starts_with($value, '20')) {
            $value = '0' . substr($value, 2);
        }
        return $value;
    }

    public function aliases(mixed $phone): array
    {
        $local = $this->normalizeLocal($phone);
        return $this->isValid($local)
            ? [$local, '+20' . substr($local, 1), '20' . substr($local, 1), '0020' . substr($local, 1)]
            : [$local];
    }

    public function isValid(mixed $phone): bool
    {
        return preg_match('/^01[0125][0-9]{8}$/', $this->normalizeLocal($phone)) === 1;
    }
}
