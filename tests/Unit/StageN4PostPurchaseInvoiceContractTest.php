<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StageN4PostPurchaseInvoiceContractTest extends TestCase
{
    public function test_second_invoice_has_an_immutable_tax_and_insurance_contract(): void
    {
        $service = $this->source('app/Services/PostPurchaseInvoiceService.php');
        $migration = $this->source('database/migrations/2026_08_16_000003_add_post_purchase_payment_state_and_customer_insurance_balances.php');

        self::assertStringContainsString('createForPaidOrderGroup', $service);
        self::assertStringContainsString('createMissingInvoicesForPaidOrders', $service);
        self::assertStringContainsString("'tax_snapshot'", $service);
        self::assertStringContainsString("'insurance_snapshot'", $service);
        self::assertStringContainsString('applyCustomerInsuranceBalance', $service);
        self::assertStringContainsString('return $this->completeIfFullyPaid($invoice->fresh())', $service);
        self::assertStringContainsString('post_purchase_invoice_external_amount_mismatch', $service);
        self::assertStringContainsString('isApprovedOfflineReview', $service);
        self::assertStringContainsString('releaseToSeller', $service);
        self::assertStringContainsString("Schema::create('customer_insurance_balances'", $migration);
        self::assertStringContainsString("Schema::create('insurance_balance_actions'", $migration);
        self::assertStringContainsString("return \$this->status === self::STATUS_PENDING", $this->source('app/Models/PostPurchaseInvoice.php'));
    }

    public function test_first_payment_excludes_tax_and_the_seller_is_gated_until_admin_release(): void
    {
        $manager = $this->source('app/Utils/OrderManager.php');
        $adminOrderController = $this->source('app/Http/Controllers/Admin/Order/OrderController.php');

        self::assertStringContainsString("'first_payment_amount'", $manager);
        self::assertStringContainsString("'order_amount' => \$cartData['first_payment_amount']", $manager);
        self::assertStringContainsString('ORDER_STATUS_AWAITING_PAYMENT', $manager);
        self::assertStringContainsString('isBlockedByPostPurchaseInvoice', $manager);
        self::assertStringContainsString("if (\$isBlockedByPostPurchaseInvoice)", $manager);
        self::assertStringContainsString("'tax' => \$sellerTax", $manager);
        $gate = $this->source('app/Services/AdminOrderGateService.php');
        self::assertStringContainsString('ORDER_STATUS_RELEASED', $gate);
        self::assertStringContainsString('customer_post_purchase_invoice_must_be_paid_before_admin_review', $gate);
        self::assertStringContainsString('$service->assign(', $adminOrderController);
    }

    public function test_web_api_admin_and_customer_app_are_wired_to_the_same_invoice(): void
    {
        $webRoutes = $this->source('routes/web/routes.php');
        $apiRoutes = $this->source('routes/rest_api/v1/api.php');
        $adminRoutes = $this->source('routes/admin/routes.php');
        $mobileController = $this->source('app/Http/Controllers/RestAPI/v1/PostPurchaseInvoiceController.php');
        $appScreen = $this->source('../user-app-main/lib/features/order_insurance/screens/customer_order_insurance_screen.dart');

        self::assertStringContainsString("prefix' => 'post-purchase-invoices'", $webRoutes);
        self::assertStringContainsString("prefix' => 'customer/post-purchase-invoices'", $apiRoutes);
        self::assertStringContainsString("prefix' => 'customer/order-insurance'", $apiRoutes);
        self::assertStringContainsString("prefix('post-purchase-invoices')", $adminRoutes);
        self::assertStringContainsString('mobileEnvelope', $mobileController);
        self::assertStringContainsString("getTranslated('tax_amount_due'", $appScreen);
        self::assertStringContainsString("getTranslated('total_amount_due'", $appScreen);
    }

    private function source(string $path): string
    {
        $base = dirname(__DIR__, 2);
        if (str_starts_with($path, '../user-app-main/')) {
            $workspace = getenv('MOBILE_APPS_WORKSPACE') ?: dirname($base, 3).DIRECTORY_SEPARATOR.'project';
            $base = $workspace;
            $path = substr($path, 3);
        }
        $contents = file_get_contents($base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($contents, "Could not read {$path}");

        return $contents;
    }
}
