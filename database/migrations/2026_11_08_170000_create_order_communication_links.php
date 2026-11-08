<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_communication_links')) Schema::create('order_communication_links', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('order_id')->index(); $table->string('channel', 30);
            $table->unsignedBigInteger('resource_id'); $table->unsignedBigInteger('admin_id'); $table->timestamp('created_at');
            $table->unique(['order_id','channel','resource_id'], 'order_communication_unique');
        });
    }
    public function down(): void { throw new RuntimeException('Order communication links must be preserved.'); }
};
