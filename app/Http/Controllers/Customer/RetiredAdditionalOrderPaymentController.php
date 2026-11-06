<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;

class RetiredAdditionalOrderPaymentController extends Controller
{
    public function __invoke(): never
    {
        abort(410, 'يُدفع إجمالي الطلب والشحن في دفعة واحدة. لا توجد دفعة إضافية لهذا الطلب.');
    }
}
