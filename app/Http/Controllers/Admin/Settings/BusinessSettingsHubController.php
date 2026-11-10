<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Utils\Helpers;
use Illuminate\Contracts\View\View;

class BusinessSettingsHubController extends Controller
{
    public function index(): View
    {
        abort_unless(Helpers::module_permission_check('business_settings'), 403);

        return view('admin-views.business-settings.hub');
    }
}
