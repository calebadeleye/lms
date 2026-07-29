<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('hostname')->unique();
            // platform_subdomain|custom_subdomain|custom_domain
            $table->string('domain_type');
            $table->string('verification_token')->nullable();
            // pending|verified|failed
            $table->string('verification_status')->default('pending');
            // pending|active|failed
            $table->string('ssl_status')->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->text('last_verification_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_primary']);
            $table->index(['tenant_id', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
