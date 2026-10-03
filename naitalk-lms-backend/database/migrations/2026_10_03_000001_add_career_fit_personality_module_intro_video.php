<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Module 3 of "Find Your Career Fit" ("Discover Your Personality Type") now
 * opens with its own introduction video, watched before the learner selects
 * their personality type. The seeder skips a course that already exists, so
 * the live module has to get the lesson here.
 *
 * The intro has no personality_type_code, so — unlike the 16 type videos —
 * every enrolled learner sees it. It takes sort_order 0, which pushes the
 * type videos down by one.
 *
 * Idempotent: does nothing if the intro is already in the module.
 */
return new class extends Migration
{
    private const MODULE_TITLE = 'Part Two: Discover Your Personality Type';

    private const INTRO_TITLE = 'Introduction to Discover Your Personality Type';

    private const INTRO_VIDEO = 'https://drive.google.com/file/d/1B0XHj-NMrTZCRMoJoWg84Py5Iu4oIJt_/view';

    public function up(): void
    {
        $moduleId = $this->moduleId();

        if (! $moduleId || $this->introQuery($moduleId)->exists()) {
            return;
        }

        DB::transaction(function () use ($moduleId) {
            DB::table('lessons')
                ->where('course_module_id', $moduleId)
                ->whereNull('deleted_at')
                ->increment('sort_order');

            DB::table('lessons')->insert([
                'course_module_id' => $moduleId,
                'title' => self::INTRO_TITLE,
                'type' => 'video',
                'video_path' => self::INTRO_VIDEO,
                'is_preview' => false,
                'is_mandatory' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        $moduleId = $this->moduleId();

        if (! $moduleId || ! $this->introQuery($moduleId)->exists()) {
            return;
        }

        DB::transaction(function () use ($moduleId) {
            $this->introQuery($moduleId)->delete();

            DB::table('lessons')
                ->where('course_module_id', $moduleId)
                ->whereNull('deleted_at')
                ->where('sort_order', '>', 0)
                ->decrement('sort_order');
        });
    }

    private function moduleId(): ?int
    {
        $courseId = DB::table('courses')->where('slug', 'find-your-career-fit')->value('id');

        if (! $courseId) {
            return null;
        }

        return DB::table('course_modules')
            ->where('course_id', $courseId)
            ->where('title', self::MODULE_TITLE)
            ->value('id');
    }

    private function introQuery(int $moduleId)
    {
        return DB::table('lessons')
            ->where('course_module_id', $moduleId)
            ->where('title', self::INTRO_TITLE)
            ->whereNull('deleted_at');
    }
};
