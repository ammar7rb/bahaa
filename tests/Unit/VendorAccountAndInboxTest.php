<?php

namespace Tests\Unit;

use App\Http\Controllers\Vendor\VendorAccountController;
use App\Models\Seller;
use App\Models\VendorAccountProfile;
use App\Services\VendorInboxService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorAccountAndInboxTest extends TestCase
{
    private Seller $seller;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY', 'public');
        Schema::create('sellers', function (Blueprint $t) {
            $t->id(); $t->string('f_name'); $t->string('l_name'); $t->string('phone'); $t->string('email');
            $t->string('password'); $t->string('image')->nullable(); $t->timestamp('phone_verified_at')->nullable(); $t->timestamps();
        });
        Schema::create('shops', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('name'); $t->text('address'); $t->string('contact'); $t->string('tax_identification_number')->nullable(); $t->timestamps(); });
        Schema::create('storages', function (Blueprint $t) { $t->id(); $t->string('data_type'); $t->unsignedBigInteger('data_id'); $t->string('key'); $t->string('value'); $t->timestamps(); });
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('seller_is')->default('seller'); $t->string('order_status')->default('pending');
            $t->decimal('order_amount', 24, 3)->default(1000); $t->string('commerce_flow_version')->nullable(); $t->string('commerce_flow_status')->nullable();
            $t->string('admin_order_review_status')->nullable(); $t->string('post_purchase_status')->nullable(); $t->uuid('seller_restricted_access_token')->nullable(); $t->timestamps();
        });
        Schema::create('seller_order_insurances', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('order_id'); $t->string('status'); $t->string('payment_status'); $t->decimal('amount', 24, 3)->default(100); $t->timestamps(); });
        Schema::create('notifications', function (Blueprint $t) { $t->id(); $t->string('sent_to'); $t->string('sent_by')->default('admin'); $t->unsignedBigInteger('seller_id')->nullable(); $t->string('title'); $t->text('description'); $t->boolean('status')->default(true); $t->string('image')->nullable(); $t->timestamps(); });
        Schema::create('notification_seens', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('notification_id'); $t->timestamps(); });
        Schema::create('seller_activation_tickets', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->timestamps(); });
        Schema::create('seller_activation_ticket_messages', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_activation_ticket_id'); $t->string('sender_type'); $t->text('body'); $t->json('attachments')->nullable(); $t->timestamps(); });
        Schema::create('payment_requests', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('attribute'); $t->unsignedBigInteger('attribute_id'); $t->unsignedBigInteger('payer_id'); $t->boolean('is_paid'); $t->decimal('payment_amount', 24, 3); $t->string('currency_code'); $t->string('payment_method'); $t->string('transaction_id')->nullable(); $t->timestamps(); });
        (require database_path('migrations/2026_10_06_120000_create_vendor_account_profiles_and_inbox_reads.php'))->up();
        DB::table('sellers')->insert(['id' => 8, 'f_name' => 'Ahmed', 'l_name' => 'Ali', 'phone' => '+201000000008', 'email' => 'vendor@example.test', 'password' => Hash::make('OldPassword!1'), 'phone_verified_at' => now(), 'created_at' => now()->subDay(), 'updated_at' => now()]);
        DB::table('shops')->insert(['seller_id' => 8, 'name' => 'Store', 'address' => 'Cairo', 'contact' => '+201000000008']);
        $this->seller = Seller::findOrFail(8);
        DB::table('business_settings')->insert(['type' => 'language', 'value' => json_encode([['code' => 'en', 'status' => 1, 'default' => true, 'direction' => 'ltr']])]);
        DB::table('business_settings')->insert(['type' => 'currency_model', 'value' => 'single_currency']);
        session(['local' => 'en', 'direction' => 'ltr']);
        Storage::fake('local'); Storage::fake('public');
    }

    private function request(array $input = [], array $files = []): Request
    {
        $request = Request::create('/api/v3/seller/account', 'POST', $input, [], $files);
        $request->setLaravelSession(app('session.store'));
        $request->attributes->set('seller', $this->seller);
        $request->merge(['seller' => $this->seller]);
        return $request;
    }

    private function input(): array
    {
        return ['f_name' => 'Updated', 'l_name' => 'Vendor', 'email' => 'updated@example.test', 'store_name' => 'Updated store',
            'phone_numbers' => ['+201111111111', '+201222222222'], 'store_addresses' => ['Cairo address', 'Alex address'],
            'commercial_registration' => 'CR 123', 'tax_card' => 'Tax 456'];
    }

    public function test_profile_persists_multiple_contacts_documents_and_keeps_login_identity(): void
    {
        $controller = new VendorAccountController();
        Model::withoutEvents(fn () => $controller->update($this->request($this->input(), ['image' => UploadedFile::fake()->image('avatar.png'), 'commercial_document' => UploadedFile::fake()->image('registration.png'), 'tax_document' => UploadedFile::fake()->image('tax.png')])));
        $profile = VendorAccountProfile::where('seller_id', 8)->firstOrFail();
        $this->assertSame($this->input()['phone_numbers'], $profile->phone_numbers);
        $this->assertSame($this->input()['store_addresses'], $profile->store_addresses);
        $this->assertSame('+201000000008', $this->seller->fresh()->phone);
        $this->assertNotNull($this->seller->fresh()->phone_verified_at);
        $this->assertSame('Updated store', DB::table('shops')->value('name'));
        Storage::disk('local')->assertExists($profile->commercial_document);
        Storage::disk('public')->assertMissing($profile->commercial_document);
        $this->assertNotNull($this->seller->fresh()->image);
        Storage::disk('public')->assertExists('seller/'.$this->seller->fresh()->image);
        $payload = $controller->show($this->request())->getData(true);
        $this->assertSame('V8', $payload['account_id']);
        $this->assertArrayNotHasKey('commercial_document', $payload);
        $this->assertTrue($payload['tax_document_uploaded']);
        $this->assertSame(200, $controller->document($this->request(), 'tax')->getStatusCode());
    }

    public function test_document_access_is_owned_by_authenticated_seller(): void
    {
        DB::table('vendor_account_profiles')->insert(['seller_id' => 99, 'commercial_document' => 'vendor-documents/99/private.png']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        (new VendorAccountController())->document($this->request(), 'commercial');
    }

    public function test_profile_rejects_invalid_uploads_and_empty_contacts_before_saving(): void
    {
        $input = $this->input(); $input['phone_numbers'] = [];
        try { (new VendorAccountController())->update($this->request($input, ['tax_document' => UploadedFile::fake()->create('bad.svg', 20, 'image/svg+xml')])); $this->fail('Invalid data was accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('phone_numbers', $e->errors()); $this->assertArrayHasKey('tax_document', $e->errors()); }
        $this->assertSame(0, VendorAccountProfile::count());
    }

    public function test_password_requires_current_password_and_persists_a_hash(): void
    {
        $controller = new VendorAccountController();
        $input = ['current_password' => 'wrong', 'password' => 'NewPassword!2', 'password_confirmation' => 'NewPassword!2'];
        try { $controller->password($this->request($input)); $this->fail('Wrong password accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('current_password', $e->errors()); }
        $input['current_password'] = 'OldPassword!1';
        Model::withoutEvents(fn () => $controller->password($this->request($input)));
        $this->assertTrue(Hash::check('NewPassword!2', $this->seller->fresh()->password));
    }

    public function test_badges_are_scoped_and_reading_never_accepts_or_unlocks_an_order(): void
    {
        DB::table('orders')->insert(['id' => 123321, 'seller_id' => 8, 'commerce_flow_version' => config('order_commerce.new_flow_version'), 'commerce_flow_status' => 'seller_insurance_pending', 'admin_order_review_status' => 'approved', 'seller_restricted_access_token' => 'secret-order-token', 'created_at' => now()]);
        DB::table('seller_order_insurances')->insert(['seller_id' => 8, 'order_id' => 123321, 'status' => 'pending_payment', 'payment_status' => 'unpaid']);
        DB::table('seller_activation_tickets')->insert([['id' => 1, 'seller_id' => 8], ['id' => 2, 'seller_id' => 99]]);
        DB::table('seller_activation_ticket_messages')->insert([
            ['seller_activation_ticket_id' => 1, 'sender_type' => 'admin', 'body' => 'Admin message'],
            ['seller_activation_ticket_id' => 1, 'sender_type' => 'seller', 'body' => 'Seller reply'],
            ['seller_activation_ticket_id' => 2, 'sender_type' => 'admin', 'body' => 'Other vendor secret'],
        ]);
        $service = app(VendorInboxService::class);
        $snapshot = $service->snapshot($this->seller);
        $this->assertSame(['orders' => 1, 'notifications' => 1, 'messages' => 1], $snapshot['counts']);
        $this->assertSame('***321', $snapshot['notifications'][0]['order_reference']);
        $this->assertStringNotContainsString('123321', json_encode($snapshot));
        $service->read($this->seller, 'order_alert', 'secret-order-token');
        $service->read($this->seller, 'message', '1');
        $this->assertSame(['orders' => 1, 'notifications' => 0, 'messages' => 0], $service->snapshot($this->seller)['counts']);
        $this->assertSame('pending', DB::table('orders')->value('order_status'));
        $this->assertSame('unpaid', DB::table('seller_order_insurances')->value('payment_status'));
    }

    public function test_vendor_cannot_acknowledge_another_vendors_message(): void
    {
        DB::table('seller_activation_tickets')->insert(['id' => 2, 'seller_id' => 99]);
        DB::table('seller_activation_ticket_messages')->insert(['id' => 2, 'seller_activation_ticket_id' => 2, 'sender_type' => 'admin', 'body' => 'Private']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(VendorInboxService::class)->read($this->seller, 'message', '2');
    }

    public function test_payment_callback_rejects_unpersisted_pending_and_underpaid_results(): void
    {
        DB::table('seller_order_insurances')->insert(['id' => 1, 'seller_id' => 8, 'order_id' => 10, 'status' => 'pending_payment', 'payment_status' => 'unpaid', 'amount' => 100]);
        $service = \Mockery::mock(\App\Services\SellerOrderInsuranceService::class);
        $service->shouldNotReceive('markPaid');
        app()->instance(\App\Services\SellerOrderInsuranceService::class, $service);
        seller_order_insurance_payment_success(['id' => 'forged', 'is_paid' => 1]);
        DB::table('payment_requests')->insert(['id' => 'pending', 'attribute' => 'seller_order_insurance', 'attribute_id' => 1, 'payer_id' => 8, 'is_paid' => false, 'payment_amount' => 100, 'currency_code' => 'EGP', 'payment_method' => 'gateway']);
        seller_order_insurance_payment_success(['id' => 'pending', 'is_paid' => 1]);
        DB::table('payment_requests')->where('id', 'pending')->update(['is_paid' => true, 'payment_amount' => 25]);
        seller_order_insurance_payment_success(['id' => 'pending', 'is_paid' => 1]);
        $this->assertSame('unpaid', DB::table('seller_order_insurances')->value('payment_status'));
    }

    public function test_payment_callback_uses_verified_amount_and_vendor_from_the_database(): void
    {
        DB::table('seller_order_insurances')->insert(['id' => 1, 'seller_id' => 8, 'order_id' => 10, 'status' => 'pending_payment', 'payment_status' => 'unpaid', 'amount' => 100]);
        DB::table('payment_requests')->insert(['id' => 'verified', 'attribute' => 'seller_order_insurance', 'attribute_id' => 1, 'payer_id' => 8, 'is_paid' => true, 'payment_amount' => 100, 'currency_code' => 'EGP', 'payment_method' => 'gateway', 'transaction_id' => 'verified-transaction']);
        $service = \Mockery::mock(\App\Services\SellerOrderInsuranceService::class);
        $service->shouldReceive('markPaid')->once()->withArgs(fn ($insurance, $method, $reference, $admin, $note) => $insurance->seller_id === 8 && $method === 'gateway' && $reference === 'verified-transaction')->andReturn(\App\Models\SellerOrderInsurance::find(1));
        app()->instance(\App\Services\SellerOrderInsuranceService::class, $service);
        seller_order_insurance_payment_success(['id' => 'verified', 'payer_id' => 99, 'payment_amount' => 1]);
        $this->assertTrue(true);
    }
}
