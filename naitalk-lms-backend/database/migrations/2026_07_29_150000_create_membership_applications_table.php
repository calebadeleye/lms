<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('ack_impact_beyond_earning')->default(false);
            $table->boolean('ack_growth_mindset')->default(false);
            $table->boolean('ack_interest_in_coaching')->default(false);
            $table->boolean('ack_positive_impact')->default(false);
            $table->string('photo_path')->nullable();
            $table->text('motivation')->nullable();
            // pending|approved|rejected
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
