<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->text('quote');
            $table->string('author');
            $table->timestamps();
        });

        // Homepage content used to carry a single testimonial inline as
        // `tenant_branding.homepage_json.testimonial`. Back-fill it into the
        // new table so tenants that already had one keep showing it after
        // the frontend switches to reading from here instead.
        $allBranding = DB::table('tenant_branding')->select('tenant_id', 'homepage_json')->get();

        foreach ($allBranding as $branding) {
            $homepage = json_decode($branding->homepage_json ?? '[]', true) ?? [];
            $testimonial = $homepage['testimonial'] ?? null;

            if (! empty($testimonial['quote']) && ! empty($testimonial['author'])) {
                DB::table('tenant_testimonials')->insert([
                    'tenant_id' => $branding->tenant_id,
                    'quote' => $testimonial['quote'],
                    'author' => $testimonial['author'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_testimonials');
    }
};
