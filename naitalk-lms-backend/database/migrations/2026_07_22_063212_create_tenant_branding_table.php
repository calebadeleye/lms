<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('primary_color', 9)->default('#3B0F32');
            $table->string('secondary_color', 9)->default('#F5B84B');
            $table->string('accent_color', 9)->default('#E8A33D');
            $table->string('font_family')->default('Inter, system-ui, sans-serif');
            $table->json('homepage_json')->nullable();
            $table->json('contact_json')->nullable();
            $table->json('social_json')->nullable();
            $table->string('email_sender_name')->nullable();
            $table->string('pwa_name')->nullable();
            $table->string('pwa_theme_color', 9)->nullable();
            $table->string('pwa_icon_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_branding');
    }
};
