<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('wallet_transactions', function (Blueprint $t) {
            $t->json('order_ids')->nullable();
            $t->text('customer_note')->nullable();
            $t->unsignedBigInteger('note_admin_id')->nullable();
            $t->timestamp('note_updated_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('wallet_transactions', fn(Blueprint $t)=>$t->dropColumn(['order_ids','customer_note','note_admin_id','note_updated_at']));
    }
};
