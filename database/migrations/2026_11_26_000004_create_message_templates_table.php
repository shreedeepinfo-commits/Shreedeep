<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->string('meta_template_id')->nullable()->index();
            $table->string('name');
            $table->string('language', 15);
            $table->string('category'); // MARKETING, UTILITY, AUTHENTICATION
            $table->string('status')->default('PENDING'); // APPROVED, PENDING, REJECTED, PAUSED, DISABLED
            $table->json('components');
            $table->text('rejected_reason')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_account_id', 'name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
