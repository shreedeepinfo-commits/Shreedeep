<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20); // digits only, with country code, e.g. 919876543210
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->json('fields')->nullable(); // extra CSV columns, usable as template variables
            $table->timestamp('opted_in_at')->nullable();
            $table->string('opt_in_source')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'phone']);
        });

        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('contact_contact_group', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'contact_group_id']);
        });

        Schema::create('contact_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('default_country_code', 4)->nullable();
            $table->string('opt_in_source')->nullable();
            $table->string('status')->default('pending'); // pending, processing, done, failed
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_imports');
        Schema::dropIfExists('contact_contact_group');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('contacts');
    }
};
