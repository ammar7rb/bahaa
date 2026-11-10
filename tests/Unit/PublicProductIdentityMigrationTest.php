<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicProductIdentityMigrationTest extends TestCase
{
    public function test_existing_published_products_receive_deterministic_codes_without_changing_sku_or_slug(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('added_by');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('request_status');
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('code');
            $table->string('slug');
            $table->decimal('unit_price', 20, 2);
        });
        foreach ([
            ['seller', 1, 1, 'SKU-A', 'alpha', 10],
            ['seller', 1, 1, 'SKU-B', 'beta', 20],
            ['seller', 2, 1, 'SKU-C', 'gamma', 30],
            ['admin', 9, 1, 'SKU-D', 'delta', 40],
            ['seller', 1, 0, 'SKU-E', 'epsilon', 50],
        ] as [$owner, $user, $published, $sku, $slug, $price]) {
            DB::table('products')->insert(['added_by' => $owner, 'user_id' => $user, 'request_status' => $published, 'code' => $sku, 'slug' => $slug, 'unit_price' => $price]);
        }

        $migration = require base_path('database/migrations/2026_11_10_000001_add_public_product_identity.php');
        DB::table('products')->insert(['added_by'=>'seller','user_id'=>3,'request_status'=>1,'status'=>0,'code'=>'HIDDEN','slug'=>'never-published','unit_price'=>60]);
        $migration->up();

        $rows = DB::table('products')->orderBy('id')->get();
        $this->assertSame(['P1-1', 'P1-2', 'P2-1', 'A-1', null, null], $rows->pluck('public_code')->all());
        $this->assertSame(['SKU-A', 'SKU-B', 'SKU-C', 'SKU-D', 'SKU-E', 'HIDDEN'], $rows->pluck('code')->all());
        $this->assertSame(['alpha', 'beta', 'gamma', 'delta', 'epsilon', 'never-published'], $rows->pluck('slug')->all());

        $migration->down();
        Schema::dropIfExists('products');
    }
}
