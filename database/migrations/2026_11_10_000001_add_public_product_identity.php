<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('public_code', 64)->nullable()->unique();
            $table->boolean('is_credit_listing')->default(false)->index();
        });

        Schema::create('public_product_code_sequences', function (Blueprint $table) {
            $table->string('scope', 64)->primary();
            $table->unsignedBigInteger('next_sequence')->default(1);
        });

        // Existing links use the slug/database ID; this fills only the new
        // display identity and does not touch the editable SKU in `code`.
        DB::table('products')->where('request_status', 1)->where('status', 1)->whereIn('added_by', ['seller', 'admin'])
            ->orderBy('id')->select(['id', 'added_by', 'user_id', 'unit_price'])->chunk(500, function ($products) {
                foreach ($products as $product) {
                    $scope = $product->added_by === 'seller' ? 'seller:'.$product->user_id : 'admin';
                    $row = DB::table('public_product_code_sequences')->where('scope', $scope)->first();
                    $number = $row ? (int) $row->next_sequence : 1;
                    $code = $product->added_by === 'seller'
                        ? 'P'.$product->user_id.'-'.$number
                        : 'A-'.$number;
                    DB::table('products')->where('id', $product->id)->update([
                        'public_code' => $code,
                    ]);
                    DB::table('public_product_code_sequences')->updateOrInsert(
                        ['scope' => $scope], ['next_sequence' => $number + 1]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_product_code_sequences');
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['public_code']);
            $table->dropIndex(['is_credit_listing']);
            $table->dropColumn(['public_code', 'is_credit_listing']);
        });
    }
};
