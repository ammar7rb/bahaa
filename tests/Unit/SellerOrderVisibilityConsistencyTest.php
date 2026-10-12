<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\OrderWorkflowService;
use App\Services\SellerOrderVisibilityService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerOrderVisibilityConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('seller_is')->default('seller');
            $table->string('order_status')->default('pending');
            $table->string('commerce_flow_version')->nullable();
            $table->string('commerce_flow_status')->nullable();
            $table->string('admin_order_review_status')->nullable();
            $table->timestamps();
        });
    }

    public function test_legacy_spellings_and_missing_version_remain_accessible(): void
    {
        foreach (['legacy-v1', 'legacy_v1', null] as $version) {
            $this->order(['commerce_flow_version' => $version]);
        }
        $this->assertSame(3, Order::sellerAccessible()->count());
        $this->assertSame(3, app(SellerOrderVisibilityService::class)->accessible(Order::all())->count());
    }

    public function test_sql_counts_and_paginated_lists_match_access_checks_for_every_gate_state(): void
    {
        foreach (['post_purchase_v3', config('order_commerce.new_flow_version')] as $version) {
            foreach (['seller_insurance_pending', 'seller_insurance_under_review', 'released_to_seller', 'fulfillment_in_progress', 'completed', 'cancelled'] as $state) {
                $this->order(['commerce_flow_version' => $version, 'commerce_flow_status' => $state, 'admin_order_review_status' => 'approved']);
            }
            foreach (['pending_admin_review', 'assignment_in_progress', 'waiting_customer_post_purchase_payment', null, 'rejected'] as $review) {
                $this->order(['commerce_flow_version' => $version, 'commerce_flow_status' => 'released_to_seller', 'admin_order_review_status' => $review]);
            }
            $this->order(['commerce_flow_version' => $version, 'commerce_flow_status' => 'under_investigation', 'admin_order_review_status' => 'approved']);
        }
        $this->order(['seller_id' => 99, 'commerce_flow_version' => 'legacy_v1']);
        $this->order(['commerce_flow_version' => 'legacy_v1', 'admin_order_review_status' => 'pending_admin_review']);

        $expected = app(SellerOrderVisibilityService::class)->accessible(Order::where('seller_id', 8)->get())->pluck('id')->all();
        $query = Order::sellerAccessible()->where('seller_id', 8);
        $this->assertCount(12, $expected);
        $this->assertSame($expected, (clone $query)->pluck('id')->all());
        $this->assertSame(12, (clone $query)->count());

        $repo = app(OrderRepository::class);
        $filters = ['seller_id' => 8, 'seller_is' => 'seller', 'seller_visible_only' => true];
        $page = $repo->getListWhereIn(orderBy: ['id' => 'asc'], filters: $filters, dataLimit: 5);
        $this->assertSame(12, $page->total());
        $this->assertCount(5, $page->items());
        $this->assertSame(array_slice($expected, 0, 5), $page->pluck('id')->all());
        $this->assertSame(['pending' => 12], $repo->getStatusCounts(filters: $filters));
        $this->assertCount(12, $repo->getListWhere(filters: $filters, dataLimit: 'all'));
    }

    public function test_confirming_before_customer_payment_or_admin_release_is_rejected(): void
    {
        $order = $this->order([
            'commerce_flow_version' => 'post_purchase_v3',
            'commerce_flow_status' => 'customer_insurance_pending',
            'admin_order_review_status' => 'waiting_customer_post_purchase_payment',
        ]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('admin_order_release_required_before_confirmation');
        app(OrderWorkflowService::class)->assertAdminOrderCanAdvance($order, 'confirmed');
    }

    public function test_full_details_stay_hidden_when_release_was_not_approved(): void
    {
        $order = $this->order([
            'commerce_flow_version' => 'post_purchase_v3',
            'commerce_flow_status' => 'released_to_seller',
            'admin_order_review_status' => 'rejected',
        ]);
        $this->assertFalse(app(SellerOrderVisibilityService::class)->canSellerView($order));
    }

    public function test_released_order_can_be_confirmed_while_insurance_gate_stays_restricted(): void
    {
        $order = $this->order([
            'commerce_flow_version' => 'post_purchase_v3',
            'commerce_flow_status' => 'seller_insurance_pending',
            'admin_order_review_status' => 'approved',
        ]);
        app(OrderWorkflowService::class)->assertAdminOrderCanAdvance($order, 'confirmed');
        $this->assertTrue(app(SellerOrderVisibilityService::class)->canSellerAccess($order));
    }

    public function test_unreleased_order_can_still_be_cancelled_and_in_house_orders_are_unaffected(): void
    {
        $order = $this->order(['commerce_flow_version' => 'post_purchase_v3', 'admin_order_review_status' => 'pending_admin_review']);
        app(OrderWorkflowService::class)->assertAdminOrderCanAdvance($order, 'canceled');
        $order->seller_is = 'admin';
        app(OrderWorkflowService::class)->assertAdminOrderCanAdvance($order, 'confirmed');
        $this->assertSame('pending_admin_review', $order->fresh()->admin_order_review_status);
    }

    private function order(array $attributes = []): Order
    {
        $order = new Order;
        $order->forceFill(array_replace(['seller_id' => 8, 'seller_is' => 'seller', 'commerce_flow_version' => 'legacy-v1'], $attributes));
        $order->save();

        return $order;
    }
}
