<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Roles are now owner, admin and agent.
        DB::table('workspace_user')->where('role', 'member')->update(['role' => 'agent']);

        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('auto_assign')->default(false); // round-robin new chats to agents
            $table->foreignId('last_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('role', 10);
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_assigned_user_id');
            $table->dropColumn('auto_assign');
        });
        DB::table('workspace_user')->where('role', 'agent')->update(['role' => 'member']);
    }
};
