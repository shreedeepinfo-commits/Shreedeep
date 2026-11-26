<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_template_id')->constrained()->restrictOnDelete();
            $table->string('name');
            // draft, scheduled, running, paused, completed, cancelled
            $table->string('status')->default('draft')->index();
            $table->json('audience'); // {"all": bool, "groups": [ids]}
            $table->json('variables')->nullable(); // {"body": [{type,value}], "header": {type,value}}
            $table->string('header_media_url')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Every WhatsApp message in or out. Grows fastest; partition by month in production MySQL.
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('direction', 3); // out, in
            $table->string('wamid')->nullable()->unique();
            $table->string('phone', 20); // the customer's number (recipient or sender)
            $table->string('type', 20)->default('template'); // template, text, image, document, ...
            $table->text('body')->nullable(); // text shown in inbox/reports
            $table->json('payload')->nullable(); // request sent or webhook message received
            // queued, sent, delivered, read, failed, received
            $table->string('status', 12)->default('queued');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status']);
            $table->index(['workspace_id', 'phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('campaigns');
    }
};
