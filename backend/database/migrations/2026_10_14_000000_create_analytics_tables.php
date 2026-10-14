<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->string('event_id', 128)->primary();
            $table->string('event_name', 64);
            $table->string('visitor_id', 128);
            $table->string('session_id', 128);
            $table->string('source', 16);
            $table->string('page_path', 512)->nullable();
            $table->json('properties');
            $table->string('occurred_at', 40);
            $table->string('received_at', 40);
            $table->index('occurred_at');
            $table->index(['event_name', 'occurred_at']);
            $table->index(['page_path', 'occurred_at']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->string('item_id', 32);
            $table->string('item_name', 128);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('total_cents');
            $table->string('visitor_id', 128);
            $table->string('session_id', 128);
            $table->string('created_at', 40);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('events');
    }
};
