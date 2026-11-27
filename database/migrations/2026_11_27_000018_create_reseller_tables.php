<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('resellers')->nullOnDelete(); // null = reseller of the platform owner
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the login that manages it
            $table->string('name'); // brand name shown to its customers
            $table->string('product_name')->nullable();
            $table->string('slug', 40)->unique(); // subdomain: slug.base-domain
            $table->string('custom_domain')->nullable()->unique();
            $table->string('logo_url', 500)->nullable();
            $table->string('color', 7)->default('#0B4E99');
            $table->string('support_email')->nullable();
            $table->string('support_phone', 30)->nullable();
            $table->bigInteger('balance')->default(0); // wallet, in paise
            $table->bigInteger('low_balance_alert')->default(100000); // paise
            $table->json('costs')->nullable(); // plan => monthly rupees the reseller pays (set by its parent)
            $table->json('prices')->nullable(); // plan => monthly rupees the reseller charges its customers
            $table->unsignedSmallInteger('trial_days')->default(7);
            $table->boolean('can_create_resellers')->default(false);
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // credit, debit
            $table->string('reason', 20); // recharge, transfer, activation, renewal, adjustment, refund
            $table->bigInteger('amount'); // paise, always positive
            $table->bigInteger('balance_after');
            $table->string('description');
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('related_reseller_id')->nullable()->constrained('resellers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['reseller_id', 'id']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('renew_failed_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->constrained()->nullOnDelete(); // set for reseller logins
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('reseller_id'));
        Schema::table('workspaces', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reseller_id');
            $t->dropColumn(['auto_renew', 'renew_failed_at']);
        });
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('resellers');
    }
};
