<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StageN5N6ShippingRefundContractTest extends TestCase
{
    public function test_shipping_contract_has_two_options_and_immutable_business_day_promises(): void
    {
        $shipping = $this->source('app/Services/CustomerThreeStepShippingService.php');
        $promise = $this->source('app/Services/ShippingPromiseService.php');
        $calendar = $this->source('app/Services/BusinessCalendarService.php');
        $manager = $this->source('app/Utils/OrderManager.php');

        self::assertStringContainsString("return ['sigma', 'normal']", $shipping);
        self::assertStringNotContainsString("return ['same_day', 'next_day', 'normal']", $shipping);
        self::assertStringContainsString("['mode' => 'fixed', 'min' => 6, 'max' => 6]", $promise);
        self::assertStringContainsString("['mode' => 'fixed', 'min' => 10, 'max' => 10]", $promise);
        self::assertStringContainsString('shipping_duration_snapshot', $promise);
        self::assertStringContainsString('business_calendar_holidays', $calendar);
        self::assertStringContainsString('shipping_promise_key', $manager);
        self::assertStringContainsString('shipping_eta_from', $manager);
        self::assertStringContainsString('shipping_eta_to', $manager);
    }

    public function test_shipping_settlement_and_private_proof_are_connected_to_all_channels(): void
    {
        $adminRoutes = $this->source('routes/admin/routes.php');
        $vendorRoutes = $this->source('routes/vendor/routes.php');
        $apiRoutes = $this->source('routes/rest_api/v3/seller.php');
        $proofService = $this->source('app/Services/OrderShippingProofService.php');
        $vendorRepository = $this->source('../Vendor app/lib/features/order_details/domain/repositories/order_details_repository.dart');

        self::assertStringContainsString('shipping-allocation', $adminRoutes);
        self::assertStringContainsString('stock-out-refund', $adminRoutes);
        self::assertStringContainsString('post-payment-refunds', $adminRoutes);
        self::assertStringContainsString('shipping-proof.store', $vendorRoutes);
        self::assertStringContainsString("'{order}/shipping-proofs'", $apiRoutes);
        self::assertStringContainsString("'pending' => ['ready_to_ship']", $proofService);
        self::assertStringContainsString("'ready_to_ship' => ['shipped']", $proofService);
        self::assertStringContainsString("'shipped' => ['delivered']", $proofService);
        self::assertStringContainsString('MultipartFile.fromFile', $vendorRepository);
    }

    public function test_customer_app_uses_structured_text_address_and_insurance_notice(): void
    {
        $addressModel = $this->source('../user-app/lib/features/address/domain/models/address_model.dart');
        $addressScreen = $this->source('../user-app/lib/features/address/screens/add_new_address_screen.dart');
        $dashboard = $this->source('../user-app/lib/features/dashboard/screens/dashboard_screen.dart');
        $shippingRepository = $this->source('../user-app/lib/features/shipping/domain/repositories/shipping_repository.dart');
        $shippingDetails = $this->source('../user-app/lib/features/checkout/widgets/shipping_details_widget.dart');
        $defaultCheckout = $this->source('resources/themes/default/web-views/checkout/shipping.blade.php');
        $asterCheckout = $this->source('resources/themes/theme_aster/theme-views/checkout/shipping.blade.php');

        foreach (['state', 'district', 'area', 'street', 'building_number', 'landmark'] as $field) {
            self::assertStringContainsString("data['{$field}']", $addressModel);
        }
        self::assertStringContainsString('shippingGovernorates', $addressScreen);
        self::assertStringContainsString("latitude: '0'", $addressScreen);
        self::assertStringContainsString("longitude: '0'", $addressScreen);
        self::assertStringContainsString('insurance_notice_dismissed_', $dashboard);
        self::assertStringContainsString('insuranceBalanceNotice', $dashboard);
        self::assertStringContainsString('shippingQuoteForAddress', $shippingRepository);
        self::assertStringContainsString('selectShippingQuoteForAddress', $shippingRepository);
        self::assertStringContainsString('selectAddressQuote', $shippingDetails);
        self::assertStringContainsString('name="building_number"', $defaultCheckout);
        self::assertStringContainsString('name="building_number"', $asterCheckout);
    }

    public function test_stock_out_refund_is_idempotent_and_keeps_wallet_purposes_separate(): void
    {
        $refund = $this->source('app/Services/PostPaymentRefundService.php');
        $migration = $this->source('database/migrations/2026_08_16_000004_complete_shipping_refund_and_proof_contract.php');

        self::assertStringContainsString('idempotency_key', $refund);
        self::assertStringContainsString('customer_purchase_balances', $migration);
        self::assertStringContainsString('customer_insurance_balances', $refund);
        self::assertStringContainsString("'withdrawable' => false", $refund);
        self::assertStringContainsString("'status' => 'held'", $refund);
        self::assertStringContainsString('releaseMatured', $refund);
        self::assertStringContainsString('post_payment_stock_out_refund_scheduled', $refund);
        self::assertStringContainsString('post_payment_refund_available', $refund);
        self::assertStringContainsString("'purchase_wallet' => 'customer_purchase_balance'", $refund);
        self::assertStringContainsString("'insurance_wallet' => 'customer_insurance_balance'", $refund);
    }

    public function test_authentication_captcha_is_removed_from_platform_and_apps(): void
    {
        $service = $this->source('app/Services/RecaptchaService.php');
        self::assertStringNotContainsString('google.com/recaptcha', $service);
        self::assertStringNotContainsString('Http::asForm()', $service);
        $trait = $this->source('app/Traits/RecaptchaTrait.php');
        self::assertStringNotContainsString('google.com/recaptcha', $trait);
        self::assertStringNotContainsString('Http::asForm()', $trait);

        foreach ([
            'resources/views/admin-views/auth/login.blade.php',
            'resources/views/vendor-views/auth/login.blade.php',
            'resources/themes/default/web-views/customer-views/auth/register.blade.php',
            'resources/themes/default/web-views/seller-view/auth/partial/vendor-information-form.blade.php',
            'resources/themes/theme_aster/theme-views/layouts/partials/modal/_register.blade.php',
            'resources/themes/theme_aster/theme-views/seller-views/auth/partial/vendor-information-form.blade.php',
        ] as $view) {
            $contents = $this->source($view);
            self::assertStringNotContainsString('g-recaptcha-response', $contents, $view);
            self::assertStringNotContainsString('default-captcha-container', $contents, $view);
        }

        $appCaptcha = $this->directoryContains('../user-app/lib', '/captcha|recaptcha/i')
            || $this->directoryContains('../Vendor app/lib', '/captcha|recaptcha/i');
        self::assertFalse($appCaptcha, 'Application source must not contain CAPTCHA integrations.');
    }

    private function source(string $path): string
    {
        $contents = file_get_contents($this->resolvePath($path));
        self::assertNotFalse($contents, "Could not read {$path}");

        return $contents;
    }

    private function directoryContains(string $path, string $pattern): bool
    {
        $base = $this->resolvePath($path);
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

    private function resolvePath(string $path): string
    {
        $project = dirname(__DIR__, 2);
        $legacy = $project.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (file_exists($legacy)) {
            return $legacy;
        }

        $workspace = dirname($project, 3);
        $mapped = str_starts_with($path, '../Vendor app/')
            ? $workspace.DIRECTORY_SEPARATOR.'new app-20260716T132511Z-1-001'.DIRECTORY_SEPARATOR.'new app'.DIRECTORY_SEPARATOR.'Vendor app'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr($path, strlen('../Vendor app/')))
            : $workspace.DIRECTORY_SEPARATOR.'user-app-main'.DIRECTORY_SEPARATOR.'user-app-main'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr($path, strlen('../user-app/')));

        self::assertFileExists($mapped, "Could not resolve application path {$path}");

        return $mapped;
    }
}
