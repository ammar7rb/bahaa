<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccountActivationCenterContractTest extends TestCase
{
    public function test_stage_n0_contract_and_feature_flags_are_declared_safely(): void
    {
        $config = $this->source('config/account_workflows.php');
        $commerceMigration = $this->source('database/migrations/2026_08_16_000002_prepare_commerce_contract_v3.php');

        self::assertStringContainsString("'current_version' => env('COMMERCE_CONTRACT_VERSION', 'post_purchase_v3')", $config);
        self::assertStringContainsString("'post_purchase_tax_invoice_enabled' => (bool) env('POST_PURCHASE_TAX_INVOICE_ENABLED', false)", $config);
        self::assertStringContainsString("'configurable_shipping_promises_enabled' => (bool) env('CONFIGURABLE_SHIPPING_PROMISES_ENABLED', false)", $config);
        self::assertStringContainsString("'commerce_flow_version'", $commerceMigration);
        self::assertStringContainsString("Schema::create('post_purchase_invoices'", $commerceMigration);
        self::assertStringContainsString("'shipping_duration_snapshot'", $commerceMigration);
    }

    public function test_stage_n1_has_one_center_for_customers_and_sellers_with_private_evidence(): void
    {
        $migration = $this->source('database/migrations/2026_08_16_000001_create_unified_account_activation_center.php');
        $service = $this->source('app/Services/AccountActivationCenterService.php');
        $routes = $this->source('routes/admin/routes.php');

        foreach (['account_activation_cases', 'account_activation_profile_fields', 'account_activation_documents', 'account_activation_case_events'] as $table) {
            self::assertStringContainsString("Schema::create('{$table}'", $migration);
        }

        self::assertStringContainsString("'hidden_from_subject_at'", $migration);
        self::assertStringContainsString("Crypt::encryptString", $service);
        self::assertStringContainsString("'document_downloaded'", $service);
        self::assertStringContainsString("assertRequiredEvidenceIsVerified", $service);
        self::assertStringContainsString("prefix' => 'activation-center'", $routes);
        self::assertStringContainsString("middleware' => ['module:people']", $routes);
    }

    public function test_completed_conversations_are_hidden_from_customer_and_seller_apis(): void
    {
        $customerController = $this->source('app/Http/Controllers/RestAPI/v1/CustomerController.php');
        $supportTicket = $this->source('app/Models/SupportTicket.php');
        $sellerController = $this->source('app/Http/Controllers/RestAPI/v3/seller/auth/RegisterController.php');
        $sellerRoutes = $this->source('routes/rest_api/v3/seller.php');

        self::assertStringContainsString('visibleToSubject()', $customerController);
        self::assertStringContainsString("whereNull('hidden_from_subject_at')", $supportTicket);
        self::assertStringContainsString("whereNull('hidden_from_subject_at')", $sellerController);
        self::assertStringContainsString("activation-ticket/messages", $sellerRoutes);
        self::assertStringContainsString("activation-status", $sellerRoutes);
    }

    public function test_customer_and_seller_activation_payloads_are_wired_to_the_unified_case(): void
    {
        $customerService = $this->source('app/Services/CustomerActivationService.php');
        $sellerService = $this->source('app/Services/SellerActivationService.php');
        $customerController = $this->source('app/Http/Controllers/RestAPI/v1/CustomerController.php');

        self::assertStringContainsString("SUBJECT_CUSTOMER", $customerService);
        self::assertStringContainsString("'source_type' => 'support_ticket'", $customerService);
        self::assertStringContainsString("SUBJECT_SELLER", $sellerService);
        self::assertStringContainsString("'source_type' => 'seller_activation_ticket'", $sellerService);
        self::assertStringContainsString('$user->activation = $activationService->payload($getUser);', $customerController);
    }

    private function source(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($contents, "Could not read {$path}");

        return $contents;
    }
}
