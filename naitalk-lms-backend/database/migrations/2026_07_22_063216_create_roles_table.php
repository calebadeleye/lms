<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            // null tenant_id = platform-scoped role (Super Admin, Operations, ...)
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            // MySQL unique indexes don't treat multiple NULLs as duplicates,
            // so this only enforces uniqueness among tenant-scoped roles.
            // Platform roles (tenant_id null) are few and admin-managed;
            // RoleService additionally guards slug uniqueness for them.
            $table->unique(['tenant_id', 'slug']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
