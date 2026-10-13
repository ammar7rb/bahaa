<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerProfileAuditService
{
    public const ADDRESS_FIELDS = ['contact_person_name','phone','email','address_type','state','city','district','area','street','building_number','floor','unit_number','company_details','landmark','address','zip','country','is_billing'];

    public function profile(Model $user): void
    {
        $changes = $this->diff($user, ['f_name','l_name','phone','email','image']);
        if ($user->wasChanged('phone') && Schema::hasTable('customer_contact_phones')) {
            $phones = DB::table('customer_contact_phones')->where('customer_id',$user->id)->orderBy('id')->pluck('phone')->all();
            $remaining = array_values(array_filter($phones, fn($phone)=>$phone !== $user->phone));
            if ($phones !== $remaining) {
                DB::table('customer_contact_phones')->where('customer_id',$user->id)->where('phone',$user->phone)->delete();
                $changes['contact_phones']=['from'=>$phones,'to'=>$remaining];
            }
        }
        // Never serialize a password, password hash or credential input into the ledger.
        if ($user->wasChanged('password')) $changes['password'] = ['changed' => true];
        $this->record((int)$user->id, 'profile', (int)$user->id, 'profile_updated', $changes);
    }

    public function address(Model $address, string $action): void
    {
        if ($address->is_guest || !$address->customer_id) return;
        $changes = $action === 'address_updated' ? $this->diff($address, self::ADDRESS_FIELDS) : [];
        if ($action !== 'address_updated') foreach (self::ADDRESS_FIELDS as $field) {
            if ($address->getAttribute($field) !== null) $changes[$field] = [$action === 'address_deleted' ? 'from' : 'to' => $address->getAttribute($field)];
        }
        $this->record((int)$address->customer_id, 'address', (int)$address->id, $action, $changes);
    }

    private function diff(Model $model, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field) if ($model->wasChanged($field)) {
            $changes[$field] = ['from' => $model->getOriginal($field), 'to' => $model->getAttribute($field)];
        }
        return $changes;
    }

    public function record(int $customerId, string $subject, ?int $subjectId, string $action, array $changes): void
    {
        $changes = array_intersect_key($changes, array_flip(array_merge(self::ADDRESS_FIELDS,['f_name','l_name','image','password','contact_phones'])));
        if (array_key_exists('password',$changes)) $changes['password']=['changed'=>true];
        if (!$changes || !Schema::hasTable('customer_profile_changes')) return;
        $customer = request()->is('api/*') ? auth('api')->id() : auth('customer')->id();
        $admin = auth('admin')->id();
        if ((int)$customer !== $customerId && !$admin) return;
        $adminAction = $admin && request()->routeIs('admin.*');
        $actorType = !$adminAction && (int)$customer === $customerId ? 'customer' : 'admin';
        DB::table('customer_profile_changes')->insert(['customer_id' => $customerId,
            'actor_type' => $actorType,
            'actor_id' => $actorType === 'customer' ? $customerId : $admin,
            'subject_type' => $subject, 'subject_id' => $subjectId, 'action' => $action,
            'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
