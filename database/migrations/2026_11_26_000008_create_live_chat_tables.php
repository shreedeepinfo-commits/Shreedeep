<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('chat_status', 10)->nullable()->index(); // open, pending, resolved
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_inbound_at')->nullable(); // starts the 24-hour reply window
            $table->json('tags')->nullable();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // agent who sent it
            $table->string('media_path')->nullable(); // local copy of sent/received media
            $table->string('media_mime')->nullable();
            $table->string('media_name')->nullable();
        });

        Schema::create('contact_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('system')->default(false); // "Assigned to Ravi" style activity
            $table->timestamps();
        });

        Schema::create('quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('shortcut', 40);
            $table->text('body');
            $table->timestamps();
            $table->unique(['workspace_id', 'shortcut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_replies');
        Schema::dropIfExists('contact_notes');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['media_path', 'media_mime', 'media_name']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn(['chat_status', 'unread_count', 'last_inbound_at', 'tags']);
        });
    }
};
