<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_reference');
            // pending|success|failed
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('gross_amount_cents');
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('provider_fee_cents')->default(0);
            $table->unsignedBigInteger('platform_commission_cents')->default(0);
            $table->unsignedBigInteger('tenant_net_cents')->default(0);
            // tenant|learner|platform
            $table->string('fee_bearer')->default('tenant');
            $table->timestamp('paid_at')->nullable();
            // Provider's raw verify-transaction response, secrets stripped —
            // never the request that carried credentials.
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['tenant_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
