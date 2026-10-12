<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAccountDeletionService
{
    private function invalid(string $message): never { throw ValidationException::withMessages(['deletion' => $message]); }
    private function password(User $user, string $password): void {
        if ($user->account_deleted_at || !Hash::check($password, (string) $user->password)) $this->invalid('كلمة المرور غير صحيحة.');
    }
    public function begin(int $customerId, string $password): string {
        return DB::transaction(function () use ($customerId, $password) {
            $user = User::lockForUpdate()->findOrFail($customerId); $this->password($user, $password);
            DB::table('customer_deletion_flows')->where('customer_id', $customerId)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $token = Str::random(64);
            DB::table('customer_deletion_flows')->insert(['customer_id' => $customerId, 'token_hash' => hash('sha256', $token),
                'password_fingerprint' => hash('sha256', $user->password), 'stage' => 1, 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
            return $token;
        });
    }
    private function flow(User $user, string $token, int $stage): object {
        $flow = DB::table('customer_deletion_flows')->where('customer_id', $user->id)->where('token_hash', hash('sha256', $token))
            ->where('stage', $stage)->whereNull('consumed_at')->where('expires_at', '>', now())->lockForUpdate()->first();
        if (!$flow || !hash_equals($flow->password_fingerprint, hash('sha256', $user->password))) $this->invalid('انتهى تفويض الحذف أو لم تكتمل الخطوات. ابدأ من التحقق بكلمة المرور.');
        return $flow;
    }
    public function reason(int $customerId, string $token, string $reason): void {
        $reason = preg_replace('/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $reason) ?? '';
        if (mb_strlen($reason) < 150 || mb_strlen($reason) > 5000) $this->invalid('سبب الحذف يجب أن يحتوي على 150 حرفًا على الأقل بعد إزالة المسافات الطرفية، وبحد أقصى 5000 حرف.');
        DB::transaction(function () use ($customerId, $token, $reason) {
            $user = User::lockForUpdate()->findOrFail($customerId); $flow = $this->flow($user, $token, 1);
            DB::table('customer_deletion_flows')->where('id', $flow->id)->update(['stage' => 2, 'reason' => $reason, 'updated_at' => now()]);
        });
    }
    public function finish(int $customerId, string $token, string $password): void {
        DB::transaction(function () use ($customerId, $token, $password) {
            $user = User::lockForUpdate()->findOrFail($customerId); $flow = $this->flow($user, $token, 2); $this->password($user, $password);
            if (DB::table('orders')->where('customer_id', $customerId)->where('is_guest', 0)->whereIn('order_status', ['pending','confirmed','processing','out_for_delivery'])->exists()) {
                $this->invalid('لا يمكن حذف الحساب أثناء وجود طلبات جارية. تواصل مع الدعم لإتمامها أولًا.');
            }
            // Retain identity and financial relations. A random password and revoked
            // credentials permanently close access without deleting ledger owners.
            DB::table('users')->where('id', $customerId)->update(['account_deleted_at' => now(), 'is_active' => 0,
                'password' => Hash::make(Str::random(80)), 'remember_token' => null, 'temporary_token' => null, 'cm_firebase_token' => null]);
            $tokens = DB::table('oauth_access_tokens')->where('user_id', $customerId)->pluck('id');
            DB::table('oauth_access_tokens')->whereIn('id', $tokens)->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokens)->update(['revoked' => true]);
            DB::table('customer_deletion_flows')->where('id', $flow->id)->update(['stage' => 3, 'consumed_at' => now(), 'updated_at' => now()]);
        });
    }
}
