<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderShippingProofService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderShippingProofServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session()->put('local', 'ar');

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('seller_is')->default('seller');
            $table->string('order_status')->default('pending');
            $table->string('commerce_flow_version')->nullable();
            $table->string('commerce_flow_status')->nullable();
            $table->timestamp('commerce_flow_status_updated_at')->nullable();
            $table->string('shipping_operational_status')->nullable();
            $table->string('shipping_fulfillment_mode')->nullable();
            $table->string('shipping_responsible_party')->nullable();
            $table->string('shipping_responsibility')->nullable();
            $table->string('shipping_workflow_status')->nullable();
            $table->timestamps();
        });

        Schema::create('order_shipping_proofs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id');
            $table->string('shipping_status');
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->text('note')->nullable();
            $table->string('review_status')->default('pending');
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('order_shipping_proofs');
        Schema::dropIfExists('orders');
        parent::tearDown();
    }

    public function test_delivery_upload_completes_without_intermediate_proofs_or_admin_approval(): void
    {
        Storage::fake('local');
        $order = Order::create(['seller_id' => 17, 'shipping_fulfillment_mode' => 'seller_shipping']);
        $this->mock(\App\Services\SellerDeliveryCompletionService::class, function ($mock) {
            $mock->shouldReceive('complete')->once()->andReturnUsing(function (Order $order) {
                self::assertTrue($order->shippingProofs()->where('shipping_status', 'delivered')->exists());
                $order->update(['order_status' => 'delivered']);
            });
        });
        $service = app(OrderShippingProofService::class);
        $proof = $service->submit($order, 17, 'delivered', UploadedFile::fake()->create('delivery.pdf', 20, 'application/pdf'));
        self::assertSame('delivered', $order->refresh()->order_status);
        self::assertSame('pending', $proof->review_status);
        $service->review($proof, 'rejected', 3, 'ملاحظة لاحقة');
        self::assertSame('delivered', $order->refresh()->order_status);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->submit($order, 17, 'delivered', UploadedFile::fake()->create('duplicate.pdf', 20, 'application/pdf'));
    }

    public function test_mobile_proof_uses_request_seller_without_web_login_and_keeps_private_history(): void
    {
        Storage::fake('local');
        $order = Order::create(['seller_id' => 17, 'shipping_fulfillment_mode' => 'seller_shipping']);
        $request = \Illuminate\Http\Request::create('/', 'POST', ['shipping_status'=>'delivered','note'=>'Delivery received']);
        $order->refresh();
        $request->merge(['seller'=>(new \App\Models\Seller)->forceFill(['id'=>17])]);
        $request->files->set('proof', UploadedFile::fake()->create('received.pdf',20,'application/pdf'));
        $this->mock(\App\Services\SellerDeliveryCompletionService::class, function ($mock) {
            $mock->shouldReceive('complete')->once()->andReturnUsing(function (Order $order) {
                self::assertSame(17, auth('seller')->id());
                $order->update(['order_status'=>'delivered']);
            });
        });
        $controller = app(\App\Http\Controllers\RestAPI\v3\seller\ShippingProofController::class);
        $response = $controller->store($request,$order,app(OrderShippingProofService::class));
        self::assertSame(201,$response->getStatusCode());
        self::assertNull(auth('seller')->id());
        self::assertSame('delivered',$response->getData(true)['order_status']);
        $history = $controller->index($request,$order->fresh())->getData(true)['proofs'];
        self::assertCount(1,$history);
        self::assertSame('received.pdf',$history[0]['original_name']);
        self::assertArrayNotHasKey('file_path',$history[0]);
        $download = $controller->download($request,$order,$order->shippingProofs()->first(),app(OrderShippingProofService::class));
        self::assertSame(200,$download->getStatusCode());
    }

    public function test_mobile_history_cannot_use_a_different_web_seller_identity(): void
    {
        $order = Order::create(['seller_id'=>17,'shipping_fulfillment_mode'=>'seller_shipping']);
        auth('seller')->setUser((new \App\Models\Seller)->forceFill(['id'=>17]));
        $request = \Illuminate\Http\Request::create('/','GET');
        $request->merge(['seller'=>(new \App\Models\Seller)->forceFill(['id'=>99])]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Http\Controllers\RestAPI\v3\seller\ShippingProofController::class)->index($request,$order);
    }

    public function test_completion_failure_rolls_back_proof_status_and_file(): void
    {
        Storage::fake('local');
        $order = Order::create(['seller_id' => 17, 'shipping_fulfillment_mode' => 'seller_shipping']);
        $this->mock(\App\Services\SellerDeliveryCompletionService::class, function ($mock) {
            $mock->shouldReceive('complete')->once()->andThrow(\Illuminate\Validation\ValidationException::withMessages(['proof' => 'Payment pending']));
        });
        try {
            app(OrderShippingProofService::class)->submit($order, 17, 'delivered', UploadedFile::fake()->create('delivery.pdf', 20, 'application/pdf'));
            self::fail('Expected completion to fail');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertSame('pending', $order->refresh()->order_status);
            self::assertNull($order->shipping_workflow_status);
            self::assertSame(0, $order->shippingProofs()->count());
            self::assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_delivery_finishes_the_managed_commerce_state_too(): void
    {
        Storage::fake('local');
        $order = Order::create(['seller_id'=>17,'shipping_fulfillment_mode'=>'seller_shipping',
            'commerce_flow_version'=>config('order_commerce.new_flow_version'),'commerce_flow_status'=>'fulfillment_in_progress']);
        $this->mock(\App\Services\SellerOrderVisibilityService::class)->shouldReceive('canSellerView')->andReturnTrue();
        $this->mock(\App\Services\SellerDeliveryCompletionService::class)->shouldReceive('complete')->once()->andReturnUsing(fn (Order $order) => $order->update(['order_status'=>'delivered']));
        app(OrderShippingProofService::class)->submit($order,17,'delivered',UploadedFile::fake()->create('delivered.pdf',20,'application/pdf'));
        self::assertSame('completed',$order->fresh()->commerce_flow_status);
        self::assertSame('delivered',$order->fresh()->shipping_operational_status);
    }

    public function test_other_seller_cannot_complete_order(): void
    {
        Storage::fake('local');
        $order = Order::create(['seller_id' => 17, 'shipping_fulfillment_mode' => 'seller_shipping']);
        $this->mock(\App\Services\SellerDeliveryCompletionService::class)->shouldNotReceive('complete');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(OrderShippingProofService::class)->submit($order, 99, 'delivered', UploadedFile::fake()->create('delivery.pdf', 20, 'application/pdf'));
    }

    public function test_completion_reuses_existing_order_workflow_and_propagates_failure(): void
    {
        $this->mock(\App\Http\Controllers\Vendor\Order\OrderController::class, function ($mock) {
            $mock->shouldReceive('updateStatus')->once()->andReturnUsing(function ($request) {
                self::assertSame('delivered', $request->input('order_status'));
                self::assertSame(123, $request->input('id'));
                self::assertTrue($request->ajax());
                return response()->json(['status' => 0, 'message' => 'Payment pending'], 422);
            });
        });
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\SellerDeliveryCompletionService::class)->complete(new Order(['id' => 123]));
    }

    public function test_seller_uploads_private_proofs_in_order_and_admin_approves_delivery(): void
    {
        Storage::fake('local');
        $this->mock(\App\Services\SellerDeliveryCompletionService::class, function ($mock) {
            $mock->shouldReceive('complete')->once()->andReturnUsing(function (Order $order) {
                $order->update(['order_status' => 'delivered']);
            });
        });
        $order = Order::query()->create([
            'seller_id' => 17,
            'shipping_fulfillment_mode' => 'seller_shipping',
            'shipping_responsibility' => 'sellerwise_shipping',
            'shipping_workflow_status' => 'pending',
        ]);
        $service = app(OrderShippingProofService::class);

        foreach (['ready_to_ship', 'shipped', 'delivered'] as $status) {
            $proof = $service->submit(
                $order->refresh(),
                17,
                $status,
                UploadedFile::fake()->create("{$status}.pdf", 20, 'application/pdf')
            );

            Storage::disk('local')->assertExists($proof->file_path);
            self::assertSame('pending', $proof->review_status);
            self::assertSame($status, $order->refresh()->shipping_workflow_status);
        }

        $reviewed = $service->review($proof, 'approved', 3, 'تمت مراجعة مستند التسليم');
        self::assertSame('delivered', $order->refresh()->order_status);
        self::assertSame('approved', $reviewed->review_status);
        self::assertSame(3, $reviewed->reviewed_by_admin_id);
        self::assertNotNull($reviewed->reviewed_at);
    }
}
