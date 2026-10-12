<?php

namespace Tests\Unit;

use App\Models\SupportTicket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArchiveClosedSupportTicketsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('type')->nullable();
            $table->string('priority')->default('low');
            $table->text('description')->nullable();
            $table->json('attachment')->nullable();
            $table->text('reply')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('support_tickets');
        parent::tearDown();
    }

    public function test_it_archives_only_closed_tickets_after_180_days_without_deleting_them(): void
    {
        $expired = SupportTicket::create([
            'status' => 'close',
            'closed_at' => now()->subDays(181),
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(181),
        ]);
        $recent = SupportTicket::create([
            'status' => 'close',
            'closed_at' => now()->subDays(179),
            'created_at' => now()->subDays(180),
            'updated_at' => now()->subDays(179),
        ]);
        $open = SupportTicket::create([
            'status' => 'open',
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ]);

        $this->artisan('support-tickets:archive-closed')->assertSuccessful();

        $this->assertNotNull($expired->fresh()->archived_at);
        $this->assertNull($recent->fresh()->archived_at);
        $this->assertNull($open->fresh()->archived_at);
        $this->assertDatabaseCount('support_tickets', 3);
    }
}
