<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->uuid('request_key')->nullable();
            $table->unique(['seller_id', 'request_key']);
        });
        $definitions = [
            'bank' => ['Bank account', ['recipient_name', 'bank_name', 'account_number', 'iban']],
            'instapay' => ['InstaPay', ['recipient_name', 'instapay_address']],
            'electronic_wallet' => ['Electronic wallet', ['recipient_name', 'wallet_provider', 'wallet_number']],
        ];
        $existing = \App\Models\WithdrawalMethod::all()->filter(function ($method) {
            try { app(\App\Services\SellerWithdrawalMethodService::class)->resolveMethodType(null, $method->method_name); return true; }
            catch (\Throwable) { return false; }
        })->map(fn ($method) => app(\App\Services\SellerWithdrawalMethodService::class)->resolveMethodType(null, $method->method_name));
        foreach ($definitions as $type => [$name, $fields]) {
            if ($existing->contains($type)) continue;
            \App\Models\WithdrawalMethod::create(['method_name' => $name, 'is_active' => true, 'is_default' => false,
                'method_fields' => array_map(fn ($field) => ['input_type' => 'string', 'input_name' => $field, 'placeholder' => $field, 'is_required' => $field !== 'iban'], $fields)]);
        }
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('no_production_expiry_dates')->default(false);
            $table->boolean('category_review_required')->default(false)->index();
        });
    }
    public function down(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->dropUnique(['seller_id', 'request_key']);
            $table->dropColumn('request_key');
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['no_production_expiry_dates', 'category_review_required']));
    }
};
