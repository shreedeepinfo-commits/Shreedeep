<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false);
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable();
            $table->string('admin_note', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_super_admin'));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['suspended_at', 'admin_note']));
    }
};
