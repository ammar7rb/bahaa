<?php

namespace Tests\Unit;

use App\Http\Controllers\RestAPI\v3\seller\auth\RegisterController;
use App\Models\Seller;
use App\Services\FirebaseService;
use App\Services\SellerActivationService;
use App\Services\SellerRegistrationVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SellerActivationRegistrationApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session()->put('local', 'ar');
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY', 'public');

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('status')->default('pending');
            $table->timestamp('phone_verified_at')->nullable();
            $table->uuid('registration_reference')->nullable()->unique();
            $table->string('activation_status')->default('pending_activation');
            $table->timestamp('activation_requested_at')->nullable();
            $table->timestamp('activation_approved_at')->nullable();
            $table->unsignedBigInteger('activation_approved_by')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_activation_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('status');
            $table->string('subject')->nullable();
            $table->unsignedBigInteger('assigned_admin_id')->nullable();
            $table->unsignedBigInteger('approved_by_admin_id')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('hidden_from_subject_at')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_activation_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_activation_ticket_id');
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_admin_id')->nullable();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->boolean('is_automatic')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_activation_ticket_messages');
        Schema::dropIfExists('seller_activation_tickets');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_registration_activation_api_returns_banner_ticket_messages_and_accepts_seller_reply(): void
    {
        $seller = Seller::create([
            'email' => 'seller@example.com',
            'phone' => '+201000000000',
            'password' => bcrypt('password'),
            'status' => 'pending',
            'registration_reference' => (string) Str::uuid(),
        ]);
        $verification = new SellerRegistrationVerificationService(Mockery::mock(FirebaseService::class));
        $activation = app(SellerActivationService::class);
        $ticket = $activation->openRegistrationTicket($seller);
        $controller = new RegisterController();

        $status = $controller->activationStatus(
            Request::create('/', 'GET', ['registration_reference' => $seller->registration_reference]),
            $verification
        );
        $statusPayload = $status->getData(true);
        $this->assertTrue($statusPayload['activation']['banner']['visible']);
        $this->assertSame('red', $statusPayload['activation']['banner']['color']);
        $this->assertSame($ticket->id, $statusPayload['activation']['ticket']['id']);

        $messages = $controller->activationTicketMessages(
            Request::create('/', 'GET', ['registration_reference' => $seller->registration_reference]),
            $verification
        );
        $messagesPayload = $messages->getData(true);
        $this->assertCount(1, $messagesPayload['messages']);
        $this->assertTrue($messagesPayload['messages'][0]['is_automatic']);

        $reply = $controller->sendActivationTicketMessage(
            Request::create('/', 'POST', [
                'registration_reference' => $seller->registration_reference,
                'ticket_id' => $ticket->id,
                'body' => 'أحتاج إلى تفعيل الحساب.',
            ]),
            $verification,
            $activation
        );
        $replyPayload = $reply->getData(true);
        $this->assertSame(201, $reply->getStatusCode());
        $this->assertSame('seller', $replyPayload['message']['sender_type']);
        $this->assertDatabaseCount('seller_activation_ticket_messages', 2);
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['filesystems.disks.default' => 'public']);
        $fileRequest = Request::create('/', 'POST', ['registration_reference' => $seller->registration_reference, 'ticket_id' => $ticket->id]);
        $fileRequest->files->set('attachments', [\Illuminate\Http\UploadedFile::fake()->create('identity.pdf', 20, 'application/pdf')]);
        $fileResponse = $controller->sendActivationTicketMessage($fileRequest, $verification, $activation);
        self::assertSame(201, $fileResponse->status());
        $storedMessage = $ticket->messages()->reorder('id', 'desc')->first();
        self::assertSame('identity.pdf', $storedMessage->attachments[0]['original_name']);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('seller-activation-ticket/'.$storedMessage->attachments[0]['file_name']);
        $seller->update(['activation_status' => 'active']);
        $completed = $controller->activationTicketMessages(Request::create('/', 'GET', ['registration_reference' => $seller->registration_reference]), $verification)->getData(true);
        self::assertTrue($completed['activation_completed']);
        self::assertSame([], $completed['messages']);
        $blocked = $controller->sendActivationTicketMessage(Request::create('/', 'POST', ['registration_reference' => $seller->registration_reference, 'body' => 'late message']), $verification, $activation);
        self::assertSame(409, $blocked->status());
        $this->assertDatabaseCount('seller_activation_ticket_messages', 3);
    }
}
