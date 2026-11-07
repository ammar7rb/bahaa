<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Operations\HomepageSectionsController;
use App\Http\Controllers\Admin\Operations\MedicalDeviceController;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\CompanyMedicalDevice;
use App\Models\MedicalDeviceInquiry;
use App\Models\SupportTicket;
use App\Services\AdminWorkspaceService;
use App\Services\CustomerCommunicationService;
use App\Services\HomepageEditorialService;
use App\Services\HomepageSectionBuilderService;
use App\Services\MedicalDeviceInquiryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class OperationsCatalogTest extends HomepageSectionBuilderPhaseSixTest
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! defined('DOMAIN_POINTED_DIRECTORY')) {
            define('DOMAIN_POINTED_DIRECTORY', 'public');
        }
        config(['vendor_advertising.enabled' => false, 'vendor_product_workspace.enabled' => false]);
        (require base_path('database/migrations/2026_10_25_000001_create_company_medical_devices.php'))->up();
        (require base_path('database/migrations/2026_11_04_120000_add_medical_device_reports.php'))->up();
        Schema::create('business_settings', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->text('value');
        });
        foreach (['business_mode' => 'multi', 'product_brand' => '0', 'digital_product' => '0', 'language' => '[{"code":"en","status":1,"default":true}]'] as $type => $value) {
            DB::table('business_settings')->insert(compact('type', 'value'));
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('f_name')->nullable();
            $t->string('l_name')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('account_deleted_at')->nullable();
        });
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->string('subject');
            $t->text('description');
            $t->string('type');
            $t->string('purpose')->nullable();
            $t->string('priority');
            $t->string('status');
            $t->timestamp('hidden_from_subject_at')->nullable();
            $t->timestamp('admin_read_at')->nullable();
            $t->unsignedBigInteger('admin_read_message_id')->nullable();
            $t->timestamps();
        });
        Schema::create('support_ticket_convs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('support_ticket_id');
            $t->unsignedBigInteger('admin_id')->nullable();
            $t->text('customer_message')->nullable();
            $t->text('admin_message')->nullable();
            $t->timestamps();
        });
        Schema::create('sellers', function (Blueprint $t) {
            $t->id();
            $t->string('status');
            $t->string('f_name')->nullable();
            $t->string('l_name')->nullable();
            $t->string('email')->nullable();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('added_by');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->unsignedBigInteger('category_id')->nullable();
            $t->string('product_type')->default('physical');
            $t->integer('status')->default(1);
            $t->integer('request_status')->default(1);
            $t->integer('is_homepage_visible')->default(1);
            $t->integer('featured')->default(0);
            $t->integer('discount')->default(0);
            $t->timestamps();
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('delivery_man_id')->nullable();
            $t->integer('rating')->nullable();
            $t->integer('status')->default(1);
        });
        Schema::create('translations', function (Blueprint $t) {
            $t->id();
            $t->string('translationable_type');
            $t->unsignedBigInteger('translationable_id');
            $t->string('locale');
            $t->string('key');
            $t->text('value');
        });
        Schema::create('seller_product_promotions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('promotion_type');
            $t->string('status');
            $t->timestamp('expires_at')->nullable();
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('seller_id')->nullable();
            $t->string('name');
        });
        Schema::create('admin_roles', function (Blueprint $t) {
            $t->id();
            $t->string('module_access')->nullable();
            $t->integer('status')->default(1);
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->integer('position')->default(0);
        });
        Schema::create('stock_clearance_products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('setup_id');
            $t->string('added_by');
            $t->boolean('is_active');
        });
        Schema::create('stock_clearance_setups', function (Blueprint $t) {
            $t->id();
            $t->boolean('is_active');
            $t->date('duration_start_date');
            $t->date('duration_end_date');
            $t->string('offer_active_time');
            $t->time('offer_active_range_start')->nullable();
            $t->time('offer_active_range_end')->nullable();
        });
        Schema::create('order_details', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->integer('qty');
        });
        DB::table('users')->insert(['id' => 1, 'f_name' => 'Customer', 'phone' => '+201000000000', 'email' => 'customer@example.test']);
    }

    private function device(array $values = []): CompanyMedicalDevice
    {
        return CompanyMedicalDevice::create($values + ['name' => 'Ultrasound', 'is_published' => true, 'sort_order' => 10]);
    }

    private function products(): void
    {
        DB::table('sellers')->insert([['id' => 1, 'status' => 'approved'], ['id' => 2, 'status' => 'pending']]);
        foreach ([1 => [], 2 => ['status' => 0], 3 => ['request_status' => 0], 4 => ['is_homepage_visible' => 0], 5 => ['user_id' => 2], 6 => ['added_by' => 'admin'], 7 => []] as $id => $overrides) {
            DB::table('products')->insert($overrides + ['id' => $id, 'name' => 'Product '.$id, 'added_by' => 'seller', 'user_id' => 1]);
        }
    }

    public function test_inquiry_is_idempotent_links_customer_and_creates_no_outbound_message(): void
    {
        $device = $this->device();
        $service = app(MedicalDeviceInquiryService::class);
        $first = $service->submit($device->id, 1);
        $again = $service->submit($device->id, 1);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, MedicalDeviceInquiry::count());
        $this->assertSame(1, SupportTicket::count());
        $this->assertSame(1, $first->customer_id);
        $this->assertSame('Ultrasound', $first->device_name);
        $this->assertNull($first->conversation->admin_read_at);
        $this->assertStringContainsString('Ultrasound', $first->conversation->subject);
        $this->assertSame(0, DB::table('support_ticket_convs')->count());
        $this->assertSame($first->support_ticket_id, app(CustomerCommunicationService::class)->messages(1)->first()->id);
        $this->assertSame(0, app(CustomerCommunicationService::class)->messages(2)->count());
        $device->delete();
        $this->assertNull(CompanyMedicalDevice::find($device->id));
        $this->assertTrue($first->fresh()->device->trashed());
        $this->assertSame(1, SupportTicket::count());
    }

    public function test_unpublished_device_cannot_receive_requests(): void
    {
        $this->expectException(ModelNotFoundException::class);
        app(MedicalDeviceInquiryService::class)->submit($this->device(['is_published' => false])->id, 1);
    }

    public function test_publication_and_saved_order_are_enforced(): void
    {
        $a = $this->device(['sort_order' => 30]);
        $b = $this->device(['sort_order' => 5]);
        $this->device(['is_published' => false]);
        $this->assertSame([$b->id, $a->id], CompanyMedicalDevice::published()->pluck('id')->all());
        $b->update(['is_published' => false]);
        $this->assertSame([$a->id], CompanyMedicalDevice::published()->pluck('id')->all());
    }

    public function test_admin_device_images_are_validated_and_saved_without_product_records(): void
    {
        Storage::fake('public');
        $request = Request::create('/admin/operations/medical-devices/save', 'POST', ['name' => 'Monitor', 'description' => 'Details', 'specifications' => 'Model: M1', 'is_published' => 1, 'sort_order' => 4, 'price'=>100, 'report_price'=>10, 'device_condition'=>'new', 'origin_country'=>'EG']);
        $request->files->set('images', [UploadedFile::fake()->image('device.jpg')]);
        app(MedicalDeviceController::class)->save($request);
        $device = CompanyMedicalDevice::firstOrFail();
        Storage::disk('public')->assertExists($device->images[0]);
        $this->assertSame('Model: M1', $device->specifications);
        $this->assertSame(0, DB::table('products')->count());
        $request = Request::create('/', 'POST', ['name' => 'Updated', 'is_published' => 0, 'sort_order' => 20, 'remove_images' => [0], 'price'=>100, 'report_price'=>10, 'device_condition'=>'new', 'origin_country'=>'EG']);
        app(MedicalDeviceController::class)->save($request, $device);
        $this->assertSame([], $device->fresh()->images);
        $this->assertFalse($device->fresh()->is_published);
    }

    public function test_guest_signin_preserves_selected_device_without_creating_an_inquiry(): void
    {
        $device = $this->device();
        $response = app(\App\Http\Controllers\Web\MedicalDeviceController::class)->inquire(Request::create('/', 'POST'), $device->id, app(\App\Services\MedicalDeviceReportService::class));
        $this->assertSame(route('customer.auth.login'), $response->getTargetUrl());
        app(\App\Services\Web\CustomerAuthService::class)->storeCustomerAuthReturnURL();
        $this->assertSame(route('medical-devices.show', $device), app(\App\Services\Web\CustomerAuthService::class)->getCustomerAuthReturnURL());
        $this->assertSame(0, MedicalDeviceInquiry::count());
    }

    public function test_editorial_eligibility_excludes_unapproved_hidden_inactive_and_company_products(): void
    {
        $this->products();
        $this->assertSame([1, 7], app(HomepageEditorialService::class)->eligibleProducts()->orderBy('id')->pluck('id')->all());
    }

    public function test_operations_catalog_matches_current_care_homepage_and_keeps_hidden_current_sections_editable(): void
    {
        $builder = app(HomepageSectionBuilderService::class);
        $this->assertSame(['latest_products', 'featured_products', 'featured_deals', 'savings_offers'],
            $builder->operationsProductCatalog('theme_sigma_care')->keys()->all());
        $draft = $builder->ensureDraft('theme_sigma_care');
        $draft->sections()->where('section_key', 'latest_products')->update(['is_visible' => false]);
        $this->assertTrue($builder->operationsProductCatalog('theme_sigma_care')->has('latest_products'));
        $this->assertFalse($builder->operationsProductCatalog('theme_sigma_care')->has('best_selling'));
        $this->assertFalse($builder->operationsProductCatalog('theme_sigma_care')->has('top_rated'));
        $this->assertFalse($builder->operationsProductCatalog('theme_sigma_care')->has('paid_ads'));
    }

    public function test_operations_cannot_restore_retired_homepage_sections(): void
    {
        $builder = app(HomepageSectionBuilderService::class);
        $draft = $builder->ensureDraft('theme_sigma_care');
        try {
            app(HomepageSectionsController::class)->save(Request::create('/', 'POST', [
                'theme' => 'theme_sigma_care', 'key' => 'best_selling', 'title' => 'Retired',
                'is_visible' => 1, 'sort_order' => 10, 'source_mode' => 'automatic',
            ]), $builder, app(HomepageEditorialService::class));
            $this->fail('Retired section was accepted');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertFalse($draft->fresh('sections')->sections->firstWhere('section_key', 'best_selling')->is_visible);
    }

    public function test_custom_section_can_be_created_ordered_published_and_revalidated(): void
    {
        $this->products();
        $admin = new Admin;
        $admin->id = 1;
        $admin->admin_role_id = 1;
        auth('admin')->setUser($admin);
        $controller = app(HomepageSectionsController::class);
        $builder = app(HomepageSectionBuilderService::class);
        $editorial = app(HomepageEditorialService::class);
        $controller->save(Request::create('/', 'POST', ['theme' => 'theme_sigma_care', 'title' => 'Devices for clinics', 'is_visible' => 1, 'sort_order' => 15, 'source_mode' => 'manual', 'product_ids' => [7, 1]]), $builder, $editorial);
        $section = $builder->ensureDraft('theme_sigma_care')->sections->first(fn ($s) => str_starts_with($s->section_key, 'custom_'));
        $this->assertNotNull($section);
        $this->assertSame([7, 1], $section->product_ids);
        $this->assertSame('Devices for clinics', $section->settings['title']);
        $builder->publish('theme_sigma_care', 1);
        $layout = $builder->layout('theme_sigma_care');
        $this->assertSame(15, $layout['sections'][$section->section_key]['sort_order']);
        $this->assertSame([7, 1], $builder->resolveProducts('theme_sigma_care', $section->section_key, collect(), $layout, false)->pluck('id')->all());
        DB::table('products')->where('id', 7)->update(['status' => 0]);
        $this->assertSame([1], $builder->resolveProducts('theme_sigma_care', $section->section_key, collect(), $layout, false)->pluck('id')->all());
        $controller->save(Request::create('/', 'POST', ['theme' => 'theme_sigma_care', 'key' => $section->section_key, 'title' => 'Renamed', 'is_visible' => 0, 'sort_order' => 25, 'source_mode' => 'manual', 'product_ids' => []]), $builder, $editorial);
        $builder->publish('theme_sigma_care', 1);
        $this->assertFalse($builder->layout('theme_sigma_care')['sections'][$section->section_key]['is_visible']);
        $this->assertSame(7, DB::table('products')->count());
    }

    public function test_editorial_eligibility_is_applied_before_automatic_and_manual_limits(): void
    {
        $this->products();
        foreach (range(8, 30) as $id) {
            DB::table('products')->insert(['id' => $id, 'name' => 'Company product '.$id, 'added_by' => 'admin', 'user_id' => 1]);
        }
        $builder = app(HomepageSectionBuilderService::class);
        $section = ['is_visible' => true, 'source_mode' => 'automatic', 'automatic_source' => 'latest', 'product_ids' => [], 'settings' => ['limit' => 2, 'editorial' => true]];
        $layout = ['sections' => ['latest_products' => $section]];
        $this->assertSame([7, 1], $builder->resolveProducts('theme_sigma_care', 'latest_products', collect(), $layout, false)->pluck('id')->all());
        $layout['sections']['latest_products']['source_mode'] = 'manual';
        $layout['sections']['latest_products']['product_ids'] = [30, 29, 2, 3, 7, 1];
        $this->assertSame([7, 1], $builder->resolveProducts('theme_sigma_care', 'latest_products', collect(), $layout, false)->pluck('id')->all());
        $layout['sections']['latest_products']['source_mode'] = 'mixed';
        $layout['sections']['latest_products']['product_ids'] = [30, 1];
        $this->assertSame([1, 7], $builder->resolveProducts('theme_sigma_care', 'latest_products', collect(), $layout, false)->pluck('id')->all());
    }

    public function test_ineligible_assignment_is_rejected_without_mutating_product_or_draft(): void
    {
        $this->products();
        $builder = app(HomepageSectionBuilderService::class);
        $response = app(HomepageSectionsController::class)->save(Request::create('/', 'POST', ['theme' => 'theme_sigma_care', 'title' => 'Invalid', 'is_visible' => 1, 'sort_order' => 10, 'source_mode' => 'manual', 'product_ids' => [2]]), $builder, app(HomepageEditorialService::class));
        $this->assertTrue(session()->get('errors')->has('product_ids'));
        $this->assertSame(0, DB::table('products')->where('id', 2)->value('status'));
        $this->assertSame(0, DB::table('homepage_layout_versions')->count());
    }

    public function test_every_management_route_has_authentication_and_module_protection(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'admin.operations.medical-devices.') || str_starts_with($r->getName() ?? '', 'admin.operations.homepage-sections.'));
        $this->assertCount(12, $routes);
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('admin', $middleware);
            $this->assertContains('module:catalog', $middleware);
            if (str_contains($route->uri(), 'inquiries')) {
                $this->assertContains('module:people', $middleware);
            }
        }
    }

    public function test_module_middleware_rejects_staff_without_required_access(): void
    {
        $admin = new Admin;
        $admin->id = 2;
        $admin->admin_role_id = 2;
        $role = new AdminRole;
        $role->module_access = json_encode(['orders']);
        $role->status = 1;
        $admin->setRelation('role', $role);
        auth('admin')->setUser($admin);
        $called = false;
        $response = (new \App\Http\Middleware\ModulePermissionMiddleware)->handle(Request::create('/admin/operations/medical-devices'), function () use (&$called) {
            $called = true;

            return response('allowed');
        }, 'catalog');
        $this->assertFalse($called);
        $this->assertSame(route('admin.dashboard.index'), $response->getTargetUrl());
    }

    public function test_long_device_name_does_not_exceed_legacy_support_subject_length(): void
    {
        $device = $this->device(['name' => str_repeat('ج', 200)]);
        $inquiry = app(MedicalDeviceInquiryService::class)->submit($device->id, 1);
        $this->assertLessThanOrEqual(150, mb_strlen($inquiry->conversation->subject));
        $this->assertSame($device->name, $inquiry->device_name);
    }

    public function test_inquiry_failure_rolls_back_its_support_conversation(): void
    {
        $device = $this->device();
        MedicalDeviceInquiry::creating(fn () => throw new \RuntimeException('Simulated persistence failure'));
        try {
            app(MedicalDeviceInquiryService::class)->submit($device->id, 1);
            $this->fail('Expected persistence failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated persistence failure', $e->getMessage());
        } finally {
            MedicalDeviceInquiry::flushEventListeners();
        }
        $this->assertSame(0, SupportTicket::count());
        $this->assertSame(0, MedicalDeviceInquiry::count());
    }

    public function test_notifications_and_communication_center_include_the_inquiry_context(): void
    {
        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('sender_type')->nullable();
            $t->string('subject');
            $t->text('message');
            $t->boolean('seen')->default(false);
            $t->timestamps();
        });
        Schema::create('contact_conversation_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contact_id');
            $t->string('sender');
            $t->text('body');
        });
        Schema::create('vendor_support_threads', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('seller_id');
            $t->string('subject');
            $t->timestamps();
        });
        Schema::create('vendor_support_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_support_thread_id');
            $t->text('body');
        });
        Schema::create('chattings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('admin_id');
            $t->text('message');
            $t->boolean('sent_by_customer');
            $t->boolean('seen_by_admin');
            $t->timestamps();
        });
        $admin = new Admin;
        $admin->id = 1;
        $admin->admin_role_id = 1;
        auth('admin')->setUser($admin);
        $inquiry = app(MedicalDeviceInquiryService::class)->submit($this->device()->id, 1);
        $workspace = new class extends AdminWorkspaceService
        {
            public function totals(): array
            {
                return [];
            }

            public function payments(): array
            {
                return ['count' => 0, 'items' => []];
            }

            public function pendingOrders()
            {
                return DB::table('order_details');
            }
        };
        // Use a role with catalog + people only so order/payment read models stay outside this fixture.
        $admin->admin_role_id = 2;
        $role = new AdminRole;
        $role->module_access = json_encode(['catalog', 'people']);
        $role->status = 1;
        $admin->setRelation('role', $role);
        $row = $workspace->conversations()->first();
        $this->assertSame('Customer ', $row->sender);
        $this->assertSame(1, (int) $row->unread);
        $this->assertStringContainsString('Ultrasound', $row->subject);
        $this->assertSame(route('admin.support-ticket.singleTicket', $inquiry->support_ticket_id), $workspace->conversationUrl($row));
        $snapshot = $workspace->snapshot();
        $this->assertSame(1, $snapshot['categories']['messages']['count']);
        $this->assertSame(route('admin.operations.medical-devices.inquiry', $inquiry), $snapshot['categories']['messages']['items'][0]['url']);
        $this->assertSame('Customer ', $snapshot['categories']['messages']['items'][0]['title']);
    }

    public function test_new_management_surfaces_render_in_arabic_with_real_blade_templates(): void
    {
        $this->products();
        DB::table('products')->where('id', 1)->update(['name' => 'جهاز مراقبة العلامات الحيوية للمستشفيات']);
        DB::table('products')->where('id', 7)->update(['name' => 'جهاز قياس ضغط الدم الإلكتروني']);
        DB::table('sellers')->where('id', 1)->update(['f_name' => 'محمد', 'l_name' => 'أحمد']);
        DB::table('shops')->insert(['seller_id' => 1, 'name' => 'شركة الرعاية الطبية للأجهزة والمستلزمات']);

        $admin = new Admin;
        $admin->id = 1;
        $admin->admin_role_id = 1;
        auth('admin')->setUser($admin);
        app()->setLocale('ar');
        session()->put('local', 'ar');
        session()->put('direction', 'rtl');
        \Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8098');
        $device = $this->device(['name' => 'جهاز قياس العلامات الحيوية', 'description' => 'جهاز طبي مملوك للشركة. أرسل طلب التفاصيل للتواصل مع الإدارة.', 'specifications' => "الطراز: M100\nالشاشة: 8 بوصات"]);
        $inquiry = app(MedicalDeviceInquiryService::class)->submit($device->id, 1);
        $controller = app(MedicalDeviceController::class);
        $builder = app(HomepageSectionBuilderService::class);
        $draft = $builder->ensureDraft('theme_sigma_care', 1);
        $draft->sections()->where('section_key', 'latest_products')->update(['source_mode' => 'manual', 'product_ids' => [1], 'settings' => ['limit' => 12, 'editorial' => true]]);

        $views = [
            'devices' => $controller->index(), 'device-edit' => $controller->edit($device),
            'inquiries' => $controller->inquiries(Request::create('/')), 'inquiry' => $controller->inquiry($inquiry),
            'homepage-sections' => app(HomepageSectionsController::class)->index(Request::create('/', 'GET', ['theme' => 'theme_sigma_care']), app(HomepageSectionBuilderService::class), app(HomepageEditorialService::class)),
            'public-devices' => app(\App\Http\Controllers\Web\MedicalDeviceController::class)->index(),
            'public-device' => app(\App\Http\Controllers\Web\MedicalDeviceController::class)->show($device->id),
        ];
        $directory = storage_path('framework/testing-operations/rendered');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
        $searchRequest = Request::create('/admin/operations/homepage-sections', 'GET', ['theme' => 'theme_sigma_care', 'q' => '7']);
        $searchRequest->headers->set('Accept', 'application/json');
        $search = app(HomepageSectionsController::class)->index($searchRequest, $builder, app(HomepageEditorialService::class));
        $this->assertSame(200, $search->getStatusCode());
        $this->assertStringContainsString('data-id="7"', $search->getData()->html);
        $this->assertStringNotContainsString('data-id="1"', $search->getData()->html);
        file_put_contents($directory.'/eligible-search.json', $search->getContent());

        foreach ($views as $name => $view) {
            // Render each real screen in isolation: the unavailable MySQL-backed shell is not simulated.
            $source = preg_replace('/^@extends[^\n]*\n/u', '', file_get_contents($view->getPath()));
            $data = $view->getData() + ['errors' => new \Illuminate\Support\ViewErrorBag];
            $html = \Illuminate\Support\Facades\Blade::render($source."\n@yield('content')\n@stack('css_or_js')\n@stack('script')", $data, deleteCachedView: true);
            $this->assertStringNotContainsString('@section', $html);
            $this->assertStringContainsString($name === 'public-device' ? 'طلب التفاصيل' : ($name === 'public-devices' ? 'الأجهزة الطبية' : ($name === 'homepage-sections' ? 'أقسام الصفحة الرئيسية' : 'الأجهزة')), $html);
            $adminCss = ['backend/libs/fonts/inter/inter.css', 'backend/libs/bootstrap/bootstrap.min.css', 'backend/libs/bootstrap/bootstrap.rtl.css', 'backend/webfonts/uicons-regular-rounded.css', 'backend/admin/css/style.css', 'backend/admin/css/style-extended.css', 'backend/admin/css/custom.css', 'back-end/css/admin-v2.css', 'back-end/css/admin-dark-mode.css'];
            $styles = str_starts_with($name, 'public-') ? ['../resources/themes/theme_aster/public/assets/css/bootstrap.min.css', '../resources/themes/theme_aster/public/assets/css/fonts-init.css', '../resources/themes/theme_sigma_care/public/assets/css/sigma-care.css'] : $adminCss;
            $head = '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
            foreach ($styles as $style) {
                $head .= '<link rel="stylesheet" href="/assets/'.$style.'">';
            }
            file_put_contents($directory.'/'.$name.'.html', '<!doctype html><html lang="ar" dir="rtl" data-ba-admin-theme="light"><head>'.$head.'</head><body><div class="app-v2" style="display:block;height:auto;overflow:visible"><main class="v2-main" style="overflow:visible">'.$html.'</main></div></body></html>');
            view()->flushState();
        }
        \Illuminate\Support\Facades\URL::forceRootUrl(null);
    }

    public function test_integrated_blade_views_compile_without_php_syntax_errors(): void
    {
        $paths = [
            'resources/views/layouts/admin/partials/_side-bar.blade.php',
            'resources/views/layouts/admin/partials/v2/_side-bar.blade.php',
            'resources/views/layouts/admin/partials/v2/_rail.blade.php',
            'resources/views/admin-views/support-ticket/singleView.blade.php',
            'resources/themes/default/web-views/home.blade.php',
            'resources/themes/theme_aster/theme-views/home.blade.php',
            'resources/themes/theme_aster/theme-views/partials/_recommended-product.blade.php',
            'resources/themes/theme_sigma_care/theme-views/home.blade.php',
        ];
        foreach ($paths as $path) {
            $compiled = \Illuminate\Support\Facades\Blade::compileString(file_get_contents(base_path($path)));
            $this->assertIsArray(token_get_all($compiled, TOKEN_PARSE), $path);
        }
    }

    public function test_device_public_templates_only_offer_the_gated_report_flow(): void
    {
        foreach (['index', 'show'] as $name) {
            $source = file_get_contents(resource_path('views/medical-devices/'.$name.'.blade.php'));
            $this->assertStringContainsString('تقرير فني', $source);
            foreach (['add-to-cart', 'checkout', 'buy_now', 'unit_price', 'purchase-limit'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source);
            }
        }
    }
}
