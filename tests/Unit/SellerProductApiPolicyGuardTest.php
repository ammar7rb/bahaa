<?php

namespace Tests\Unit;

use App\Http\Controllers\RestAPI\v2\seller\ProductController as LegacyProductController;
use App\Http\Controllers\RestAPI\v3\seller\ProductController as CurrentProductController;
use App\Models\Product;
use App\Models\Seller;
use App\Services\SellerProductContentPolicyService;
use App\Services\SellerProductEntitlementService;
use App\Services\SellerProductReviewService;
use App\Services\SellerProductSpecificationService;
use App\Services\SellerProductCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SellerProductApiPolicyGuardTest extends TestCase
{
    private Seller $seller;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('approved');
            $table->string('auth_token')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->uuid('registration_reference')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('added_by');
            $table->integer('request_status')->default(0);
            $table->timestamps();
        });
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translationable_type');
            $table->unsignedBigInteger('translationable_id');
            $table->string('locale');
            $table->string('key');
            $table->text('value');
            $table->timestamps();
        });
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('digital_product_variations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->timestamps();
        });
        Schema::create('product_seos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->timestamps();
        });
        Schema::create('seller_product_content_violations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('category', 60);
            $table->string('source', 40);
            $table->timestamp('detected_at');
            $table->timestamps();
        });

        DB::table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([[
                'code' => 'en',
                'status' => 1,
                'default' => true,
                'direction' => 'ltr',
            ]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->token = Str::random(48);
        $this->seller = Seller::create([
            'status' => 'approved',
            'auth_token' => $this->token,
            'phone_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Schema::dropIfExists('seller_product_content_violations');
        Schema::dropIfExists('product_seos');
        Schema::dropIfExists('digital_product_variations');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('translations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_legacy_add_api_rejects_contact_content_and_records_the_violation(): void
    {
        $response = $this->legacyController()->add_new($this->legacyRequest([
            'name' => ['Medical gloves'],
            'description' => ['Contact us on 01012345678'],
            'images' => $this->fiveImages(),
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('seller_product_content_policy_violation', $response->getData(true)['errors'][0]['code']);
        $this->assertDatabaseHas('seller_product_content_violations', [
            'seller_id' => $this->seller->id,
            'category' => 'phone_number',
        ]);
    }

    public function test_legacy_update_api_rejects_contact_content_and_links_the_product(): void
    {
        $product = Product::create(['user_id' => $this->seller->id, 'added_by' => 'seller']);
        $response = $this->legacyController()->update($this->legacyRequest([
            'name' => ['Medical gloves'],
            'description' => ['Write to seller@example.com'],
            'images' => $this->fiveImages(),
        ]), $product->id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('seller_product_content_policy_violation', $response->getData(true)['errors'][0]['code']);
        $this->assertDatabaseHas('seller_product_content_violations', [
            'seller_id' => $this->seller->id,
            'product_id' => $product->id,
            'category' => 'email_address',
        ]);
    }

    public function test_api_content_guards_allow_products_with_zero_or_few_images(): void
    {
        foreach ([[], ['one.webp'], ['one.webp', 'two.webp', 'three.webp', 'four.webp']] as $images) {
            $legacy = $this->legacyRequest(['name' => ['Medical gloves'], 'description' => ['Sterile gloves'], 'images' => $images]);
            $current = Request::create('/', 'POST', [
                'name' => json_encode(['Medical gloves']),
                'description' => json_encode(['Sterile gloves']),
                'images' => json_encode(array_map(fn ($name) => ['image_name' => $name], $images)),
                'color_image' => json_encode([]),
            ]);
            foreach ([[$this->legacyController(), $legacy], [app(CurrentProductController::class), $current]] as [$controller, $request]) {
                $method = new \ReflectionMethod($controller, 'guardProductPolicy');
                $this->assertNull($method->invoke($controller, $request, $this->seller));
            }
        }
    }

    public function test_empty_product_images_are_stored_and_fall_back_to_placeholder(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('images')->nullable();
            $table->string('thumbnail')->nullable();
        });
        $request = Request::create('/', 'POST', ['colors_active' => 1, 'colors' => ['#ffffff']]);
        $images = app(\App\Services\ProductService::class)->getProcessedImages($request);
        $this->assertSame([], $images['image_names']);
        $this->assertSame([], $images['colored_image_names']);
        $product = Product::create(['user_id' => $this->seller->id, 'added_by' => 'seller', 'images' => json_encode($images['image_names']), 'thumbnail' => null]);
        $this->assertSame('[]', $product->fresh()->images);
        $this->assertNull($product->fresh()->thumbnail);
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY', 'public');
        $this->assertStringContainsString('placeholder/product.png', getStorageImages(['status' => 404, 'path' => null], 'backend-product'));
    }

    public function test_optional_thumbnail_still_rejects_non_image_uploads(): void
    {
        $rules = (new \App\Http\Requests\ProductAddRequest())->rules();
        $this->assertTrue(\Validator::make(['image' => null], ['image' => $rules['image']])->passes());
        $file = \Illuminate\Http\UploadedFile::fake()->create('not-an-image.txt', 1, 'text/plain');
        $this->assertTrue(\Validator::make(['image' => $file], ['image' => $rules['image']])->fails());
    }

    private function legacyController(): LegacyProductController
    {
        return new LegacyProductController(
            Mockery::mock(SellerProductEntitlementService::class),
            app(SellerProductContentPolicyService::class),
            app(SellerProductReviewService::class),
            app(SellerProductSpecificationService::class),
            app(SellerProductCategoryService::class),
        );
    }

    private function legacyRequest(array $data): Request
    {
        $request = Request::create('/', 'POST', $data);
        $request->headers->set('authorization', 'Bearer ' . $this->token);

        return $request;
    }

    private function fiveImages(): array
    {
        return ['one.webp', 'two.webp', 'three.webp', 'four.webp', 'five.webp'];
    }
}
