<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('course_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('excerpt')->nullable();
            $table->longText('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('promo_video_path')->nullable();
            // draft|published
            $table->string('status')->default('draft');
            // free|paid|membership_only
            $table->string('pricing_type')->default('free');
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('NGN');
            // beginner|intermediate|advanced
            $table->string('difficulty_level')->default('beginner');
            $table->json('tags')->nullable();
            $table->json('prerequisite_course_ids')->nullable();
            // none|scheduled
            $table->string('drip_type')->default('none');
            $table->boolean('certificate_enabled')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index('status');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
