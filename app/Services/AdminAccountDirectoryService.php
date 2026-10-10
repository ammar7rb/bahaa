<?php

namespace App\Services;

use App\Models\{Seller, User};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Database\Query\Builder;

/** A directory projection only: display references never replace stored IDs. */
class AdminAccountDirectoryService
{
    public function query(array $filters): Builder
    {
        $customers = DB::table('users')->where(fn ($q) => $q->whereNull('email')->orWhere('email','!=','walking@customer.com'))
            ->selectRaw("id, 'customer' as kind, CASE WHEN COALESCE(f_name,'')='' AND COALESCE(l_name,'')='' THEN name ELSE f_name END as f_name, l_name, email, phone, NULL as store_name, CASE WHEN is_active=1 THEN 'active' ELSE 'frozen' END as account_status, created_at");
        $vendors = DB::table('sellers')->selectRaw("sellers.id, 'vendor' as kind, f_name, l_name, email, phone, (SELECT name FROM shops WHERE shops.seller_id=sellers.id ORDER BY shops.id LIMIT 1) as store_name, CASE status WHEN 'approved' THEN 'active' WHEN 'suspended' THEN 'frozen' ELSE status END as account_status, sellers.created_at");
        $q = DB::query()->fromSub($customers->unionAll($vendors), 'accounts');
        $customerOrders=DB::table('orders')->where('is_guest',0)->selectRaw("customer_id as account_id, 'customer' as kind, COUNT(*) as orders_count, COALESCE(SUM(order_amount),0) as order_amount")->groupBy('customer_id');
        $vendorOrders=DB::table('orders')->where('seller_is','seller')->selectRaw("seller_id as account_id, 'vendor' as kind, COUNT(*) as orders_count, COALESCE(SUM(order_amount),0) as order_amount")->groupBy('seller_id');
        $q->leftJoinSub($customerOrders->unionAll($vendorOrders),'activity',fn($join)=>$join->on('activity.account_id','=','accounts.id')->on('activity.kind','=','accounts.kind'))
            ->select('accounts.*')->selectRaw('COALESCE(activity.orders_count,0) as orders_count, COALESCE(activity.order_amount,0) as order_amount');
        if (!empty($filters['type'])) $q->where('accounts.kind',$filters['type']);
        if (!empty($filters['status'])) $q->where('account_status',$filters['status']);
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $reference = AdministrativeReferenceService::parse($search);
            if ($reference) $q->where('accounts.id',$reference['id'])->where('accounts.kind',$reference['type']==='users'?'customer':'vendor');
            else $q->where(function ($q) use ($search) {
                $like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
                foreach (['f_name','l_name','store_name','email','phone'] as $column) $q->orWhere($column,'like',$like);
                $fullName=DB::getDriverName()==='sqlite' ? "TRIM(COALESCE(f_name,'') || ' ' || COALESCE(l_name,''))" : "TRIM(CONCAT(COALESCE(f_name,''),' ',COALESCE(l_name,'')))";
                $q->orWhereRaw($fullName.' LIKE ?',[$like]);
                $digits=preg_replace('/\D/u','',strtr($search,['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
                if (strlen($digits)>=4) {
                    $normalized=app(EgyptPhoneService::class)->normalize($digits);
                    $local=ltrim(str_starts_with($normalized,'+20')?substr($normalized,3):$digits,'0');
                    if (strlen($local)>=4) {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+',''),'(',''),')','') LIKE ?",['%'.$local.'%']);
                        if (Schema::hasTable('vendor_account_profiles')) $q->orWhere(function ($q) use ($local) {
                            $q->where('accounts.kind','vendor')->whereExists(fn($p)=>$p->selectRaw('1')->from('vendor_account_profiles as vp')->whereColumn('vp.seller_id','accounts.id')->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(vp.phone_numbers,' ',''),'-',''),'+',''),'(',''),')','') LIKE ?",['%'.$local.'%']));
                        });
                        $q->orWhere(function ($q) use ($local) {
                            $q->where('accounts.kind','vendor')->whereExists(fn($s)=>$s->selectRaw('1')->from('shops as contact_shop')->whereColumn('contact_shop.seller_id','accounts.id')->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_shop.contact,' ',''),'-',''),'+',''),'(',''),')','') LIKE ?",['%'.$local.'%']));
                        });
                    }
                }
            });
        }
        foreach (['joined_from'=>'>=','joined_to'=>'<='] as $key=>$operator) if (!empty($filters[$key])) $q->where('accounts.created_at',$operator,$filters[$key].($operator==='>='?' 00:00:00':' 23:59:59.999999'));
        if (!empty($filters['ordered_from']) || !empty($filters['ordered_to'])) {
            $q->whereExists(function($orders) use ($filters) {
                $orders->selectRaw('1')->from('orders as dated_orders')->where(function($match) {
                    $match->where(fn($customer)=>$customer->where('accounts.kind','customer')->whereColumn('dated_orders.customer_id','accounts.id')->where('dated_orders.is_guest',0))
                        ->orWhere(fn($vendor)=>$vendor->where('accounts.kind','vendor')->whereColumn('dated_orders.seller_id','accounts.id')->where('dated_orders.seller_is','seller'));
                });
                if (!empty($filters['ordered_from'])) $orders->where('dated_orders.created_at','>=',$filters['ordered_from'].' 00:00:00');
                if (!empty($filters['ordered_to'])) $orders->where('dated_orders.created_at','<=',$filters['ordered_to'].' 23:59:59.999999');
            });
        }
        $column=['name'=>'f_name','identifier'=>'id','status'=>'account_status','created_at'=>'created_at','orders_count'=>'orders_count','order_amount'=>'order_amount'][$filters['sort'] ?? 'created_at'] ?? 'created_at';
        $direction=($filters['direction'] ?? 'desc')==='asc'?'asc':'desc';
        $q->orderBy($column,$direction)->orderBy('accounts.kind')->orderBy('accounts.id');
        // Limit the matching set before pagination, so its count and exports agree.
        if (!empty($filters['first'])) return DB::query()->fromSub($q->limit((int)$filters['first']),'selected_accounts')->orderBy($column,$direction)->orderBy('kind')->orderBy('id');
        return $q;
    }

    public function contacts($accounts): array
    {
        $result=[];
        $ids=$accounts->where('kind','vendor')->pluck('id');
        $profiles=Schema::hasTable('vendor_account_profiles') ? DB::table('vendor_account_profiles')->whereIn('seller_id',$ids)->get()->keyBy('seller_id') : collect();
        $shops=DB::table('shops')->whereIn('seller_id',$ids)->get(['seller_id','contact'])->groupBy('seller_id');
        foreach ($accounts as $account) {
            $values=[];
            if ($account->kind==='vendor') {
                $phones=json_decode($profiles->get($account->id)->phone_numbers ?? '[]',true) ?: [];
                foreach ($phones as $phone) {
                    $value=is_array($phone)?($phone['phone'] ?? $phone['number'] ?? ''):$phone;
                    if (is_string($value) && trim($value)!=='') $values[]=trim($value);
                }
                foreach ($shops->get($account->id,collect()) as $shop) if ($shop->contact) $values[]=trim($shop->contact);
            }
            $primary=app(EgyptPhoneService::class)->normalize($account->phone);
            $result[$account->kind.':'.$account->id]=array_values(array_filter(array_unique($values),fn($value)=>filter_var($value,FILTER_VALIDATE_EMAIL) ? strcasecmp($value,$account->email ?? '')!==0 : app(EgyptPhoneService::class)->normalize($value)!==$primary));
        }
        return $result;
    }

    public function account(string $kind, int $id, bool $lock=false): User|Seller
    {
        abort_unless(in_array($kind,['customer','vendor'],true),404);
        $q=$kind==='customer'?User::query():Seller::query();
        if ($kind==='customer') $q->where(fn($q)=>$q->whereNull('email')->orWhere('email','!=','walking@customer.com'));
        return ($lock?$q->lockForUpdate():$q)->findOrFail($id);
    }

    /** Retain any related history; only empty profile-owned records are removed. */
    public function deletionBlockers(string $kind, int $id): array
    {
        $blocked=[];
        $account=$this->account($kind,$id);
        if ($kind==='customer' && ((float)$account->wallet_balance>0 || (float)$account->loyalty_point>0)) $blocked[]='account_balance';
        if ($account->email && Schema::hasTable('contacts') && DB::table('contacts')->where('email',$account->email)->exists()) $blocked[]='contacts';
        if ($kind==='customer' && Schema::hasColumn('users','referred_by') && DB::table('users')->where('referred_by',$id)->exists()) $blocked[]='customer_referrals';
        if ($kind==='vendor' && Schema::hasTable('products') && DB::table('products')->where('added_by','seller')->where('user_id',$id)->exists()) $blocked[]='products';
        $owned=$kind==='vendor'?['shops','vendor_account_profiles']:[];
        $columns=$kind==='vendor'?['seller_id','vendor_id']:['customer_id','user_id'];
        foreach (Schema::getTables() as $table) {
            $name=$table['name'];
            if (in_array($name,array_merge($owned,['users','sellers','oauth_access_tokens','oauth_auth_codes']),true)) continue;
            $available=Schema::getColumnListing($name);
            foreach (Schema::getForeignKeys($name) as $foreign) {
                if (($foreign['foreign_table'] ?? '')!==($kind==='vendor'?'sellers':'users')) continue;
                foreach ($foreign['columns'] as $column) if (DB::table($name)->where($column,$id)->exists()) $blocked[]=$name;
            }
            foreach (array_intersect($columns,$available) as $column) {
                // user_id in products/cart denotes a vendor only when explicitly marked.
                if ($kind==='customer' && $name==='products') continue;
                $query=DB::table($name)->where($column,$id);
                if ($name==='orders' && $kind==='vendor' && in_array('seller_is',$available)) $query->where('seller_is','seller');
                if ($name==='orders' && $kind==='customer' && in_array('is_guest',$available)) $query->where('is_guest',0);
                if ($query->exists()) {$blocked[]=$name;break;}
            }
            if (in_array('subject_type',$available) && in_array('subject_id',$available)) {
                if (DB::table($name)->where('subject_id',$id)->whereIn('subject_type',$kind==='vendor'?['seller','vendor']:['customer'])->exists()) $blocked[]=$name;
            }
        }
        return array_values(array_unique($blocked));
    }

    public function revokeCustomerTokens(int $id): void
    {
        if (Schema::hasTable('oauth_access_tokens')) {
            $tokens=DB::table('oauth_access_tokens')->where('user_id',$id)->pluck('id');
            if (Schema::hasTable('oauth_refresh_tokens')) DB::table('oauth_refresh_tokens')->whereIn('access_token_id',$tokens)->update(['revoked'=>true]);
            DB::table('oauth_access_tokens')->where('user_id',$id)->update(['revoked'=>true]);
        }
        if (Schema::hasTable('oauth_auth_codes')) DB::table('oauth_auth_codes')->where('user_id',$id)->update(['revoked'=>true]);
    }

    public function permanentlyDelete(string $kind,int $id): array
    {
        return DB::transaction(function () use ($kind,$id) {
            $account=$this->account($kind,$id,true);
            $blockers=$this->deletionBlockers($kind,$id);
            if ($blockers) return $blockers;
            if ($kind==='vendor') {
                DB::table('shops')->where('seller_id',$id)->delete();
                if (Schema::hasTable('vendor_account_profiles')) DB::table('vendor_account_profiles')->where('seller_id',$id)->delete();
            } else {
                if (Schema::hasTable('oauth_access_tokens')) {
                    $tokens=DB::table('oauth_access_tokens')->where('user_id',$id)->pluck('id');
                    if (Schema::hasTable('oauth_refresh_tokens')) DB::table('oauth_refresh_tokens')->whereIn('access_token_id',$tokens)->delete();
                    DB::table('oauth_access_tokens')->where('user_id',$id)->delete();
                }
                if (Schema::hasTable('oauth_auth_codes')) DB::table('oauth_auth_codes')->where('user_id',$id)->delete();
            }
            $account->delete();
            return [];
        });
    }
}
