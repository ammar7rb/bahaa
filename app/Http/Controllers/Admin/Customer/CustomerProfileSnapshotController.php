<?php
namespace App\Http\Controllers\Admin\Customer;
use App\Http\Controllers\Controller;
use App\Models\{User, ShippingAddress};
use App\Services\CustomerContactPhoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};

class CustomerProfileSnapshotController extends Controller
{
    public function index(Request $request, int $customer)
    {
        abort_unless(auth('admin')->check(), 401);
        $data = $request->validate(['page' => 'nullable|integer|min:1|max:100000']);
        $user = User::findOrFail($customer);
        $ready = Schema::hasTable('customer_profile_changes');
        $history = $ready ? DB::table('customer_profile_changes')->where('customer_id',$customer)->latest('id')->paginate(15,['*'],'page',$data['page'] ?? 1) : null;
        return response()->json(['customer' => ['id'=>$user->id,'reference'=>$user->administrative_reference,
            'name'=>trim($user->f_name.' '.$user->l_name),'email'=>$user->email,'phone'=>$user->phone,
            'image_url'=>getStorageImages(path:$user->image_full_url,type:'backend-profile'),'updated_at'=>$user->updated_at,
            'profile_changed_at'=>$ready ? DB::table('customer_profile_changes')->where('customer_id',$customer)->latest('id')->value('created_at') : null,
            'contact_phones'=>app(CustomerContactPhoneService::class)->list($customer)],
            'addresses'=>ShippingAddress::where('customer_id',$customer)->where('is_guest',0)->latest('id')->get(\App\Services\CustomerProfileAuditService::ADDRESS_FIELDS)->map(fn($address)=>$address->getAttributes()),
            'history_ready'=>$ready,'history'=>$history ? $history->getCollection()->map(function($entry){
                $changes=json_decode($entry->changes,true) ?: [];
                if(isset($changes['password'])) $changes['password']=['changed'=>true];
                return ['id'=>$entry->id,'created_at'=>$entry->created_at,'action'=>$entry->action,'actor_type'=>$entry->actor_type,'changes'=>$changes];
            })->values() : [],'current_page'=>$history?->currentPage() ?? 1,'last_page'=>$history?->lastPage() ?? 1])
            ->header('Cache-Control','private, no-store');
    }
}
