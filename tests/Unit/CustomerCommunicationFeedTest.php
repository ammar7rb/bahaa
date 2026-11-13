<?php

namespace Tests\Unit;

use App\Http\Controllers\Customer\CommunicationController;
use App\Models\Order;
use App\Models\SupportTicketConv;
use App\Models\User;
use App\Services\AdministrativeReferenceService;
use App\Services\CustomerCommunicationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerCommunicationFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY', 'public');
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->nullable(); $t->string('customer_event_key')->nullable()->unique();
            $t->string('sent_to'); $t->string('sent_by'); $t->string('title'); $t->text('description');
            $t->string('image')->default(''); $t->integer('status')->default(1); $t->integer('notification_count')->default(1); $t->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->string('subject'); $t->text('description');
            $t->string('status'); $t->string('purpose')->nullable(); $t->timestamp('hidden_from_subject_at')->nullable(); $t->timestamps();
        });
        Schema::create('support_ticket_convs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('support_ticket_id'); $t->unsignedBigInteger('admin_id')->nullable();
            $t->text('admin_message')->nullable(); $t->text('customer_message')->nullable(); $t->timestamp('customer_hidden_at')->nullable(); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id'); $t->boolean('is_guest')->default(false); $t->string('order_status'); $t->timestamps();
        });
        $user = new User(); $user->id = 1; auth('customer')->setUser($user);
        Schema::create('notification_seens', function (Blueprint $t) {
            $t->id(); $t->integer('user_id')->nullable(); $t->integer('notification_id'); $t->timestamps();
        });
    }

    private function ticket(int $id, int $customer, bool $hidden = false): void
    {
        DB::table('support_tickets')->insert(['id' => $id, 'customer_id' => $customer, 'subject' => 'طلب دعم',
            'description' => 'وصف المشكلة', 'status' => 'open', 'hidden_from_subject_at' => $hidden ? now() : null,
            'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_mobile_customer_reply_is_owned_validated_and_saved(): void
    {
        Schema::table('support_ticket_convs', fn (Blueprint $t) => $t->text('attachment')->nullable());
        $this->ticket(1, 1); $this->ticket(2, 2);
        $user = new User(); $user->id = 1;
        $request = \Illuminate\Http\Request::create('/', 'POST', ['message' => 'رد من العميل']);
        $request->setUserResolver(fn () => $user);
        $controller = app(\App\Http\Controllers\RestAPI\v1\CustomerController::class);
        $this->assertSame(404, $controller->reply_support_ticket($request, 2)->getStatusCode());
        $this->assertSame(200, $controller->reply_support_ticket($request, 1)->getStatusCode());
        $this->assertDatabaseHas('support_ticket_convs', ['support_ticket_id' => 1, 'customer_message' => 'رد من العميل']);
        $empty = \Illuminate\Http\Request::create('/', 'POST', ['message' => '   ']);
        $empty->setUserResolver(fn () => $user);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->reply_support_ticket($empty, 1);
    }

    public function test_badge_counts_only_unread_admin_replies_and_preserves_waiting_conversations(): void
    {
        $this->ticket(1, 1);
        $service = app(CustomerCommunicationService::class);
        SupportTicketConv::create(['support_ticket_id'=>1, 'customer_message'=>'طلب العميل']);
        $this->assertSame(0, $service->unreadMessageCount(1));
        $this->assertSame(0, $service->notifications(1)->count());
        $this->assertSame(1, $service->messages(1)->count());
        SupportTicketConv::create(['support_ticket_id'=>1, 'admin_id'=>1, 'admin_message'=>'رد الإدارة']);
        $this->assertSame(1, $service->unreadMessageCount(1));
        $this->assertSame(1, $service->unreadNotifications(1)->count());
        $loaded = $service->messages(1)->with('conversations')->first();
        $service->markSupportRepliesRead($loaded, 2);
        $this->assertSame(1, $service->unreadMessageCount(1));
        $service->markSupportRepliesRead($loaded, 1);
        $this->assertSame(0, $service->unreadMessageCount(1));
        $this->assertSame(0, $service->unreadNotifications(1)->count());
        SupportTicketConv::create(['support_ticket_id'=>1, 'admin_id'=>1, 'admin_message'=>'رد جديد']);
        $service->markSupportRepliesRead($loaded, 1);
        $this->assertSame(1, $service->unreadMessageCount(1));
        $this->assertSame(1, $service->unreadNotifications(1)->count());
    }

    public function test_urgent_package_notice_links_to_subscription_only_for_order_owner(): void
    {
        DB::table('orders')->insert(['id'=>12,'customer_id'=>1,'order_status'=>'pending']);
        $notice = new \App\Models\Notification();
        $notice->setRawAttributes(['customer_event_key'=>'initial-order-12-requirements','title'=>'اختيار باقة','description'=>'يلزم الاشتراك في باقة'],true);
        $service = app(CustomerCommunicationService::class);
        $this->assertSame(['type'=>'subscription','id'=>12],$service->notificationItem($notice,1)['target']);
        $this->assertSame(route('customer.subscription.index'),$service->notificationItem($notice,1)['url']);
        $this->assertNull($service->notificationItem($notice,2)['target']);
    }

    public function test_reference_format_preserves_internal_ids_and_old_lookup(): void
    {
        $this->assertSame('C000012', AdministrativeReferenceService::format('customer', 12));
        $this->assertSame('M000012', AdministrativeReferenceService::format('seller', 12));
        $this->assertSame(['type' => 'users', 'id' => 12], AdministrativeReferenceService::parse('C000012'));
        $this->assertSame(['type' => 'sellers', 'id' => 12], AdministrativeReferenceService::parse('M12'));
        $this->assertSame('C1000000', AdministrativeReferenceService::format('customer', 1000000));
        $user = new User(); $user->id = 12;
        $this->assertSame('C000012', $user->administrative_reference);
        $this->assertSame(12, $user->id);
        $legacyUser = new \App\User(); $legacyUser->id = 12;
        $this->assertSame('C000012', $legacyUser->administrative_reference);
    }

    public function test_reply_is_persisted_once_and_feed_is_scoped_to_owner(): void
    {
        $this->ticket(1, 1); $this->ticket(2, 2); $this->ticket(3, 1, true);
        $reply = SupportTicketConv::create(['support_ticket_id' => 1, 'admin_id' => 1, 'admin_message' => 'رد الإدارة']);
        app(CustomerCommunicationService::class)->recordSupportReply($reply);
        SupportTicketConv::create(['support_ticket_id' => 2, 'admin_id' => 1, 'admin_message' => 'رد خاص بعميل آخر']);
        SupportTicketConv::create(['support_ticket_id' => 3, 'admin_id' => 1, 'admin_message' => 'محادثة مخفية']);
        $response = app(CommunicationController::class)->index(Request::create('/customer/communication'), app(CustomerCommunicationService::class));
        $data = $response->getData(true);
        $this->assertSame(1, $data['notifications']['total']);
        $this->assertSame(1, $data['messages']['total']);
        $this->assertSame('رد الإدارة', $data['messages']['items'][0]['description']);
        $this->assertSame(['type' => 'message', 'id' => 1], $data['notifications']['items'][0]['target']);
        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_order_notice_links_only_to_own_order_and_deduplicates_event(): void
    {
        DB::table('orders')->insert(['id' => 8, 'customer_id' => 1, 'is_guest' => 0, 'order_status' => 'processing', 'updated_at' => now()]);
        $service = app(CustomerCommunicationService::class);
        $order = Order::find(8);
        $service->recordOrderStatus($order); $service->recordOrderStatus($order);
        $notice = $service->notifications(1)->first();
        $this->assertSame(['type' => 'order', 'id' => 8], $service->notificationItem($notice, 1)['target']);
        $this->assertStringContainsString('id=8', $service->notificationItem($notice, 1)['url']);
        $this->assertNull($service->notificationItem($notice, 2)['target']);
        $this->assertSame(1, DB::table('notifications')->count());
        $service->recordOrderStatus(['id' => 8, 'customer_id' => 1, 'is_guest' => 0, 'order_status' => 'delivered', 'updated_at' => '2026-10-13 12:00:00']);
        $this->assertSame(2, DB::table('notifications')->count());
    }

    public function test_full_message_feed_paginates_and_rejects_unauthenticated_access(): void
    {
        for ($id = 1; $id <= 22; $id++) $this->ticket($id, 1);
        $response = app(CommunicationController::class)->index(Request::create('/customer/communication', 'GET', ['type' => 'messages', 'page' => 2]), app(CustomerCommunicationService::class));
        $data = $response->getData(true);
        $this->assertSame(22, $data['total']); $this->assertCount(2, $data['items']);
        $this->assertContains('customer', app('router')->getRoutes()->getByName('customer.communication.index')->gatherMiddleware());
        auth('customer')->forgetUser();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CommunicationController::class)->index(Request::create('/customer/communication'), app(CustomerCommunicationService::class));
    }

    public function test_profile_header_renders_owned_identity_and_both_preview_links(): void
    {
        $request = Request::create('/user-profile');
        app()->instance('request', $request);
        session()->put('local', 'ar'); session()->put('direction', 'rtl');
        $user = new \App\User(); $user->id = 12; $user->f_name = 'أحمد'; $user->l_name = 'محمد';
        $user->setRelation('storage', collect()); auth('customer')->setUser($user);
        $header = view('customer-account.profile-header')->render();
        $this->assertStringContainsString('C000012', $header);
        $this->assertStringContainsString('data-communication-type="messages"', $header);
        $this->assertStringContainsString('data-communication-type="notifications"', $header);
        $this->assertStringContainsString('عرض الكل', $header);
        $this->assertStringContainsString(route('customer.messages.index'), $header);
        $this->assertStringContainsString(route('customer.notifications.index'), $header);
        // Isolated visual fixture: the real Blade header + shipped CSS/JS, no live customer data.
        $header = str_replace('http://localhost', 'http://127.0.0.1:8768', $header);
        $directory = base_path('output/customer-phase12');
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        file_put_contents($directory.'/header-preview.html', '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/front-end/css/customer-profile-communication.css"><style>body{margin:0;background:#f4f7fb;color:#172942;font-family:Arial,sans-serif}.container{max-width:1100px;margin:auto;padding-inline:16px;box-sizing:border-box}a{color:#1a428a}svg{width:22px;height:22px}main{padding:24px}button{min-height:44px}</style></head><body>'.$header.'<main><h1>الملف الشخصي</h1><p>معاينة معزولة للهيدر؛ بيانات الاختبار ليست حساباً فعلياً.</p></main><script src="/assets/front-end/js/customer-profile-communication.js"></script></body></html>');
    }

    public function test_open_chat_feed_receives_new_replies_and_does_not_cache_them(): void
    {
        $this->ticket(1, 1);
        $controller = app(\App\Http\Controllers\Web\UserProfileController::class);
        $this->assertCount(0, $controller->ticketMessages(1)->getData(true)['messages']);
        SupportTicketConv::create(['support_ticket_id' => 1, 'admin_id' => 1, 'admin_message' => 'رسالة جديدة']);
        $response = $controller->ticketMessages(1);
        $message = $response->getData(true)['messages'][0];
        $this->assertSame('رسالة جديدة', $message['body']);
        $this->assertSame('admin', $message['sender_type']);
        $this->assertSame([], $message['attachments']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_open_chat_feed_rejects_another_customers_ticket(): void
    {
        $this->ticket(2, 2);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\App\Http\Controllers\Web\UserProfileController::class)->ticketMessages(2);
    }

    public function test_activation_banner_is_absent_from_all_conversations(): void
    {
        session()->put('local', 'ar');
        $activation = \Mockery::mock(\App\Services\CustomerActivationService::class);
        $activation->shouldReceive('payload')->andReturn(['banner' => ['visible' => true, 'message' => 'بانتظار التفعيل']]);
        app()->instance(\App\Services\CustomerActivationService::class, $activation);
        $request = Request::create('/support-ticket/1');
        $route = new \Illuminate\Routing\Route('GET', 'support-ticket/{id}', fn () => null);
        $route->name('support-ticket.index');
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
        $view = 'web-views.users-profile.partials.customer-activation-banner';
        $this->assertStringNotContainsString('id="customer-activation-notice"', view($view, ['conversation' => (object) ['purpose' => 'account_activation']])->render());
        $this->assertStringNotContainsString('id="customer-activation-notice"', view($view, ['conversation' => (object) ['purpose' => 'problem_report']])->render());
    }
}
