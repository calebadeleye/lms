<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaching_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // one_to_one|group
            $table->string('session_type')->default('one_to_one');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->boolean('is_free')->default(false);
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->unsignedInteger('max_participants')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('coach_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_services');
    }
};
