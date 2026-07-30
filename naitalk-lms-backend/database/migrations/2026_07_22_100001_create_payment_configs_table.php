<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row config table: the one organization's payment gateway.
        // PaymentConfigController reads it with firstOrCreate(), never by id.
        Schema::create('payment_configs', function (Blueprint $table) {
            $table->id();
            // paystack|flutterwave
            $table->string('provider');
            // client_owned = the organization's own gateway credentials and
            // money goes straight to them; managed = NAI TALK collects and
            // remits net of commission_percent.
            $table->string('mode');
            $table->string('public_key')->nullable();
            $table->text('secret_key_encrypted')->nullable();
            $table->text('webhook_secret_encrypted')->nullable();
            // Used in the webhook URL path — webhooks come directly from the
            // provider's servers, so the URL carries its own opaque secret.
            $table->uuid('webhook_token')->unique();
            $table->string('subaccount_code')->nullable();
            // test|live
            $table->string('environment')->default('test');
            // null = use the default (DEFAULT_MANAGED_COMMISSION_PERCENT)
            $table->decimal('commission_percent', 5, 2)->nullable();
            // organization|learner|platform
            $table->string('fee_bearer')->default('organization');
            // active|disabled
            $table->string('status')->default('active');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_configs');
    }
};
