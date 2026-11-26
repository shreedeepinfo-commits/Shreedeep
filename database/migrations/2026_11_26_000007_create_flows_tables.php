<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->nullable()->constrained()->nullOnDelete(); // null = all numbers
            $table->string('name');
            $table->json('triggers'); // keywords, matched case-insensitively against the whole incoming text
            $table->string('status')->default('draft'); // draft, active
            $table->json('definition'); // {"start": nodeId, "nodes": {id: {...}}}
            $table->unsignedInteger('triggered_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('flow_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->string('current_node')->nullable();
            $table->string('status')->default('active'); // active, completed, expired
            $table->json('answers')->nullable(); // node id => chosen option / typed answer
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
            $table->index(['contact_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_sessions');
        Schema::dropIfExists('flows');
    }
};
