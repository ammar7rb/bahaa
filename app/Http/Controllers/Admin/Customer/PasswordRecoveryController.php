<?php
namespace App\Http\Controllers\Admin\Customer;
use App\Http\Controllers\Controller;
use App\Services\CustomerPasswordRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PasswordRecoveryController extends Controller {
    public function index(Request $request){
        abort_unless(\App\Utils\Helpers::module_permission_check('people'),403);
        $request->validate(['contact_id'=>'nullable|integer|min:1']);
        return view('admin-views.customer.password-recovery',['requests'=>DB::table('customer_password_recovery_requests')->leftJoin('users','users.id','=','customer_password_recovery_requests.customer_id')
            ->select('customer_password_recovery_requests.*','users.f_name','users.phone','users.email')->when($request->filled('contact_id'),fn($q)=>$q->where('contact_id',$request->integer('contact_id')))->orderByDesc('customer_password_recovery_requests.id')->paginate(20)->withQueryString(),
            'channel'=>'support_chat']);
    }
    public function verify(Request $request,int $id,CustomerPasswordRecoveryService $service){
        abort_unless(\App\Utils\Helpers::module_permission_check('people'),403);
        $data=$request->validate(['identity_verified'=>'required|accepted','verification_note'=>'required|string|min:10|max:2000']);
        $record=DB::table('customer_password_recovery_requests')->where('id',$id)->first();abort_unless($record?->customer_id,422);
        $service->verify($id,(int)auth('admin')->id(),$data['verification_note']);return back()->with('recovery_message','تم تسجيل التحقق من صاحب الحساب.');
    }
    public function issue(int $id,CustomerPasswordRecoveryService $service){
        abort_unless(\App\Utils\Helpers::module_permission_check('people'),403);
        $service->issue($id,(int)auth('admin')->id());return back()->with('recovery_message','تم إصدار الكلمة المؤقتة وإرسالها عبر القناة المعتمدة.');
    }
}
