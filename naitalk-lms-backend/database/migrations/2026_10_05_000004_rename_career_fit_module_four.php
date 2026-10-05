<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Module 4 of "Find Your Career Fit" is retitled, as asked by the client:
 *
 *   "Career Mapping – Design Your Future" -> "Career Mapping: Build Your Career Road Map"
 *
 * Only the module's title changes — its lessons, ordering and learner
 * progress are untouched. Matches by old title inside the Career Fit course
 * only, so it is safe to run twice; down() restores the old title.
 */
return new class extends Migration
{
    private const OLD = 'Career Mapping – Design Your Future';

    private const NEW = 'Career Mapping: Build Your Career Road Map';

    public function up(): void
    {
        $this->rename(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->rename(self::NEW, self::OLD);
    }

    private function rename(string $from, string $to): void
    {
        $courseId = DB::table('courses')->where('slug', 'find-your-career-fit')->value('id');

        if (! $courseId) {
            return;
        }

        DB::table('course_modules')->where('course_id', $courseId)->where('title', $from)->update(['title' => $to]);
    }
};
