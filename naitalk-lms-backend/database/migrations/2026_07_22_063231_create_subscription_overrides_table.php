<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('value');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('granted_by_platform_staff_id')->nullable()
                ->constrained('platform_staff')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_overrides');
    }
};
