<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An introduction module holds course-level lessons (e.g. a welcome video)
 * that aren't part of the numbered curriculum — the UI shows it as
 * "Introduction" and starts "Module 1" after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_modules', function (Blueprint $table) {
            $table->boolean('is_introduction')->default(false)->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('course_modules', function (Blueprint $table) {
            $table->dropColumn('is_introduction');
        });
    }
};
