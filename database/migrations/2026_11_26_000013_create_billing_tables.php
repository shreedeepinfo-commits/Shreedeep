<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('plan_cycle', 10)->nullable()->after('plan');
            $table->timestamp('plan_ends_at')->nullable()->after('trial_ends_at');
            $table->string('billing_name')->nullable();
            $table->string('billing_gstin', 15)->nullable();
            $table->string('billing_address', 500)->nullable();
            $table->string('billing_state', 60)->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 20)->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 10); // razorpay, cashfree, test
            $table->string('plan', 20);
            $table->string('cycle', 10);
            $table->decimal('amount', 10, 2); // before GST
            $table->decimal('tax', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('status', 10)->default('created'); // created, paid, failed
            $table->string('order_id')->unique();
            $table->string('gateway_payment_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('issued_on');
            $table->json('seller');
            $table->json('buyer');
            $table->string('description');
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('cgst', 10, 2)->default(0);
            $table->decimal('sgst', 10, 2)->default(0);
            $table->decimal('igst', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['plan_cycle', 'plan_ends_at', 'billing_name', 'billing_gstin', 'billing_address', 'billing_state', 'billing_email', 'billing_phone']);
        });
    }
};
