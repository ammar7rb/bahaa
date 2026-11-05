<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerWalletWithdrawalService;
use Illuminate\Http\Request;

class WalletWithdrawalController extends Controller
{
    public function index(Request $request, CustomerWalletWithdrawalService $service)
    {
        abort(404);
    }

    public function store(Request $request, CustomerWalletWithdrawalService $service)
    {
        abort(404);
    }
}
