<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('lead_status', 20)->default('new')->index();
            $table->decimal('deal_value', 12, 2)->nullable();
            $table->timestamp('follow_up_at')->nullable()->index();
            $table->string('follow_up_note')->nullable();
            $table->timestamp('blocked_at')->nullable(); // blacklist: never message this number
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['lead_status']);
            $table->dropIndex(['follow_up_at']);
            $table->dropColumn(['lead_status', 'deal_value', 'follow_up_at', 'follow_up_note', 'blocked_at']);
        });
    }
};
