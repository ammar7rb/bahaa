<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Services\CustomerContactPhoneService;
use Illuminate\Http\Request;

class ContactPhoneController extends Controller
{
    public function index(Request $r, CustomerContactPhoneService $service) {
        $id = (int)$r->user('api')->id;
        return response()->json(['primary_phone' => $r->user('api')->phone, 'phones' => $service->list($id)])->header('Cache-Control','private, no-store');
    }
    public function update(Request $r, CustomerContactPhoneService $service) {
        if ($r->exists('phones') && ($r->input('phones') === null || $r->input('phones') === '')) $r->merge(['phones'=>[]]);
        $data = $r->validate(['phones' => 'present|array|max:20']);
        $id = (int)($r->is('api/*') ? $r->user('api')->id : auth('customer')->id());
        $phones = $service->replace($id, $data['phones']);
        return $r->is('api/*') ? response()->json(['phones' => $phones]) : back()->with('contact_phones_message','تم حفظ أرقام التواصل.');
    }
}
