<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // Course|LearnerMembershipPlan|CoachingService — polymorphic on
            // purpose (see ARCHITECTURE.md §11 for why there's no separate
            // products/prices table).
            $table->string('itemable_type');
            $table->unsignedBigInteger('itemable_id');
            // Snapshot at purchase time — never re-derived from the live
            // record, so a later price change never rewrites history.
            $table->string('name');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'order_id']);
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
