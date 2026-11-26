<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // welcome, away, keyword, drip, date
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->json('config');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();
            $table->index(['workspace_id', 'type', 'active']);
        });

        Schema::create('automation_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step')->default(0);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->string('status', 10)->default('active'); // active, done, stopped
            $table->timestamps();
            $table->unique(['automation_id', 'contact_id']);
        });

        // One row per automatic message, so nothing is sent twice.
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('run_key', 40);
            $table->timestamp('created_at')->nullable();
            $table->unique(['automation_id', 'contact_id', 'run_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_enrollments');
        Schema::dropIfExists('automations');
    }
};
