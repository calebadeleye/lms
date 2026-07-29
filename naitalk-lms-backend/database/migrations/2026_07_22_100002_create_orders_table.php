<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // pending|paid|failed|refunded|partially_refunded|cancelled
            $table->string('status')->default('pending');
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('fee_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            // client_owned|managed
            $table->string('payment_mode')->nullable();
            // paystack|flutterwave
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['tenant_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
