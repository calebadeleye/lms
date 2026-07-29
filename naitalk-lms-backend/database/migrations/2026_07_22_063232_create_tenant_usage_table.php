<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('metric_key'); // e.g. active_students, storage_bytes
            $table->unsignedBigInteger('value')->default(0);
            $table->date('recorded_for'); // day the metric represents (snapshot)
            $table->timestamps();

            $table->unique(['tenant_id', 'metric_key', 'recorded_for']);
            $table->index(['tenant_id', 'metric_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage');
    }
};
