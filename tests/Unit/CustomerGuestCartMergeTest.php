<?php

namespace Tests\Unit;

use App\Models\Cart;
use App\Models\User;
use App\Utils\CartManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerGuestCartMergeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->timestamps(); });
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            foreach (['customer_id','product_id','seller_id','quantity','is_guest','is_checked'] as $column) $table->integer($column)->default(0);
            foreach (['seller_is','variant','cart_group_id'] as $column) $table->string($column);
            $table->timestamps();
        });
    }
    protected function tearDown(): void
    {
        Schema::dropIfExists('carts'); Schema::dropIfExists('users'); parent::tearDown();
    }
    public function test_quantities_merge_once_without_losing_other_variants(): void
    {
        $user = User::query()->create([]);
        $base = ['product_id'=>4,'seller_id'=>1,'seller_is'=>'seller','is_checked'=>1];
        Cart::query()->forceCreate($base + ['customer_id'=>$user->id,'is_guest'=>0,'quantity'=>2,'variant'=>'red','cart_group_id'=>'customer-group']);
        Cart::query()->forceCreate($base + ['customer_id'=>99,'is_guest'=>1,'quantity'=>3,'variant'=>'red','cart_group_id'=>'guest-group']);
        Cart::query()->forceCreate($base + ['customer_id'=>99,'is_guest'=>1,'quantity'=>1,'variant'=>'blue','cart_group_id'=>'guest-group']);
        $request = Request::create('/', 'POST', ['guest_id'=>99]);
        $request->setUserResolver(fn () => $user);
        CartManager::cartListSessionToDatabase($request);
        CartManager::cartListSessionToDatabase($request);
        $this->assertSame(2, Cart::query()->count());
        $this->assertSame(5, Cart::query()->where('variant','red')->value('quantity'));
        $this->assertSame(1, Cart::query()->where('variant','blue')->value('quantity'));
        $this->assertSame(0, Cart::query()->where('is_guest',1)->count());
        $this->assertSame(['customer-group'], Cart::query()->pluck('cart_group_id')->unique()->values()->all());
    }
    public function test_guest_cannot_merge_into_an_unauthenticated_account(): void
    {
        $request = Request::create('/', 'POST', ['guest_id'=>99]);
        CartManager::cartListSessionToDatabase($request);
        $this->assertSame(0, Cart::query()->count());
    }
}
