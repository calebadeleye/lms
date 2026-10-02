<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            // pending|paid — the ₦20,000 registration fee gate. Kept separate
            // from `status` (the approval-review record) since payment and
            // admin review are independent: an applicant can be paid and
            // still awaiting review, but must never be approved unpaid.
            $table->string('payment_status')->default('pending')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
