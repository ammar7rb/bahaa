<?php

namespace Tests\Unit;

use App\Models\CompanyMedicalDevice;
use App\Models\MedicalDeviceReportPurchase;
use App\Models\SupportTicketConv;
use App\Models\User;
use App\Services\MedicalDeviceReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MedicalDeviceReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->decimal('wallet_balance', 24, 3); $t->boolean('is_active')->default(true); $t->timestamp('account_deleted_at')->nullable(); $t->timestamps(); });
        Schema::create('wallet_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->string('transaction_id')->unique(); $t->string('reference'); $t->string('transaction_type'); $t->string('payment_method');
            foreach (['credit','debit','admin_bonus','balance'] as $field) $t->decimal($field, 24, 3)->default(0); $t->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('subject'); $t->text('description'); $t->string('type'); $t->string('purpose')->nullable(); $t->string('priority'); $t->string('status');
            $t->timestamp('closed_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamp('hidden_from_subject_at')->nullable(); $t->timestamps();
        });
        Schema::create('support_ticket_convs', function (Blueprint $t) {
            $t->id(); $t->integer('support_ticket_id'); $t->integer('admin_id')->nullable(); $t->text('customer_message')->nullable(); $t->text('admin_message')->nullable(); $t->json('attachment')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_25_000001_create_company_medical_devices.php'))->up();
        (require base_path('database/migrations/2026_11_04_120000_add_medical_device_reports.php'))->up();
        DB::table('users')->insert([['id'=>1,'wallet_balance'=>100], ['id'=>2,'wallet_balance'=>10]]);
        CompanyMedicalDevice::create(['id'=>1,'name'=>'جهاز','is_published'=>1,'report_price'=>35,'price'=>500,'device_condition'=>'new','origin_country'=>'EG']);
    }

    public function test_payment_is_per_device_and_does_not_charge_twice(): void
    {
        $service = app(MedicalDeviceReportService::class);
        $first = $service->pay(1, 1, 35);
        $this->assertSame($first->id, $service->pay(1, 1, 35)->id);
        $this->assertEquals(65, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $this->assertSame(1, DB::table('medical_device_inquiries')->count());
        $this->assertEquals(35, $first->amount);
        $this->assertNotNull($service->requestPurchase(1, 1)->purchase_requested_at);
        $service->requestPurchase(1, 1);
        $this->assertSame(1, SupportTicketConv::where('customer_message', 'like', '%شراء%')->count());
    }

    public function test_insufficient_balance_cannot_create_inquiry_or_purchase(): void
    {
        try { app(MedicalDeviceReportService::class)->pay(1, 2, 35); $this->fail('Insufficient balance accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('medical_report', $e->errors()); }
        $this->assertSame(0, MedicalDeviceReportPurchase::count());
        $this->assertSame(0, DB::table('medical_device_inquiries')->count());
        $this->assertEquals(10, DB::table('users')->where('id', 2)->value('wallet_balance'));
    }

    public function test_stale_price_and_another_device_never_grant_access(): void
    {
        $service = app(MedicalDeviceReportService::class);
        try { $service->pay(1, 1, 30); $this->fail('Changed quote accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('medical_report', $e->errors()); }
        $this->assertEquals(100, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $service->pay(1, 1, 35);
        CompanyMedicalDevice::create(['name'=>'جهاز آخر', 'is_published'=>1, 'report_price'=>20]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->requestPurchase(2, 1);
    }

    public function test_report_is_private_to_the_paying_customer(): void
    {
        Storage::fake('local');
        $service = app(MedicalDeviceReportService::class);
        $purchase = $service->pay(1, 1, 35);
        $inquiry = \App\Models\MedicalDeviceInquiry::findOrFail($purchase->inquiry_id);
        $file = UploadedFile::fake()->createWithContent('report.pdf', '%PDF-1.4 test report');
        $service->sendReport($inquiry, $file, 7);
        $reply = SupportTicketConv::where('support_ticket_id', $inquiry->support_ticket_id)->firstOrFail();
        $this->assertSame('medical-report', $reply->attachment[0]['storage']);
        $this->actingAs(User::findOrFail(1), 'customer');
        $response = app(\App\Http\Controllers\ChatAttachmentController::class)->customer($inquiry->support_ticket_id, (string) $reply->id, 0);
        $this->assertSame(200, $response->getStatusCode());
        $this->actingAs(User::findOrFail(2), 'customer');
        $api = \Illuminate\Http\Request::create('/api/v1/customer/support-ticket');
        $api->setUserResolver(fn () => User::findOrFail(1));
        $this->assertSame(200, app(\App\Http\Controllers\ChatAttachmentController::class)->customerApi($api, $inquiry->support_ticket_id, (string) $reply->id, 0)->getStatusCode());
        $api->setUserResolver(fn () => User::findOrFail(2));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\App\Http\Controllers\ChatAttachmentController::class)->customerApi($api, $inquiry->support_ticket_id, (string) $reply->id, 0);
    }

    public function test_mobile_report_download_requires_api_authentication(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($route) => str_contains($route->uri(), 'support-ticket/{id}/attachments/{message}/{index}/download') && str_starts_with($route->uri(), 'api/'));
        $this->assertNotNull($route);
        $this->assertContains('auth:api', $route->gatherMiddleware());
    }
}
