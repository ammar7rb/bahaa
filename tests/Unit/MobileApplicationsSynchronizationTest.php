<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class MobileApplicationsSynchronizationTest extends TestCase
{
    private string $vendorApp;
    private string $customerApp;

    protected function setUp(): void
    {
        parent::setUp();
        $workspace = rtrim((string) env('MOBILE_APPS_WORKSPACE', dirname(base_path(), 3).'\project'), '\\/');
        $this->vendorApp = $workspace.'\vendor-app-main';
        $this->customerApp = $workspace.'\user-app-main';

        if (!is_dir($this->vendorApp) || !is_dir($this->customerApp)) {
            $this->markTestSkipped('Mobile application sources are not available in this environment.');
        }
    }

    public function test_both_apps_use_environment_configurable_base_urls(): void
    {
        foreach ([$this->vendorApp, $this->customerApp] as $app) {
            $constants = $this->read($app.'\lib\utill\app_constants.dart');
            $this->assertStringContainsString("String.fromEnvironment(", $constants);
            $this->assertStringContainsString("'BASE_URL'", $constants);
            $this->assertStringNotContainsString('YOUR_BASE_URL_HERE', $constants);
            $this->assertDoesNotMatchRegularExpression('/192\.168\.\d+\.\d+/', $constants);
        }

        $this->assertEgyptPhoneAndMapContracts();
        $this->assertVendorApplicationContract();
        $this->assertCustomerApplicationContract();
        $this->assertTranslationKeyParity();
    }

    private function assertEgyptPhoneAndMapContracts(): void
    {
        foreach ([$this->vendorApp, $this->customerApp] as $app) {
            $this->assertDartDoesNotContain($app.'\lib', '+880');
            $this->assertDartContains($app.'\lib', "code?.toUpperCase() == 'EG'");
            $this->assertDartContains($app.'\lib', 'CameraTargetBounds(EgyptLocationHelper.bounds)');
            $this->assertDartContains($app.'\lib', "static const LatLng center = LatLng(26.8206, 30.8025)");
        }
    }

    private function assertVendorApplicationContract(): void
    {
        $this->assertDartContains($this->vendorApp.'\lib', "sellerOrderInsuranceSuffix = '/insurance'");
        $this->assertDartContains($this->vendorApp.'\lib', "'production_date'");
        $this->assertDartContains($this->vendorApp.'\lib', "'expiry_date'");
        $this->assertDartContains($this->vendorApp.'\lib', "policy_version_ids[");
        $this->assertDartDoesNotContain($this->vendorApp.'\lib', '/api/v3/seller/insurance/status');
        $this->assertDartDoesNotContain($this->vendorApp.'\lib', '/api/v3/seller/insurance/pay');
        $this->assertFileDoesNotExist($this->vendorApp.'\lib\features\seller_package\screens\seller_insurance_payment_screen.dart');
    }

    private function assertCustomerApplicationContract(): void
    {
        $this->assertSame([], glob($this->customerApp.'\lib\features\customer_packages\*\*.dart') ?: []);
        $this->assertDartDoesNotContain($this->customerApp.'\lib', 'purchase-packages');
        $this->assertDartContains($this->customerApp.'\lib', 'CustomerActivationBannerWidget');
        $this->assertDartContains($this->customerApp.'\lib', 'OrderInsuranceQuoteModel');
        $this->assertDartContains($this->customerApp.'\lib', 'confirmOrderReceiptUri');
        $this->assertDartContains($this->customerApp.'\lib', 'customerDeliveryConfirmationStatus');
        $this->assertDartContains($this->customerApp.'\lib', 'shipmentReference');
    }

    private function assertTranslationKeyParity(): void
    {
        foreach ([$this->vendorApp, $this->customerApp] as $app) {
            $english = json_decode($this->read($app.'\assets\language\en.json'), true, flags: JSON_THROW_ON_ERROR);
            $arabic = json_decode($this->read($app.'\assets\language\ar.json'), true, flags: JSON_THROW_ON_ERROR);
            $englishKeys = array_keys($english);
            $arabicKeys = array_keys($arabic);
            sort($englishKeys);
            sort($arabicKeys);
            $this->assertSame($englishKeys, $arabicKeys);
        }
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->fail('Unable to read '.$path);
        }
        return (string) $contents;
    }

    private function assertDartContains(string $directory, string $needle): void
    {
        foreach ($this->dartFiles($directory) as $file) {
            if (str_contains($this->read($file->getPathname()), $needle)) {
                $this->addToAssertionCount(1);
                return;
            }
        }
        $this->fail("Dart sources in {$directory} do not contain: {$needle}");
    }

    private function assertDartDoesNotContain(string $directory, string $needle): void
    {
        foreach ($this->dartFiles($directory) as $file) {
            if (str_contains($this->read($file->getPathname()), $needle)) {
                $this->fail("Unexpected '{$needle}' in {$file->getPathname()}");
            }
        }
        $this->addToAssertionCount(1);
    }

    private function dartFiles(string $directory): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'dart') {
                yield $file;
            }
        }
    }
}
