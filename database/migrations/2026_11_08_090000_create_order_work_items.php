<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_work_items')) Schema::create('order_work_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('order_detail_id')->unique();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('seller_id')->nullable()->index();
            $table->string('status', 40)->default('awaiting_admin');
            $table->decimal('vendor_unit_price', 24, 8)->nullable();
            $table->decimal('shipping_customer_cost', 24, 8)->nullable();
            $table->decimal('shipping_vendor_cost', 24, 8)->nullable();
            $table->decimal('customer_total', 24, 8)->nullable();
            $table->decimal('vendor_net_amount', 24, 8)->nullable();
            $table->timestamp('expected_delivery_at')->nullable();
            $table->timestamp('vendor_payment_due_at')->nullable();
            $table->timestamp('sent_to_vendor_at')->nullable();
            $table->timestamp('vendor_response_due_at')->nullable()->index();
            $table->timestamp('vendor_responded_at')->nullable();
            $table->string('vendor_response', 24)->nullable();
            $table->unsignedBigInteger('assigned_by_admin_id')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });

        if (!Schema::hasTable('order_work_item_events')) Schema::create('order_work_item_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('work_item_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('order_detail_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 20)->default('system');
            $table->string('event_key', 64);
            $table->string('status_before', 40)->nullable();
            $table->string('status_after', 40)->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        // Create an independent operations record for every product line already in the system.
        DB::table('order_details as line')->join('orders as parent', 'parent.id', '=', 'line.order_id')
            ->select([
                'line.id as line_id', 'line.order_id', 'line.product_id', 'line.seller_id',
                'line.vendor_unit_price_snapshot', 'line.delivery_status', 'line.created_at as line_created_at', 'line.updated_at as line_updated_at',
                'parent.seller_id as parent_seller_id', 'parent.order_status', 'parent.commerce_flow_status',
                'parent.seller_shipping_response_status', 'parent.seller_shipping_response_at', 'parent.admin_order_reviewed_at', 'parent.seller_shipping_response_due_at',
                'parent.created_at as order_created_at', 'parent.updated_at as order_updated_at',
            ])->orderBy('line.id')->chunk(500, function ($lines): void {
            foreach ($lines as $line) {
                $status = 'awaiting_admin';
                if (($line->delivery_status ?? null) === 'canceled' || ($line->order_status ?? null) === 'canceled' || ($line->commerce_flow_status ?? null) === 'cancelled') $status = 'canceled';
                elseif (($line->delivery_status ?? null) === 'delivered') $status = 'completed';
                elseif (($line->order_status ?? null) === 'delivered') $status = 'completed';
                elseif (($line->seller_shipping_response_status ?? null) === 'rejected') $status = 'rejected';
                elseif (($line->seller_shipping_response_status ?? null) === 'accepted' || in_array($line->order_status ?? null, ['processing', 'out_for_delivery'], true)) $status = 'accepted';
                elseif (in_array($line->commerce_flow_status ?? null, ['released_to_seller', 'fulfillment_in_progress'], true) || ($line->order_status ?? null) === 'confirmed') $status = 'sent_to_vendor';

                $sentAt = $line->admin_order_reviewed_at ?? $line->order_updated_at;
                $responseDueAt = $line->seller_shipping_response_due_at;
                if (in_array($status, ['sent_to_vendor', 'accepted'], true)) {
                    $responseDueAt = $sentAt ? date('Y-m-d H:i:s', strtotime($sentAt.' +24 hours')) : null;
                }

                if (DB::table('order_work_items')->where('order_detail_id', $line->line_id)->exists()) continue;
                $id = DB::table('order_work_items')->insertGetId([
                    'order_id' => $line->order_id, 'order_detail_id' => $line->line_id,
                    'product_id' => $line->product_id, 'seller_id' => $line->seller_id ?? $line->parent_seller_id,
                    'status' => $status, 'vendor_unit_price' => $line->vendor_unit_price_snapshot ?? null,
                    'sent_to_vendor_at' => in_array($status, ['sent_to_vendor', 'accepted'], true) ? $sentAt : null,
                    'vendor_response_due_at' => $responseDueAt,
                    'vendor_responded_at' => in_array($status, ['accepted', 'rejected'], true) ? $line->seller_shipping_response_at : null,
                    'vendor_response' => in_array($status, ['accepted', 'rejected'], true) ? $status : null,
                    'created_at' => $line->line_created_at ?? $line->order_created_at,
                    'updated_at' => $line->line_updated_at ?? $line->order_updated_at,
                ]);
                DB::table('order_work_item_events')->insert([
                    'work_item_id' => $id, 'order_id' => $line->order_id, 'order_detail_id' => $line->line_id,
                    'actor_type' => 'system', 'event_key' => 'imported_into_order_center',
                    'status_after' => $status,
                    'snapshot' => json_encode(['source' => 'existing_order_detail'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $line->line_created_at ?? $line->order_created_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_work_item_events');
        Schema::dropIfExists('order_work_items');
    }
};
