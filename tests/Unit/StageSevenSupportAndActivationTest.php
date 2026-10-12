<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StageSevenSupportAndActivationTest extends TestCase
{
    public function test_activation_and_support_printing_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.vendors.activation-tickets.reply'));
        $this->assertTrue(Route::has('admin.vendors.activation-tickets.print'));
        $this->assertTrue(Route::has('admin.support-ticket.print'));
    }

    public function test_customer_inbox_is_backed_by_administration_support_tickets(): void
    {
        $inbox = file_get_contents('E:/project/user-app-main/lib/features/chat/screens/inbox_screen.dart');
        $orderContact = file_get_contents('E:/project/user-app-main/lib/features/order_details/widgets/cal_chat_widget.dart');
        $router = file_get_contents('E:/project/user-app-main/lib/helper/route_healper.dart');
        $english = json_decode(file_get_contents('E:/project/user-app-main/assets/language/en.json'), true, flags: JSON_THROW_ON_ERROR);
        $arabic = json_decode(file_get_contents('E:/project/user-app-main/assets/language/ar.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('SupportTicketScreen', $inbox);
        $this->assertStringNotContainsString('ConversationListTabview', $inbox);
        $this->assertStringContainsString('getSupportTicketRoute', $orderContact);
        $this->assertStringNotContainsString('tel:', $orderContact);
        $this->assertStringContainsString('path: chatScreen', $router);
        $this->assertStringContainsString('builder: (context, state) => const SupportTicketScreen()', $router);
        $this->assertSame('Admin & Support Messages', $english['inbox']);
        $this->assertSame('رسائل الإدارة والدعم', $arabic['inbox']);
        $this->assertStringContainsString('administration messages', $english['to_communicate_with_vendors']);
        $this->assertStringContainsString('رسائل الإدارة', $arabic['to_communicate_with_vendors']);
    }

    public function test_direct_customer_and_seller_chat_endpoints_are_blocked(): void
    {
        $customerChat = file_get_contents(base_path('app/Http/Controllers/RestAPI/v1/ChatController.php'));
        $sellerApiChat = file_get_contents(base_path('app/Http/Controllers/RestAPI/v3/seller/ChatController.php'));
        $sellerWebChat = file_get_contents(base_path('app/Http/Controllers/Vendor/ChattingController.php'));

        $this->assertStringContainsString('please_open_a_support_ticket_to_contact_the_platform', $customerChat);
        $this->assertStringContainsString('direct_chat_is_disabled_use_admin_support', $sellerApiChat);
        $this->assertStringContainsString('direct_chat_is_disabled_use_admin_support', $sellerWebChat);
    }

    public function test_homepage_support_button_is_internal_in_both_themes(): void
    {
        $defaultTheme = file_get_contents(base_path('resources/themes/default/layouts/front-end/app.blade.php'));
        $asterTheme = file_get_contents(base_path('resources/themes/theme_aster/theme-views/layouts/app.blade.php'));
        $defaultProduct = file_get_contents(base_path('resources/themes/default/web-views/products/details.blade.php'));
        $asterProduct = file_get_contents(base_path('resources/themes/theme_aster/theme-views/product/details.blade.php'));

        foreach ([$defaultTheme, $asterTheme] as $theme) {
            $this->assertStringContainsString("route('account-tickets')", $theme);
            $this->assertStringContainsString('contact_technical_support', $theme);
            $this->assertStringNotContainsString('https://wa.me/', $theme);
        }
        $this->assertStringContainsString('contact_technical_support', $defaultProduct);
        $this->assertStringContainsString('contact_technical_support', $asterProduct);
        $this->assertStringNotContainsString("_chat-with-seller',['shop'", $asterProduct);
    }

    public function test_activation_conversation_has_attachments_printing_and_dashboard_shortcut(): void
    {
        $model = file_get_contents(base_path('app/Models/SellerActivationTicketMessage.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Admin/Vendor/SellerActivationTicketController.php'));
        $dashboard = file_get_contents(base_path('resources/views/admin-views/system/dashboard.blade.php'));
        $vendorLayout = file_get_contents(base_path('resources/views/layouts/vendor/partials/v2/_body.blade.php'));

        $this->assertStringContainsString('attachment_full_url', $model);
        $this->assertStringContainsString('attachments.*', $controller);
        $this->assertStringContainsString('public function print', $controller);
        $this->assertStringContainsString('seller-activation-inbox', $dashboard);
        $this->assertStringContainsString('activation-support-banner', $vendorLayout);
    }

    public function test_registration_images_are_optional_across_legacy_and_admin_flows(): void
    {
        $adminRequest = file_get_contents(base_path('app/Http/Requests/Admin/VendorAddRequest.php'));
        $legacyApi = file_get_contents(base_path('app/Http/Controllers/RestAPI/v2/seller/auth/RegisterController.php'));
        $shopService = file_get_contents(base_path('app/Services/ShopService.php'));

        $this->assertStringNotContainsString("rules: ['required']", $adminRequest);
        $this->assertStringContainsString("'image'         => 'nullable|mimes", $legacyApi);
        $this->assertStringContainsString("\$request->hasFile('logo')", $shopService);
        $this->assertStringContainsString(": 'def.png'", $shopService);
    }
}
