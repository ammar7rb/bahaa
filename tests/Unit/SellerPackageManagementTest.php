<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Vendor\SellerPackageController;
use App\Http\Requests\Admin\SellerPackageRequest;
use App\Models\SellerPackage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SellerPackageManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('package_price', 40, 20)->default(0);
            $table->unsignedInteger('product_limit')->default(0);
            $table->unsignedInteger('product_duration_days')->default(0);
            $table->unsignedInteger('search_promotion_limit')->default(0);
            $table->unsignedInteger('search_promotion_duration_days')->default(0);
            $table->unsignedInteger('homepage_promotion_limit')->default(0);
            $table->unsignedInteger('homepage_promotion_duration_days')->default(0);
            $table->unsignedInteger('package_validity_days')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_package_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('seller_package_id')->nullable();
            $table->string('package_name')->nullable();
            $table->decimal('paid_package_price', 40, 20)->default(0);
            $table->unsignedInteger('product_limit')->default(0);
            $table->unsignedInteger('product_duration_days')->default(0);
            $table->unsignedInteger('search_promotion_limit')->default(0);
            $table->unsignedInteger('search_promotion_duration_days')->default(0);
            $table->unsignedInteger('homepage_promotion_limit')->default(0);
            $table->unsignedInteger('homepage_promotion_duration_days')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']]),
        ]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_package_subscriptions');
        Schema::dropIfExists('seller_packages');
        Schema::dropIfExists('business_settings');
        Cache::flush();

        parent::tearDown();
    }

    public function test_optional_promotion_quotas_accept_zero_without_duration(): void
    {
        $data = $this->validPackageData();
        unset($data['search_promotion_duration_days'], $data['homepage_promotion_duration_days']);

        $validator = Validator::make($data, (new SellerPackageRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_enabled_promotion_quota_requires_its_own_duration(): void
    {
        $data = $this->validPackageData();
        $data['search_promotion_limit'] = 2;
        unset($data['search_promotion_duration_days']);

        $validator = Validator::make($data, (new SellerPackageRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('search_promotion_duration_days', $validator->errors()->toArray());
    }

    public function test_updating_package_definition_does_not_change_existing_subscription_snapshot(): void
    {
        $package = $this->createPackage();
        DB::table('seller_package_subscriptions')->insert([
            'seller_id' => 10,
            'seller_package_id' => $package->id,
            'package_name' => $package->name,
            'paid_package_price' => $package->package_price,
            'product_limit' => 5,
            'product_duration_days' => 30,
            'search_promotion_limit' => 1,
            'search_promotion_duration_days' => 7,
            'homepage_promotion_limit' => 0,
            'homepage_promotion_duration_days' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $package->update([
            'product_limit' => 20,
            'product_duration_days' => 90,
            'search_promotion_limit' => 10,
        ]);
        $snapshot = DB::table('seller_package_subscriptions')->where('seller_package_id', $package->id)->first();

        $this->assertSame(5, (int) $snapshot->product_limit);
        $this->assertSame(30, (int) $snapshot->product_duration_days);
        $this->assertSame(1, (int) $snapshot->search_promotion_limit);
    }

    public function test_package_with_subscription_cannot_be_deleted(): void
    {
        $package = $this->createPackage();
        DB::table('seller_package_subscriptions')->insert([
            'seller_id' => 10,
            'seller_package_id' => $package->id,
            'package_name' => $package->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(SellerPackageController::class)->destroy($package->id);

        $this->assertDatabaseHas('seller_packages', ['id' => $package->id]);
    }

    public function test_unused_package_can_be_deleted(): void
    {
        $package = $this->createPackage();

        app(SellerPackageController::class)->destroy($package->id);

        $this->assertDatabaseMissing('seller_packages', ['id' => $package->id]);
    }

    public function test_package_status_can_be_disabled_without_changing_its_definition(): void
    {
        $package = $this->createPackage();
        $request = Request::create('/admin/vendors/packages/status', 'POST', [
            'id' => $package->id,
            'status' => 0,
        ]);

        $response = app(SellerPackageController::class)->updateStatus($request);

        $this->assertSame(200, $response->status());
        $package->refresh();
        $this->assertFalse($package->status);
        $this->assertSame(5, $package->product_limit);
    }

    private function validPackageData(): array
    {
        return [
            'name' => 'Basic',
            'package_price' => 100,
            'product_limit' => 5,
            'product_duration_days' => 30,
            'search_promotion_limit' => 0,
            'homepage_promotion_limit' => 0,
            'status' => 1,
            'sort_order' => 1,
        ];
    }

    private function createPackage(): SellerPackage
    {
        return SellerPackage::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Basic seller package',
            'package_price' => 100,
            'product_limit' => 5,
            'product_duration_days' => 30,
            'search_promotion_limit' => 1,
            'search_promotion_duration_days' => 7,
            'homepage_promotion_limit' => 0,
            'homepage_promotion_duration_days' => 0,
            'package_validity_days' => null,
            'status' => true,
            'sort_order' => 1,
        ]);
    }
}
