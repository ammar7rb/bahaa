<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\BusinessPage;

class LegalContentController extends Controller
{
    public function index()
    {
        return view('admin-views.business-settings.legal', [
            'pages' => BusinessPage::query()->whereIn('slug', ['terms-and-conditions', 'privacy-policy'])
                ->get()->keyBy('slug'),
        ]);
    }
}
