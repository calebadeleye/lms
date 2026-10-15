<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Find Your Career Fit" goes from ₦2,500 to ₦2,650, as asked by the client.
 * price_cents is in kobo, so 265_000. The seeder skips a course that already
 * exists, so the live row has to be re-priced here. Only the price changes;
 * existing enrolments and past orders keep what they paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setPrice(265_000);
    }

    public function down(): void
    {
        $this->setPrice(250_000);
    }

    private function setPrice(int $priceCents): void
    {
        DB::table('courses')->where('slug', 'find-your-career-fit')->update(['price_cents' => $priceCents]);
    }
};
