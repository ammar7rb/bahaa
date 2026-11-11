<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminOperationsRedistributionTest extends TestCase
{
    private function sidebar(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/admin/partials/v2/_side-bar.blade.php');
    }

    private function section(string $name): string
    {
        $source = $this->sidebar();
        $this->assertStringContainsString('data-section="'.$name.'"', $source);
        $rest = explode('data-section="'.$name.'"', $source, 2)[1];

        return preg_split('/<div class="v2-ctx-section|\{\{-- =+ [A-Z ]+ =+ --\}\}/', $rest, 2)[0];
    }

    public function test_operations_section_and_rail_button_are_gone(): void
    {
        $this->assertStringNotContainsString('data-section="operations"', $this->sidebar());
        $rail = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/admin/partials/v2/_rail.blade.php');
        $this->assertStringNotContainsString('data-section="operations"', $rail);
    }

    public function test_each_former_operations_link_lives_in_its_proper_section_exactly_once(): void
    {
        $expected = [
            'orders' => ["admin.orders.logistics.index", "admin.payment-reviews.index"],
            'catalog' => ["admin.operations.medical-devices.index"],
            'customers' => ["guest_customers"],
            'vendors' => ["seller-settlement-timing"]
        ];
        $source = $this->sidebar();
        foreach ($expected as $section => $needles) {
            $body = $this->section($section);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $body, "$needle should be in $section");
                $this->assertSame(1, substr_count($source, $needle === 'guest_customers' ? "data-item=\"guest-customers\"" : $needle) , "$needle should appear once in the whole sidebar");
            }
        }
    }

    public function test_guest_orders_are_an_order_center_filter(): void
    {
        $this->assertArrayHasKey('guests', \App\Support\OrderCenterFilters::OPTIONS);
        $this->assertSame('guests', \App\Support\OrderCenterFilters::normalize('guests'));
    }

    public function test_settings_items_live_inside_the_settings_page_not_as_separate_sidebar_entries(): void
    {
        $hub = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin-views/business-settings/hub.blade.php');
        $source = $this->sidebar();
        foreach (['admin.operations.shipping.index', 'admin.operations.homepage-sections.index', 'admin.customer-withdrawals.index', 'admin.monthly-packages.index', 'admin.customer.password-recovery.index'] as $route) {
            $this->assertStringContainsString("'".$route."'", $hub, "$route should be a card in the settings page");
            $this->assertStringNotContainsString("route('".$route."')", $source, "$route must not be a separate sidebar entry");
        }
        foreach (['admin.operations.medical-devices.index', 'admin.category.view', 'admin.brand.list'] as $route) {
            $this->assertStringNotContainsString("'".$route."'", $hub);
        }
    }
}
