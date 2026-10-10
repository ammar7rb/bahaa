<?php

namespace App\Http\Middleware;

use App\Services\EgyptPhoneService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ValidateEgyptPhoneInputs
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }
        $phones = app(EgyptPhoneService::class);
        foreach (['phone', 'phone_number', 'mobile_number', 'receiver_phone', 'contact_person_phone'] as $field) {
            if (!$request->exists($field) || $request->input($field) === null || $request->input($field) === '') {
                continue;
            }
            if (!is_scalar($request->input($field)) || !$phones->isValid($request->input($field))) {
                throw ValidationException::withMessages([$field => 'أدخل رقم موبايل مصري من 11 رقمًا يبدأ بـ 010 أو 011 أو 012 أو 015']);
            }
            $request->merge([$field => $phones->normalizeLocal($request->input($field))]);
        }
        return $next($request);
    }
}
