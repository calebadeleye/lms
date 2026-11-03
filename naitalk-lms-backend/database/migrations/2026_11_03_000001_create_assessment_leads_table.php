<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People who asked for the free personality assessment (the opt-in on
 * /free-assessment and the homepage). One row per email address; asking
 * again updates the row and re-sends the email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_leads', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            // Where the opt-in happened, e.g. "landing" or "home".
            $table->string('source')->nullable();
            $table->unsignedInteger('request_count')->default(1);
            $table->timestamp('last_requested_at')->nullable();
            // Null when the last send failed — the visitor was shown the link on screen instead.
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_leads');
    }
};
