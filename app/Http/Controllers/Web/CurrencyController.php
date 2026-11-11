<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\CurrencyRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyRepositoryInterface $currencyRepo
    )
    {
    }

    public function changeCurrency(Request $request): JsonResponse
    {
        // Egyptian pounds only: ignore any requested currency and keep the store currency.
        loadCurrency();
        $message = translate('currency_changed_to') . ' ' . (session('system_default_currency_info')['name'] ?? '');
        return response()->json(['message' => $message]);
    }
}
