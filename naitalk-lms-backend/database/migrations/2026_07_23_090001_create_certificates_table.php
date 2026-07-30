<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Nullable — a certificate must survive the course or enrolment
            // it was earned from being deleted later; recipient_name/course_title
            // below are the durable record of what was actually issued.
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('enrolment_id')->nullable()->unique()->constrained('enrolments')->nullOnDelete();
            $table->string('certificate_number');
            $table->uuid('verification_code')->unique();
            $table->string('recipient_name');
            $table->string('course_title');
            $table->timestamp('completed_at');
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('certificate_number');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
