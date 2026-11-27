<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // widget, shopify, woocommerce
            $table->string('token', 40)->unique(); // public id used in the widget script and store webhook URL
            $table->text('secret')->nullable(); // encrypted, store webhook signing secret
            $table->json('settings')->nullable();
            $table->boolean('active')->default(false);
            $table->unsignedBigInteger('events_count')->default(0); // widget clicks or store orders received
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'type']);
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30); // order_placed, order_shipped, order_cancelled
            $table->string('external_id', 100); // the store's order id
            $table->string('order_number', 60)->nullable();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20); // queued, sent, skipped, failed
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'event', 'external_id']); // store retries never send twice
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('integrations');
    }
};
