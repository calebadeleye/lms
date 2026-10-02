<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            // Lets a not-yet-logged-in applicant (see AuthController::register()
            // — it no longer issues a session) start and check on the
            // registration-fee Paystack checkout without a Sanctum token.
            // Unguessable, so knowing it is equivalent to being the applicant.
            $table->uuid('payment_token')->nullable()->unique()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn('payment_token');
        });
    }
};
