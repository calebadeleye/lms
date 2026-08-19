<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pre-account membership fee payment: the public membership page's
        // "pay first, then register" flow — no user row exists yet at
        // payment time, only a name + email the payer typed into the modal.
        // Matching that email to the account they create afterward via
        // /register is a manual admin step, not automated here.
        Schema::create('membership_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->unsignedInteger('amount_cents');
            $table->string('currency')->default('NGN');
            $table->string('provider');
            $table->string('provider_reference')->nullable();
            $table->uuid('idempotency_key')->unique();
            // pending|paid|failed
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fee_payments');
    }
};
