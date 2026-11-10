<?php

namespace Tests\Unit;

use App\Models\OfflinePaymentMethod;
use App\Models\Order;
use App\Services\CheckoutTransferAmountService;
use App\Services\PostPurchaseInvoiceService;
use App\Utils\OrderManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessSettingsHistoricalSnapshotTest extends TestCase
{
    public function test_new_payment_instruction_is_copied_into_transaction_snapshot(): void
    {
        Schema::create('offline_payment_methods', function (Blueprint $t) {
            $t->id(); $t->string('method_name'); $t->string('payment_channel');
            $t->json('method_fields'); $t->json('method_informations'); $t->boolean('status'); $t->timestamps();
        });
        try {
            $method = OfflinePaymentMethod::create(['method_name' => 'Wallet', 'payment_channel' => 'wallet',
                'method_fields' => [['input_name' => 'wallet_number', 'input_data' => '01000000000']],
                'method_informations' => [], 'status' => true]);
            $snapshot = app(CheckoutTransferAmountService::class)->snapshot(['method_id' => $method->id], 120, 20);
            $method->update(['method_fields' => [['input_name' => 'wallet_number', 'input_data' => '01111111111']]]);
            $this->assertSame('01000000000', $snapshot['recipient_method_snapshot']['fields'][0]['input_data']);
            $this->assertSame('120.000', $snapshot['transferred_amount']);
        } finally { Schema::dropIfExists('offline_payment_methods'); }
    }

    public function test_saved_order_tax_remains_even_when_current_global_setting_changes(): void
    {
        $order = new Order(['tax_type' => 'customer_product_14', 'tax_model' => 'exclude', 'total_tax_amount' => 14]);
        $method = new \ReflectionMethod(PostPurchaseInvoiceService::class, 'taxSnapshot');
        $snapshot = $method->invoke(app(PostPurchaseInvoiceService::class), $order);
        $this->assertSame(14.0, $snapshot['amount']);
        $this->assertSame(100.0, $snapshot['taxable_amount']);
        $this->assertSame(0.0, OrderManager::customerProductTaxAmount(0, 0, 0));
        $this->assertSame(14.0, OrderManager::customerProductTaxAmount(100, 100, 0));
    }
}
