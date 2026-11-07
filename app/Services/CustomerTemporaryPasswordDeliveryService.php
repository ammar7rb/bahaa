<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\{DB,Crypt};
use Illuminate\Validation\ValidationException;
class CustomerTemporaryPasswordDeliveryService {
    public function send(User $customer,string $password,object $request): void {
        if(!$request->contact_id)throw ValidationException::withMessages(['delivery'=>'يلزم ربط الطلب بمحادثة الدعم الأصلية.']);
        DB::table('contact_conversation_messages')->insert(['contact_id'=>$request->contact_id,'sender'=>'admin_temp_password',
            'body'=>Crypt::encryptString(json_encode(['request_id'=>$request->id,'password'=>$password],JSON_THROW_ON_ERROR)),
            'created_at'=>now(),'updated_at'=>now()]);
        \App\Models\Contact::whereKey($request->contact_id)->update(['seen'=>true]);
    }
}
