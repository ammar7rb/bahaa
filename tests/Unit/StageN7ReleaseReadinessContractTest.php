<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StageN7ReleaseReadinessContractTest extends TestCase
{
    public function test_laravel_financial_state_activation_calendar_and_refund_contracts_are_covered(): void
    {
        $migrationFiles = [
            'database/migrations/2026_08_16_000001_create_unified_account_activation_center.php',
            'database/migrations/2026_08_16_000002_prepare_commerce_contract_v3.php',
            'database/migrations/2026_08_16_000003_add_lifecycle_and_review_fields_to_products.php',
            'database/migrations/2026_08_16_000003_add_post_purchase_payment_state_and_customer_insurance_balances.php',
            'database/migrations/2026_08_16_000004_complete_shipping_refund_and_proof_contract.php',
        ];

        foreach ($migrationFiles as $path) {
            self::assertFileExists($this->absoluteRuntime($path), $path);
        }

        $schedule = $this->source('bootstrap/app.php');
        self::assertStringContainsString("post-purchase-invoices:expire", $schedule);
        self::assertStringContainsString("commerce:release-matured-refunds", $schedule);

        $postPurchase = $this->source('app/Services/PostPurchaseInvoiceService.php');
        self::assertStringContainsString("public const CONTRACT_VERSION = 'post_purchase_v3'", $postPurchase);
        self::assertStringContainsString('createForPaidOrderGroup', $postPurchase);
        self::assertStringContainsString('applyCustomerInsuranceBalance', $postPurchase);
        self::assertStringContainsString('releaseToSeller', $postPurchase);

        $visibility = $this->source('app/Services/SellerOrderVisibilityService.php');
        self::assertStringContainsString('canSellerView', $visibility);
        self::assertStringContainsString('ORDER_STATUS_RELEASED', $visibility);

        $refund = $this->source('app/Services/PostPaymentRefundService.php');
        self::assertStringContainsString('idempotency_key', $refund);
        self::assertStringContainsString('customer_purchase_balance', $refund);
        self::assertStringContainsString('customer_insurance_balance', $refund);
        self::assertStringContainsString('releaseMatured', $refund);

        $calendar = $this->source('app/Services/BusinessCalendarService.php');
        self::assertStringContainsString('business_calendar_holidays', $calendar);
    }

    public function test_mobile_and_web_contracts_hide_order_data_until_allowed_and_expose_required_actions(): void
    {
        $sellerRoutes = $this->source('routes/rest_api/v3/seller.php');
        self::assertStringContainsString("Route::get('{order}/shipping-proofs'", $sellerRoutes);
        self::assertStringContainsString("Route::post('{order}/shipping-proofs'", $sellerRoutes);

        $customerRoutes = $this->source('routes/rest_api/v1/api.php');
        self::assertStringContainsString("customer/post-purchase-invoices", $customerRoutes);
        self::assertStringContainsString("customer/order-insurance", $customerRoutes);
        self::assertStringContainsString("customer/post-payment-refunds", $customerRoutes);

        $sellerOrderController = $this->source('app/Http/Controllers/RestAPI/v3/seller/OrderController.php');
        self::assertStringContainsString('SellerOrderInsuranceService', $sellerOrderController);
        self::assertStringContainsString('OrderCommerceContractService', $sellerOrderController);
        self::assertStringContainsString('seller_order_insurance_payment_required_before_order_details', $sellerOrderController);
        self::assertStringContainsString('shipping_assignment', $sellerOrderController);
        self::assertStringContainsString('proof_upload_endpoint', $sellerOrderController);

        $sellerInsuranceController = $this->source('app/Http/Controllers/RestAPI/v3/seller/SellerOrderInsuranceController.php');
        $sellerInsuranceService = $this->source('app/Services/SellerOrderInsuranceService.php');
        self::assertStringContainsString('details_hidden', $sellerInsuranceService);
        self::assertStringContainsString('can_view_order_details', $sellerInsuranceService);
        self::assertStringContainsString('restricted_access_token', $sellerInsuranceController);
        self::assertStringContainsString('seller_order_insurance_credit', $sellerInsuranceController);

        $adminRoutes = $this->source('routes/admin/routes.php');
        $adminShippingPartial = $this->source('resources/views/admin-views/order/partials/_shipping-settlement-refund.blade.php');
        self::assertStringContainsString('shipping-allocation', $adminShippingPartial);
        self::assertStringContainsString('shipping-proofs.review', $adminShippingPartial);
        self::assertStringContainsString('stock-out-refund', $adminShippingPartial);
        self::assertStringContainsString('stock-out-refund', $adminRoutes);

        $vendorAppConstants = $this->read('E:/project/vendor-app-main/lib/utill/app_constants.dart', 'vendor-app-main/lib/utill/app_constants.dart');
        self::assertStringContainsString('sellerOrderInsuranceSuffix', $vendorAppConstants);
        self::assertStringContainsString('insurancePendingOrdersUri', $vendorAppConstants);
        self::assertStringContainsString("String.fromEnvironment", $vendorAppConstants);

        $vendorGate = $this->read('E:/project/vendor-app-main/lib/features/order_details/widgets/seller_order_insurance_gate_widget.dart', 'vendor-app-main insurance gate');
        self::assertStringContainsString('details_hidden', $this->read('E:/project/vendor-app-main/lib/features/order_details/domain/models/seller_order_insurance_model.dart', 'vendor-app-main insurance model'));
        self::assertStringContainsString('seller_order_details_hidden_until_insurance_paid', $vendorGate);
        self::assertStringContainsString('seller_order_insurance_credit', $vendorGate);

        $customerConstants = $this->read('E:/project/user-app-main/lib/utill/app_constants.dart', 'user-app-main/lib/utill/app_constants.dart');
        self::assertStringContainsString('customerOrderInsuranceUri', $customerConstants);
        self::assertStringContainsString('customerOrderInsuranceSupportUri', $customerConstants);
        self::assertStringContainsString('String.fromEnvironment', $customerConstants);

        $customerInsuranceScreen = $this->read('E:/project/user-app-main/lib/features/order_insurance/screens/customer_order_insurance_screen.dart', 'user-app-main insurance screen');
        self::assertStringContainsString('tax_amount_due', $customerInsuranceScreen);
        self::assertStringContainsString('insurance_amount_due', $customerInsuranceScreen);
        self::assertStringContainsString('contact_admin_support', $customerInsuranceScreen);
    }

    public function test_release_tooling_and_captcha_shutdown_are_ready_for_manual_acceptance(): void
    {
        $sync = $this->read('E:/project/scripts/sync-runtime-merge-to-xampp.ps1', 'scripts/sync-runtime-merge-to-xampp.ps1');
        self::assertStringContainsString('runtime-merge\\ba', $sync);
        self::assertStringContainsString('/XJ', $sync);
        self::assertStringContainsString('*.log', $sync);

        $readiness = $this->read('E:/project/scripts/run-n7-release-readiness.ps1', 'scripts/run-n7-release-readiness.ps1');
        self::assertStringContainsString('StageN7ReleaseReadinessContractTest', $readiness);
        self::assertStringContainsString('flutter analyze', $readiness);
        self::assertStringContainsString('schedule:list', $readiness);

        $recaptchaService = $this->source('app/Services/RecaptchaService.php');
        self::assertStringNotContainsString('google.com/recaptcha', $recaptchaService);
        self::assertStringNotContainsString('Http::asForm()', $recaptchaService);

        foreach (['E:/project/vendor-app-main/lib', 'E:/project/user-app-main/lib'] as $path) {
            self::assertFalse(
                $this->directoryContainsAbsolute($path, '/captcha|recaptcha/i'),
                "{$path} must not contain functional CAPTCHA code."
            );
        }
    }

    private function source(string $path): string
    {
        return $this->read($this->absoluteRuntime($path), $path);
    }

    private function externalSource(string $path): string
    {
        return $this->read($this->absoluteWorkspace($path), $path);
    }

    private function absoluteRuntime(string $path): string
    {
        return $this->runtimeBase().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function absoluteWorkspace(string $path): string
    {
        return $this->workspaceCompatibilityBase().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function runtimeBase(): string
    {
        $current = dirname(__DIR__, 2);
        $prepared = dirname($current).DIRECTORY_SEPARATOR.'runtime-merge'.DIRECTORY_SEPARATOR.'ba';

        return is_file($prepared.DIRECTORY_SEPARATOR.'artisan') ? $prepared : $current;
    }

    private function workspaceCompatibilityBase(): string
    {
        $current = dirname(__DIR__, 2);
        $parent = basename(dirname($current));

        return $parent === 'runtime-merge'
            ? dirname(dirname($current)).DIRECTORY_SEPARATOR.'ba'
            : $current;
    }

    private function read(string $absolutePath, string $label): string
    {
        $contents = file_get_contents($absolutePath);
        self::assertNotFalse($contents, "Could not read {$label}");

        return $contents;
    }

    private function directoryContains(string $path, string $pattern): bool
    {
        $base = $this->absoluteWorkspace($path);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'dart') {
                if (preg_match($pattern, (string) file_get_contents($file->getPathname()))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function directoryContainsAbsolute(string $path, string $pattern): bool
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'dart'
                && preg_match($pattern, (string) file_get_contents($file->getPathname()))) {
                return true;
            }
        }
        return false;
    }
}
