<?php

namespace App\Services;

class AdministrativeReferenceService
{
    public static function parse(?string $value): ?array
    {
        $value = strtoupper(trim((string) $value));
        if (!preg_match('/^([CMV])\s*[-#]?\s*(\d+)$/', $value, $matches)) {
            return null;
        }

        return [
            'type' => $matches[1] === 'C' ? 'users' : 'sellers',
            'id' => (int) $matches[2],
        ];
    }

    public static function format(string $subjectType, int $id): string
    {
        return match ($subjectType) {
            'customer', 'users' => 'C' . $id,
            'seller', 'sellers', 'vendor' => 'M' . $id,
            'product' => 'P' . $id,
            'invoice' => 'IV' . $id,
            'customer_balance_deposit' => 'ADC' . $id,
            'customer_insurance_deposit' => 'AIC' . $id,
            'seller_balance_deposit' => 'ADM' . $id,
            'seller_insurance_deposit' => 'AIM' . $id,
            'seller_payment' => 'MP' . $id,
            'seller_insurance_payment' => 'MIP' . $id,
            'customer_insurance_payment' => 'CIP' . $id,
            default => '#' . $id,
        };
    }

    public static function parseDeposit(?string $value): ?array
    {
        if (!preg_match('/^(ADC|AIC|ADM|AIM)\s*[-#]?\s*(\d+)$/i', trim((string) $value), $matches)) return null;
        $prefix = strtoupper($matches[1]);
        return ['table' => in_array($prefix, ['ADC', 'AIC']) ? 'customer_balance_deposits' : 'seller_balance_deposits',
            'id' => (int) $matches[2], 'wallet' => in_array($prefix, ['AIC', 'AIM']) ? 'insurance' : 'purchase'];
    }
}
