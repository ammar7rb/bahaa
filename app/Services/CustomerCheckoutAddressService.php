<?php
namespace App\Services;
use App\Models\ShippingAddress;
use Illuminate\Support\Facades\Validator;

class CustomerCheckoutAddressService
{
    /** Owner is supplied by the authenticated controller, never taken from form data. */
    public function choose(int $customerId, bool $guest, array $input): int
    {
        $id = (int)($input['shipping_method_id'] ?? 0);
        $draftKey = Validator::make($input, ['checkout_draft_key'=>'nullable|uuid'])->validate()['checkout_draft_key'] ?? null;
        $draftId = $draftKey ? (int) session('checkout_address_drafts.'.$draftKey, 0) : 0;
        if (!$id && $draftId) $id = $draftId;
        $existing = $id > 0 ? ShippingAddress::where('customer_id',$customerId)->where('is_guest',$guest)->findOrFail($id) : null;
        $saveNew = ($input['save_address'] ?? '') === 'on';
        $update = ($input['update_address'] ?? '') === 'on';
        if ($draftId && $id === $draftId) { $saveNew = false; $update = true; }
        if ($existing && !$saveNew && !$update) return (int)$existing->id;
        $data=Validator::make($input,[
            'contact_person_name'=>'required|string|max:255','address_type'=>'required|string|max:50','address'=>'required|string|max:2000',
            'phone'=>'required|string|max:20','email'=>'nullable|email|max:255','city'=>'required|string|max:255','state'=>'nullable|string|max:255',
            'district'=>'nullable|string|max:255','area'=>'nullable|string|max:255','street'=>'nullable|string|max:255','building_number'=>'nullable|string|max:100',
            'floor'=>'nullable|string|max:100','unit_number'=>'nullable|string|max:100','company_details'=>'nullable|string|max:500','landmark'=>'nullable|string|max:255','zip'=>'nullable|string|max:20',
        ], [
            'contact_person_name.required' => 'اكتب اسم المستلم.', 'phone.required' => 'أدخل رقم موبايل المستلم.',
            'city.required' => 'أدخل المدينة أو المركز.', 'address.required' => 'أكمل تفاصيل العنوان.',
            'address_type.required' => 'اختر نوع العنوان.',
        ])->validate();
        if (!app(EgyptShippingRateService::class)->isEgyptAddress($input + ['country' => 'Egypt'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['country' => 'عنوان الشحن يجب أن يكون داخل مصر.']);
        }
        $location=app(GoogleMapsShippingLocationService::class);
        $resolution=$location->resolve($data);
        $attributes=array_merge(['customer_id'=>$customerId,'is_guest'=>$guest,'country'=>'Egypt','is_billing'=>0],$data,$location->persistedLocationFields($data,$resolution));
        if($existing && $update && !$saveNew){$existing->update($attributes);return (int)$existing->id;}
        $createdId = (int)ShippingAddress::create($attributes)->id;
        if ($draftKey) session()->put('checkout_address_drafts.'.$draftKey, $createdId);
        return $createdId;
    }
}
