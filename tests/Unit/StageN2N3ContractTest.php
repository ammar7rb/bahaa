<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StageN2N3ContractTest extends TestCase
{
    public function test_n2_dashboard_uses_one_server_contract_for_web_and_vendor_app(): void
    {
        $service = $this->source('app/Services/SellerDashboardOverviewService.php');
        $apiRoutes = $this->source('routes/rest_api/v3/seller.php');
        $webDashboard = $this->source('resources/views/vendor-views/dashboard/partials/unified-overview.blade.php');
        $appConstants = $this->externalSource('../Vendor app/lib/utill/app_constants.dart');
        $appWidget = $this->externalSource('../Vendor app/lib/features/home/widgets/seller_dashboard_overview_widget.dart');

        foreach (['sales', 'products', 'advertisements', 'balances', 'issues', 'due_rows', 'quick_actions'] as $key) {
            self::assertStringContainsString("'{$key}' =>", $service);
        }
        self::assertStringContainsString("Route::get('dashboard-overview'", $apiRoutes);
        self::assertStringContainsString("sellerOverview['due_rows']", $webDashboard);
        self::assertStringContainsString('sellerDashboardOverviewUri', $appConstants);
        self::assertStringContainsString("data['due_rows']", $appWidget);
        self::assertStringContainsString("row['sales_amount']", $appWidget);
        self::assertStringContainsString("row['sales_due_at']", $appWidget);
        self::assertStringNotContainsString('calculate', strtolower($appWidget));
    }

    public function test_n3_ordinary_product_creation_has_no_package_or_insurance_gate(): void
    {
        foreach ([
            'app/Http/Controllers/Vendor/Product/ProductController.php',
            'app/Http/Controllers/Admin/Product/ProductController.php',
            'app/Http/Controllers/RestAPI/v2/seller/ProductController.php',
            'app/Http/Controllers/RestAPI/v3/seller/ProductController.php',
        ] as $path) {
            $source = $this->source($path);
            self::assertStringNotContainsString('assertCanReserve(', $source, $path);
            self::assertStringNotContainsString('reserveForProduct(', $source, $path);
            self::assertStringNotContainsString('activateForPublication(', $source, $path);
        }

        $setup = $this->source('app/Services/SellerDashboardSetupService.php');
        self::assertStringContainsString("'advertising_package_optional' => true", $setup);
        self::assertStringNotContainsString("return 'choose_package'", $setup);
        self::assertStringNotContainsString("return 'activate_insurance'", $setup);
    }

    public function test_n3_product_lifecycle_and_optional_measurement_contract_is_server_enforced(): void
    {
        $productSchemaMigrations = $this->source('database/migrations/2026_08_08_000003_add_product_specifications_to_products.php')
            . $this->source('database/migrations/2026_08_11_000003_add_product_package_governance.php');
        $addRequest = $this->source('app/Http/Requests/ProductAddRequest.php');
        $v3Controller = $this->source('app/Http/Controllers/RestAPI/v3/seller/ProductController.php');

        foreach (['production_date', 'expiry_date', 'length', 'width', 'height', 'weight'] as $field) {
            self::assertStringContainsString("'{$field}'", $productSchemaMigrations);
        }
        self::assertStringContainsString("'production_date' => 'required|date'", $addRequest);
        self::assertStringContainsString("'expiry_date' => 'required|date|after_or_equal:production_date|after:today'", $addRequest);
        self::assertStringContainsString("'length' => 'nullable|numeric|gt:0'", $addRequest);
        self::assertStringContainsString("'request_status' => 0", $v3Controller);
        self::assertStringContainsString('submittedImageCount < 5', $v3Controller);
    }

    public function test_n3_admin_review_decision_is_audited_and_reason_protected(): void
    {
        $routes = $this->source('routes/admin/routes.php');
        $controller = $this->source('app/Http/Controllers/Admin/Product/ProductController.php');
        $view = $this->source('resources/views/admin-views/product/view.blade.php');

        self::assertStringContainsString("Route::post('review-decision'", $routes);
        self::assertStringContainsString("'required_unless:decision,approve'", $controller);
        self::assertStringContainsString('ProductReviewDecision::create', $controller);
        self::assertStringContainsString("'approve' => ['request_status' => 1, 'status' => 1", $controller);
        self::assertStringContainsString('V{{ $product->user_id }}', $view);
        self::assertStringContainsString('reviewDecisions', $view);
    }

    private function source(string $path): string
    {
        return $this->read(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path), $path);
    }

    private function externalSource(string $path): string
    {
        $projectRelativePath = ltrim(str_replace(['../', '/'], ['', DIRECTORY_SEPARATOR], $path), DIRECTORY_SEPARATOR);
        $candidates = [
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path),
            'E:\\project'.DIRECTORY_SEPARATOR.$projectRelativePath,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $this->read($candidate, $path);
            }
        }

        self::fail("Could not read {$path}");
    }

    private function read(string $absolutePath, string $label): string
    {
        $contents = file_get_contents($absolutePath);
        self::assertNotFalse($contents, "Could not read {$label}");
        return $contents;
    }
}
