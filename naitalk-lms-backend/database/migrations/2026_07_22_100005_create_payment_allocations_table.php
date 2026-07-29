<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            // Enrolment|LearnerSubscription|Booking — the entitlement this
            // payment actually unlocked, distinct from order_items (what was
            // commercially sold). See ARCHITECTURE.md §11.
            $table->string('allocatable_type');
            $table->unsignedBigInteger('allocatable_id');
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();

            $table->index(['tenant_id', 'payment_id']);
            $table->index(['allocatable_type', 'allocatable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
