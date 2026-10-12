<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerActivationTicket;
use App\Models\SellerActivationTicketMessage;
use App\Services\SellerActivationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerActivationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('status')->default('pending');
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
        parent::tearDown();
    }

    public function test_registration_ticket_is_created_once_with_the_required_automatic_message(): void
    {
        $seller = Seller::create([
            'email' => 'seller@example.com',
            'phone' => '+201000000000',
            'password' => bcrypt('password'),
            'status' => 'pending',
        ]);

        $service = app(SellerActivationService::class);
        $firstTicket = $service->openRegistrationTicket($seller);
        $secondTicket = $service->openRegistrationTicket($seller->fresh());

        $this->assertSame($firstTicket->id, $secondTicket->id);
        $this->assertSame(SellerActivationTicket::STATUS_OPEN, $firstTicket->status);
        $this->assertSame('activation_ticket_open', $seller->fresh()->activation_status);
        $this->assertDatabaseCount('seller_activation_tickets', 1);
        $this->assertDatabaseHas('seller_activation_ticket_messages', [
            'seller_activation_ticket_id' => $firstTicket->id,
            'sender_type' => SellerActivationTicketMessage::SENDER_SYSTEM,
            'body' => SellerActivationService::AUTOMATIC_MESSAGE,
            'is_automatic' => 1,
        ]);
    }
}
