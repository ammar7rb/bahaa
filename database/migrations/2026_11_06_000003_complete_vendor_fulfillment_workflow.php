<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach(['accepted_at'=>'timestamp','rejected_at'=>'timestamp','canceled_at'=>'timestamp','delivered_at'=>'timestamp',
            'decision_reason'=>'text','unlock_snapshot'=>'json','penalty_rule_snapshot'=>'json','penalty_charge_id'=>'unsignedBigInteger'] as $name=>$type){
            if(!Schema::hasColumn('vendor_fulfillment_requests',$name))Schema::table('vendor_fulfillment_requests',fn(Blueprint $t)=>$t->{$type}($name)->nullable());
        }
        if(!Schema::hasColumn('vendor_fulfillment_events','metadata'))Schema::table('vendor_fulfillment_events', function (Blueprint $t) { $t->json('metadata')->nullable(); });
        if(!Schema::hasColumn('order_shipping_proofs','fulfillment_request_id'))Schema::table('order_shipping_proofs', function (Blueprint $t) { $t->unsignedBigInteger('fulfillment_request_id')->nullable()->index(); });
        if(!Schema::hasColumn('refund_requests','fulfillment_request_id'))Schema::table('refund_requests',fn(Blueprint $t)=>$t->unsignedBigInteger('fulfillment_request_id')->nullable()->index());
        if(!Schema::hasIndex('refund_requests','fulfillment_line_refund_unique'))Schema::table('refund_requests',fn(Blueprint $t)=>$t->unique(['fulfillment_request_id','order_details_id'],'fulfillment_line_refund_unique'));
        if(!Schema::hasTable('vendor_cancellation_penalty_settings'))Schema::create('vendor_cancellation_penalty_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary(); $t->boolean('enabled')->default(false);
            $t->string('mode', 16)->default('fixed'); $t->decimal('fixed_amount', 18, 2)->default(0);
            $t->decimal('percentage', 7, 4)->default(0); $t->unsignedBigInteger('revision')->default(1);
            $t->unsignedBigInteger('updated_by')->nullable(); $t->timestamps();
        });
        if(!Schema::hasTable('vendor_fulfillment_financial_reviews'))Schema::create('vendor_fulfillment_financial_reviews', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('request_id')->unique(); $t->unsignedBigInteger('order_id')->index();
            $t->string('status', 24)->default('pending'); $t->json('line_ids');
            $t->text('reason'); $t->text('admin_note')->nullable(); $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve acceptance, assessed fees, proof and refund-review history. Use a forward repair.');
    }
};
