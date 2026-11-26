<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('waba_id')->index();
            $table->string('phone_number_id')->unique();
            $table->string('business_id')->nullable();
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('quality_rating')->nullable();
            $table->string('messaging_limit')->nullable();
            $table->text('access_token'); // encrypted cast
            $table->text('pin')->nullable(); // encrypted cast, two-step verification PIN
            $table->string('status')->default('connected'); // connected, pending, disconnected, error
            $table->string('connected_via')->default('embedded_signup'); // embedded_signup, manual
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
