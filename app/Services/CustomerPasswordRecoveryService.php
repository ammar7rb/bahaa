<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class CustomerPasswordRecoveryService {
    public const MESSAGE='تم استلام طلبك. ستراجعه الإدارة وتتحقق من صاحب الحساب قبل إصدار كلمة مرور مؤقتة.';
    public function request(string $identity,?string $conversationToken=null): object {
        $identity=trim($identity);
        $conversationToken??=bin2hex(random_bytes(32));
        if(!preg_match('/^[a-f0-9]{64}$/D',$conversationToken))abort(422);
        $hash=hash('sha256','customer-recovery:'.$conversationToken);
        $phones=app(EgyptPhoneService::class);
        $customer=filter_var($identity,FILTER_VALIDATE_EMAIL) ? User::where('email',$identity)->first() :
            ($phones->isValid($identity)?User::whereIn('phone',$phones->aliases($identity))->first():null);
        if($customer && (!$customer->is_active||$customer->account_deleted_at))$customer=null;
        // Ownership follows the random conversation token, never the supplied account identity.
        return DB::transaction(function()use($customer,$identity,$hash){
            $contact=\App\Models\Contact::firstOrCreate(['mobile_support_token_hash'=>$hash],['sender_type'=>'customer',
                'name'=>'طلب استعادة كلمة مرور','email'=>filter_var($identity,FILTER_VALIDATE_EMAIL)?$identity:'',
                'mobile_number'=>filter_var($identity,FILTER_VALIDATE_EMAIL)?'':$identity,'subject'=>'استعادة كلمة المرور — عميل',
                'message'=>'طلب مراجعة هوية صاحب الحساب وإصدار كلمة مرور مؤقتة.','seen'=>false]);
            $contact=\App\Models\Contact::lockForUpdate()->findOrFail($contact->id);
            if($contact){
                $existing=DB::table('customer_password_recovery_requests')->where('contact_id',$contact->id)->latest('id')->first();
                if($existing && $existing->status==='issued') {
                    $owner=User::find($existing->customer_id);
                    if($owner?->temporary_password_expires_at && now()->gte(\Carbon\Carbon::parse($owner->temporary_password_expires_at))) {
                        DB::table('customer_password_recovery_requests')->where('id',$existing->id)->update(['status'=>'expired','updated_at'=>now()]);$existing->status='expired';
                    }
                }
                if($existing && in_array($existing->status,['pending','verified','issued'],true))return $existing;
            }
            $id=DB::table('customer_password_recovery_requests')->insertGetId(['customer_id'=>$customer?->id,'contact_id'=>$contact->id,'identity'=>$identity,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('contact_conversation_messages')->insert(['contact_id'=>$contact->id,'sender'=>'requester','body'=>'أطلب استعادة كلمة المرور للحساب المسجل: '.$identity,'created_at'=>now(),'updated_at'=>now()]);
            return DB::table('customer_password_recovery_requests')->where('id',$id)->first();
        },3);
    }
    public function verify(int $id,int $adminId,string $note): void {
        if($adminId<=0 || mb_strlen(trim($note))<10)throw ValidationException::withMessages(['verification'=>'سجل التحقق من صاحب الحساب قبل المتابعة.']);
        DB::transaction(function()use($id,$adminId,$note){
            $r=DB::table('customer_password_recovery_requests')->where('id',$id)->lockForUpdate()->first();abort_unless($r,404);
            if($r->status!=='pending')return;
            DB::table('customer_password_recovery_requests')->where('id',$id)->update(['status'=>'verified','verified_by'=>$adminId,'verification_note'=>$note,'verified_at'=>now(),'updated_at'=>now()]);
        });
    }
    public function issue(int $id,int $adminId): void {
        if($adminId<=0)abort(403);
        $channel='support_chat'; // Approved by the user: the originating support conversation.
        DB::transaction(function()use($id,$adminId,$channel){
            $initial=DB::table('customer_password_recovery_requests')->where('id',$id)->first();abort_unless($initial,404);
            $customer=User::lockForUpdate()->findOrFail($initial->customer_id);
            $r=DB::table('customer_password_recovery_requests')->where('id',$id)->lockForUpdate()->first();
            if($r->status==='issued'||$r->status==='completed')return;
            if($customer->must_change_password && $customer->temporary_password_expires_at && now()->lt(\Carbon\Carbon::parse($customer->temporary_password_expires_at)))throw ValidationException::withMessages(['request'=>'توجد كلمة مؤقتة سارية في محادثة أخرى. تابع الطلب الأصلي.']);
            if($r->status!=='verified'||!$r->verified_at||!$customer->is_active||$customer->account_deleted_at)throw ValidationException::withMessages(['request'=>'يجب التحقق من صاحب الحساب النشط قبل إصدار كلمة مؤقتة.']);
            $password=Str::password(16,symbols:false);
            $customer->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60),
                'must_change_password'=>true,'temporary_password_expires_at'=>now()->addHours(24),'password_revision'=>(int)$customer->password_revision+1])->save();
            app(CustomerTemporaryPasswordDeliveryService::class)->send($customer,$password,$r);
            $this->revoke($customer->id);
            DB::table('customer_password_recovery_requests')->where('id',$id)->update(['status'=>'issued','delivery_channel'=>$channel,'issued_at'=>now(),'updated_at'=>now()]);
            app(CustomerProfileAuditService::class)->record($customer->id,'profile',$customer->id,'temporary_password_issued',['password'=>['changed'=>true]]);
        });
    }
    public function change(int $customerId,string $current,string $password): void {
        if(strlen($password)<8||strlen($password)>72)throw ValidationException::withMessages(['password'=>'كلمة المرور يجب أن تكون من 8 إلى 72 حرفًا.']);
        DB::transaction(function()use($customerId,$current,$password){
            $user=User::lockForUpdate()->findOrFail($customerId);
            if(!$user->must_change_password || !$user->is_active || $user->account_deleted_at || !$user->temporary_password_expires_at || now()->gte(\Carbon\Carbon::parse($user->temporary_password_expires_at)))throw ValidationException::withMessages(['password'=>'انتهت صلاحية الكلمة المؤقتة أو تعذر التغيير. اطلب الاستعادة عبر الإدارة.']);
            if(!Hash::check($current,$user->password)||Hash::check($password,$user->password))throw ValidationException::withMessages(['current_password'=>'أدخل الكلمة المؤقتة الصحيحة واختر كلمة مرور جديدة مختلفة.']);
            $user->forceFill(['password'=>Hash::make($password),'must_change_password'=>false,'temporary_password_expires_at'=>null,
                'remember_token'=>Str::random(60),'password_revision'=>(int)$user->password_revision+1])->save();
            $this->revoke($customerId);
            DB::table('customer_password_recovery_requests')->where('customer_id',$customerId)->where('status','issued')->update(['status'=>'completed','completed_at'=>now(),'updated_at'=>now()]);
            app(CustomerProfileAuditService::class)->record($customerId,'profile',$customerId,'temporary_password_changed',['password'=>['changed'=>true]]);
        });
    }
    private function revoke(int $customerId): void {
        if(!Schema::hasTable('oauth_access_tokens'))return;
        $ids=DB::table('oauth_access_tokens')->where('user_id',$customerId)->pluck('id');
        DB::table('oauth_access_tokens')->whereIn('id',$ids)->update(['revoked'=>true]);
        if(Schema::hasTable('oauth_refresh_tokens'))DB::table('oauth_refresh_tokens')->whereIn('access_token_id',$ids)->update(['revoked'=>true]);
    }
}
