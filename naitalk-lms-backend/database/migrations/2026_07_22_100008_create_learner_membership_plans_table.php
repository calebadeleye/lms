<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            // monthly|annual|free
            $table->string('billing_period');
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->json('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_membership_plans');
    }
};
