<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('prefix', 12); // first characters, shown in the list
            $table->string('key_hash', 64)->unique(); // sha256 of the full key; the key itself is never stored
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('requests')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->text('secret'); // encrypted
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('failures')->default(0); // in a row
            $table->timestamp('last_delivery_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->json('payload');
            $table->string('status', 10)->default('pending'); // pending, success, failed
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('response_body', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['webhook_endpoint_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('api_keys');
    }
};
