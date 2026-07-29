<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // monthly|annual|free|trial|custom
            $table->string('billing_period');
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('trial_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_plans');
    }
};
