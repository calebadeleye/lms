<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            // paystack|flutterwave
            $table->string('provider');
            // client_owned|managed
            $table->string('mode');
            $table->string('public_key')->nullable();
            $table->text('secret_key_encrypted')->nullable();
            $table->text('webhook_secret_encrypted')->nullable();
            // Used in the webhook URL path to resolve the tenant — never the
            // hostname, since webhooks come directly from the provider.
            $table->uuid('webhook_token')->unique();
            $table->string('subaccount_code')->nullable();
            // test|live
            $table->string('environment')->default('test');
            // null = use the platform default (DEFAULT_MANAGED_COMMISSION_PERCENT)
            $table->decimal('commission_percent', 5, 2)->nullable();
            // tenant|learner|platform
            $table->string('fee_bearer')->default('tenant');
            // active|disabled
            $table->string('status')->default('active');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_configs');
    }
};
