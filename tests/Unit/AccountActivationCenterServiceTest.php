<?php

namespace Tests\Unit;

use App\Models\AccountActivationCase;
use App\Services\AccountActivationCenterService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountActivationCenterServiceTest extends TestCase
{
    private object $activationMigration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacyTables();
        $this->activationMigration = require database_path('migrations/2026_08_16_000001_create_unified_account_activation_center.php');
        $this->activationMigration->up();
        (require database_path('migrations/2026_08_21_000001_add_custom_name_to_account_activation_documents_table.php'))->up();

        DB::table('business_settings')->insert([
            'type' => 'feature_activation_center',
            'value' => '1',
        ]);
        DB::table('admins')->insert(['id' => 1, 'name' => 'Activation Agent', 'status' => 1]);
    }

    protected function tearDown(): void
    {
        foreach ([
            'account_activation_case_events', 'account_activation_documents', 'account_activation_profile_fields',
            'account_activation_cases', 'seller_activation_ticket_messages', 'seller_activation_tickets',
            'support_ticket_convs', 'support_tickets', 'users', 'sellers', 'admins', 'business_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_required_evidence_blocks_then_allows_customer_activation_and_hides_conversation(): void
    {
        DB::table('users')->insert([
            'id' => 10, 'f_name' => 'Customer', 'email' => 'customer@example.test', 'phone' => '01000000000',
            'is_active' => 0, 'activation_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ticketId = DB::table('support_tickets')->insertGetId([
            'customer_id' => 10, 'subject' => 'Activation', 'type' => 'account_activation',
            'purpose' => 'account_activation', 'review_status' => 'pending', 'priority' => 'medium',
            'description' => 'Please activate my account', 'status' => 'pending', 'attachment' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $case = AccountActivationCase::query()->create([
            'subject_type' => AccountActivationCase::SUBJECT_CUSTOMER,
            'subject_id' => 10,
            'source_type' => 'support_ticket',
            'source_id' => $ticketId,
            'status' => AccountActivationCase::STATUS_PENDING,
        ]);

        $service = app(AccountActivationCenterService::class);
        $service->saveField($case, [
            'field_key' => 'national_id', 'label' => 'National ID', 'value' => '29901011234567',
            'is_sensitive' => true, 'is_required' => true, 'is_verified' => false,
        ], 1);

        try {
            $service->approve($case, null, 1);
            self::fail('Unverified required evidence must block activation.');
        } catch (DomainException $exception) {
            self::assertSame('required_activation_evidence_must_be_verified', $exception->getMessage());
        }

        $service->saveField($case, [
            'field_key' => 'national_id', 'label' => 'National ID', 'value' => '29901011234567',
            'is_sensitive' => true, 'is_required' => true, 'is_verified' => true,
        ], 1);
        $approved = $service->approve($case->fresh(), 'Identity verified', 1);

        self::assertSame(AccountActivationCase::STATUS_APPROVED, $approved->status);
        self::assertNotNull($approved->subject_hidden_at);
        self::assertSame(1, (int) DB::table('users')->where('id', 10)->value('is_active'));
        self::assertSame('active', DB::table('users')->where('id', 10)->value('activation_status'));
        self::assertNotNull(DB::table('support_tickets')->where('id', $ticketId)->value('hidden_from_subject_at'));
        self::assertSame('approved', DB::table('support_tickets')->where('id', $ticketId)->value('review_status'));
        self::assertTrue(DB::table('account_activation_case_events')->where('event_type', 'case_approved')->exists());
    }

    public function test_sensitive_fields_and_documents_are_encrypted_and_download_access_is_audited(): void
    {
        Storage::fake('local');
        DB::table('sellers')->insert([
            'id' => 20, 'f_name' => 'Seller', 'email' => 'seller@example.test', 'phone' => '01100000000',
            'status' => 'pending', 'activation_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ticketId = DB::table('seller_activation_tickets')->insertGetId([
            'seller_id' => 20, 'status' => 'open', 'subject' => 'Activation',
            'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $case = AccountActivationCase::query()->create([
            'subject_type' => AccountActivationCase::SUBJECT_SELLER,
            'subject_id' => 20,
            'source_type' => 'seller_activation_ticket',
            'source_id' => $ticketId,
            'status' => AccountActivationCase::STATUS_PENDING,
        ]);
        $service = app(AccountActivationCenterService::class);
        $field = $service->saveField($case, [
            'field_key' => 'tax_number', 'label' => 'Tax number', 'value' => 'TAX-SECRET-1',
            'is_sensitive' => true, 'is_verified' => true,
        ], 1);

        self::assertNotSame('TAX-SECRET-1', DB::table('account_activation_profile_fields')->where('id', $field->id)->value('value'));
        self::assertSame('TAX-SECRET-1', $field->value);

        $upload = UploadedFile::fake()->create('tax-card.pdf', 32, 'application/pdf');
        $originalContents = (string) file_get_contents($upload->getRealPath());
        $document = $service->storeDocument($case, [
            'document_type' => 'tax_card', 'document_number' => 'DOC-SECRET-2', 'is_verified' => true,
        ], $upload, 1);
        $stored = Storage::disk('local')->get($document->storage_path);

        self::assertTrue((bool) ($document->metadata['encrypted_at_rest'] ?? false));
        self::assertNotSame('DOC-SECRET-2', DB::table('account_activation_documents')->where('id', $document->id)->value('document_number'));
        self::assertSame('DOC-SECRET-2', $document->document_number);
        self::assertNotSame($originalContents, $stored);
        self::assertSame($originalContents, Crypt::decryptString($stored));

        $service->recordDocumentAccess($case, $document, 1);
        self::assertTrue(DB::table('account_activation_case_events')->where('event_type', 'document_downloaded')->exists());
    }

    private function createLegacyTables(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('type')->nullable();
            $table->string('priority')->default('medium');
            $table->text('description')->nullable();
            $table->json('attachment')->nullable();
            $table->text('reply')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('support_ticket_convs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('customer_message')->nullable();
            $table->text('admin_message')->nullable();
            $table->boolean('position')->default(false);
            $table->json('attachment')->nullable();
            $table->timestamps();
        });
    }
}
