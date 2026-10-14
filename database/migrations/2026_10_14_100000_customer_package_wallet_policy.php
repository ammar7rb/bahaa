<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        // Validate the catalog before schema changes; never guess which duplicate tier to retain.
        $tiers=[];
        foreach(['silver','gold','diamond'] as $tier){
            $ids=DB::table('customer_purchase_packages')->where('is_custom',0)->whereRaw('LOWER(name) = ?',[$tier])->pluck('id');
            if($ids->count()!==1)throw new RuntimeException('Expected exactly one '.$tier.' customer package.');
            $tiers[$tier]=$ids->first();
        }
        Schema::table('customer_purchase_packages',function(Blueprint $t){
            if(!Schema::hasColumn('customer_purchase_packages','customer_tier'))$t->string('customer_tier',20)->nullable()->unique();
            if(!Schema::hasColumn('customer_purchase_packages','renewal_price'))$t->decimal('renewal_price',18,3)->nullable();
        });
        Schema::table('customer_subscription_payments',function(Blueprint $t){
            if(!Schema::hasColumn('customer_subscription_payments','wallet_policy_version'))$t->unsignedTinyInteger('wallet_policy_version')->default(1);
            if(!Schema::hasColumn('customer_subscription_payments','operation_signature'))$t->string('operation_signature',64)->nullable();
            if(!Schema::hasColumn('customer_subscription_payments','terms_snapshot'))$t->json('terms_snapshot')->nullable();
            foreach(['allowance_before','allowance_after'] as $column)if(!Schema::hasColumn('customer_subscription_payments',$column))$t->decimal($column,18,3)->nullable();
            if(!Schema::hasColumn('customer_subscription_payments','wallet_balance_after'))$t->decimal('wallet_balance_after',24,3)->nullable();
            if(!Schema::hasColumn('customer_subscription_payments','subscription_expires_at'))$t->timestamp('subscription_expires_at')->nullable();
        });
        if(!Schema::hasColumn('wallet_transactions','customer_subscription_payment_id'))Schema::table('wallet_transactions',fn(Blueprint $t)=>$t->unsignedBigInteger('customer_subscription_payment_id')->nullable()->unique());
        if(!Schema::hasColumn('customer_monthly_subscriptions','limit_revision'))Schema::table('customer_monthly_subscriptions',fn(Blueprint $t)=>$t->unsignedInteger('limit_revision')->default(0));
        foreach($tiers as $tier=>$id)DB::table('customer_purchase_packages')->where('id',$id)->update(['customer_tier'=>$tier]);
        // Keep excluded packages and all their historical relationships intact.
        DB::table('customer_purchase_packages')->whereNull('customer_tier')->update(['status'=>0]);
        DB::transaction(function(){
            $drafts=DB::table('customer_subscription_payments')->where('wallet_policy_version',1)->where('status','draft')
                ->whereNull('submitted_at')->whereNull('payment_reference')->whereNull('payment_proof')->lockForUpdate()->get();
            foreach($drafts as $draft){
                if(DB::table('wallet_transactions')->where('customer_subscription_payment_id',$draft->id)->orWhere('reference','customer-monthly-subscription-'.$draft->id)->orWhere('transaction_id','customer-monthly-subscription-'.$draft->id)->exists())continue;
                DB::table('customer_financial_archives')->insertOrIgnore(['source_table'=>'customer_subscription_payments','source_id'=>(string)$draft->id,
                    'snapshot'=>json_encode($draft,JSON_UNESCAPED_UNICODE),'reason'=>'wallet_only_policy_cancel_unpaid_draft','archived_at'=>now()]);
                DB::table('customer_subscription_payments')->where('id',$draft->id)->update(['status'=>'cancelled','open_customer_id'=>null,'review_note'=>'أُلغيت المسودة غير المدفوعة عند اعتماد معاملات المحفظة المباشرة.','updated_at'=>now()]);
            }
        });
    }
    public function down(): void { throw new RuntimeException('Preserve package and wallet history during rollback.'); }
};
