<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Services\CustomerPasswordRecoveryService;
use Illuminate\Http\Request;
class PasswordRecoveryController extends Controller {
    public function form(){return view('customer-account.password-recovery');}
    public function request(Request $request,CustomerPasswordRecoveryService $service){
        $data=$request->validate(['identity'=>'required|string|max:191']);
        if($request->is('api/*')) {
            $token=$request->header('X-Support-Token','');abort_unless(preg_match('/^[a-f0-9]{64}$/D',$token),401);
        } else {
            $token=$request->session()->get('customer_recovery_token')??bin2hex(random_bytes(32));$request->session()->put('customer_recovery_token',$token);
        }
        $record=$service->request($data['identity'],$token);
        if($request->is('api/*'))return response()->json(['message'=>$service::MESSAGE,'support_required'=>true,'conversation_created'=>true]);
        $ids=$request->session()->get('contact_conversation_ids',[]);$ids[]=(int)$record->contact_id;$request->session()->put('contact_conversation_ids',array_values(array_unique($ids)));
        return redirect()->route('contact.conversation',$record->contact_id)->with('recovery_message',$service::MESSAGE);
    }
    public function status(Request $request){return response()->json(['must_change_password'=>(bool)$request->user('api')->must_change_password]);}
    private function conversationContact(Request $request) {
        $token=$request->header('X-Support-Token','');abort_unless(preg_match('/^[a-f0-9]{64}$/D',$token),401);
        return \App\Models\Contact::where('mobile_support_token_hash',hash('sha256','customer-recovery:'.$token))->where('sender_type','customer')->firstOrFail();
    }
    public function conversation(Request $request) {
        $contact=$this->conversationContact($request);
        $messages=\Illuminate\Support\Facades\DB::table('contact_conversation_messages')->where('contact_id',$contact->id)->orderBy('id')->get()->map(fn($m)=>['id'=>$m->id,'sender'=>$m->sender==='requester'?'requester':'admin','body'=>\App\Services\ContactPasswordResetService::displayBody($m),'created_at'=>$m->created_at]);
        return response()->json(['messages'=>$messages])->header('Cache-Control','private, no-store');
    }
    public function reply(Request $request) {
        $contact=$this->conversationContact($request);$data=$request->validate(['message'=>'required|string|max:5000']);
        \Illuminate\Support\Facades\DB::table('contact_conversation_messages')->insert(['contact_id'=>$contact->id,'sender'=>'requester','body'=>$data['message'],'created_at'=>now(),'updated_at'=>now()]);
        $contact->update(['seen'=>false]);return $this->conversation($request);
    }
    public function changeForm(){return view('customer-account.password-change-required');}
    public function change(Request $request,CustomerPasswordRecoveryService $service){
        $data=$request->validate(['current_password'=>'required|string|max:128','password'=>'required|string|min:8|max:72|confirmed']);
        $api=$request->is('api/*');$user=$api?$request->user('api'):auth('customer')->user();
        $service->change((int)$user->id,$data['current_password'],$data['password']);
        if($api)return response()->json(['message'=>'تم تغيير كلمة المرور. سجّل الدخول بكلمتك الجديدة.','must_change_password'=>false,'login_required'=>true]);
        auth('customer')->logout();$request->session()->invalidate();$request->session()->regenerateToken();
        return redirect()->route('customer.auth.login')->with('recovery_message','تم تغيير كلمة المرور. سجّل الدخول بالكلمة الجديدة.');
    }
}
