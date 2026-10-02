<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Find Your Career Fit" moves from free to ₦2,500 (BOT's request, so
 * learners take it seriously). The seeder skips a course that already
 * exists, so the live row has to be re-priced here. price_cents is in kobo.
 *
 * Existing enrolments are untouched — anyone already enrolled keeps access.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('courses')
            ->where('slug', 'find-your-career-fit')
            ->update(['pricing_type' => 'paid', 'price_cents' => 250_000, 'currency' => 'NGN']);
    }

    public function down(): void
    {
        DB::table('courses')
            ->where('slug', 'find-your-career-fit')
            ->update(['pricing_type' => 'free', 'price_cents' => 0]);
    }
};
