<?php

namespace App\Services;

use App\Models\OfflinePaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** One bounded, backwards-compatible transfer field format for vendor payments. */
class VendorOfflinePaymentInputService
{
    public function reference(Request $request, array $information, bool $required = true): ?string
    {
        $reference = $request->input('transfer_reference');
        foreach (['transfer_reference','transaction_reference','transaction_id','reference_number','reference'] as $key) {
            if ($reference !== null) break;
            $reference = $information[$key] ?? null;
        }
        if (!$required && ($reference === null || (is_string($reference) && trim($reference) === ''))) return null;
        if (!is_scalar($reference) || trim((string)$reference) === '' || mb_strlen((string)$reference) > 191) {
            throw ValidationException::withMessages(['transfer_reference'=>'رقم عملية التحويل مطلوب، بحد أقصى ١٩١ حرفًا.']);
        }
        return trim((string)$reference);
    }
    public function information(Request $request, OfflinePaymentMethod $method, bool $requireReference = true): array
    {
        $information = $request->input('method_information');
        if ($information === null) {
            $json = $request->input('method_informations', '{}');
            if (!is_string($json) || strlen($json) > 10000) $this->invalid();
            $information = json_decode($json, true);
        }
        if (!is_array($information) || count($information) > 30) $this->invalid();
        foreach ($information as $key => $value) {
            if (!is_string($key) || mb_strlen($key) > 100 || !is_scalar($value) || mb_strlen((string) $value) > 1000) $this->invalid();
        }
        $canonical = $request->input('transfer_reference', $information['transfer_reference'] ?? null);
        foreach ($method->method_informations ?? [] as $field) {
            $key = $field['customer_input'] ?? null;
            if (!$requireReference && in_array($key,['transfer_reference','transaction_reference','transaction_id','reference_number','reference'],true)) continue;
            if (in_array($key,['transfer_reference','transaction_reference','transaction_id','reference_number','reference'],true)
                && is_scalar($canonical) && trim((string)$canonical) !== '' && mb_strlen((string)$canonical) <= 191) $information[$key] = trim((string)$canonical);
            if ($key && $key !== 'payment_screenshot' && ($field['is_required'] ?? false)
                && (!array_key_exists($key, $information) || trim((string) $information[$key]) === '')) {
                throw ValidationException::withMessages(['method_information' => 'أكمل بيانات التحويل المطلوبة: '.$key]);
            }
        }
        return $information;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['method_information' => 'بيانات التحويل غير صحيحة.']);
    }
}
