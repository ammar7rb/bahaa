<?php

namespace Tests\Unit;

use App\Models\BusinessSetting;
use App\Models\PolicyVersion;
use App\Models\SupportTicket;
use App\Models\Seller;
use App\Repositories\VendorRepository;
use App\Models\User;
use App\Services\AdministrativeReferenceService;
use App\Services\CustomerActivationService;
use App\Services\PolicyAcceptanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdentityAndSupportBatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('activation_status')->default('pending_support');
            $table->timestamp('activation_requested_at')->nullable();
            $table->timestamp('activation_approved_at')->nullable();
            $table->unsignedBigInteger('activation_approved_by')->nullable();
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('subject')->nullable();
            $table->string('type')->nullable();
            $table->string('purpose')->default('general');
            $table->string('priority')->default('medium');
            $table->text('description')->nullable();
            $table->json('attachment')->nullable();
            $table->text('reply')->nullable();
            $table->string('status')->default('pending');
            $table->string('review_status')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('support_ticket_convs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('customer_message')->nullable();
            $table->text('admin_message')->nullable();
            $table->integer('position')->default(0);
            $table->json('attachment')->nullable();
            $table->timestamps();
        });
        Schema::create('policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->string('title');
            $table->longText('content');
            $table->string('audience');
            $table->integer('version');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_at')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
        });
        Schema::create('policy_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('policy_version_id');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['policy_version_id', 'subject_type', 'subject_id']);
        });

        BusinessSetting::create(['type' => 'language', 'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']])]);
        clearWebConfigCacheKeys();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('policy_acceptances');
        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('support_ticket_convs');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('users');
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_customer_activation_ticket_is_created_once_and_admin_can_approve_it(): void
    {
        $customer = User::create([
            'f_name' => 'Test', 'l_name' => 'Customer', 'email' => 'customer@example.com',
            'phone' => '+201000000000', 'password' => bcrypt('password'),
        ]);
        $service = app(CustomerActivationService::class);

        $first = $service->ensureTicket($customer);
        $second = $service->ensureTicket($customer->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame('activation_ticket_open', $customer->fresh()->activation_status);
        $this->assertDatabaseCount('support_tickets', 1);

        $service->decide($first->fresh(['customer']), 'approve', null, 7);

        $this->assertSame('active', $customer->fresh()->activation_status);
        $this->assertSame('approved', $first->fresh()->review_status);
        $this->assertDatabaseHas('support_ticket_convs', [
            'support_ticket_id' => $first->id,
            'admin_id' => 7,
        ]);
    }

    public function test_customer_and_seller_references_are_unambiguous(): void
    {
        $this->assertSame(['type' => 'users', 'id' => 123], AdministrativeReferenceService::parse('C123'));
        $this->assertSame(['type' => 'users', 'id' => 123], AdministrativeReferenceService::parse('c-123'));
        $this->assertSame(['type' => 'sellers', 'id' => 55], AdministrativeReferenceService::parse('V#55'));
        $this->assertNull(AdministrativeReferenceService::parse('123'));
        $this->assertSame('C8', AdministrativeReferenceService::format('customer', 8));
        $this->assertSame('V8', AdministrativeReferenceService::format('seller', 8));
    }

    public function test_vendor_repository_can_find_the_visible_v_reference(): void
    {
        $seller = Seller::create([
            'f_name' => 'Vendor',
            'l_name' => 'Example',
            'email' => 'vendor@example.com',
            'phone' => '+201011111111',
            'password' => bcrypt('password'),
        ]);

        $results = app(VendorRepository::class)->getListWhere(searchValue: 'V'.$seller->id, dataLimit: 'all');

        $this->assertCount(1, $results);
        $this->assertSame($seller->id, $results->first()->id);
        $this->assertSame('V'.$seller->id, $results->first()->administrative_reference);
    }

    public function test_only_latest_active_required_policy_version_is_returned_for_registration(): void
    {
        PolicyVersion::create(['key' => 'privacy', 'title' => 'Old', 'content' => 'old', 'audience' => 'both', 'version' => 1, 'is_required' => true, 'is_active' => true]);
        $latest = PolicyVersion::create(['key' => 'privacy', 'title' => 'Latest', 'content' => 'latest', 'audience' => 'both', 'version' => 2, 'is_required' => true, 'is_active' => true]);
        PolicyVersion::create(['key' => 'seller-only', 'title' => 'Seller', 'content' => 'seller', 'audience' => 'seller', 'version' => 1, 'is_required' => true, 'is_active' => true]);

        $required = app(PolicyAcceptanceService::class)->requiredFor('customer');

        $this->assertCount(1, $required);
        $this->assertSame($latest->id, $required->first()->id);
    }
}
