<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Module 4 of "Find Your Career Fit" ("Part Three: Career Mapping") should
 * contain only the Module 4 video from the course's Drive folder. Its
 * "Why This Matters" lesson had been given the Module 3 video by mistake
 * (that video is now Module 3's introduction), so any lesson here that is
 * not the Module 4 video is removed and the rest renumbered from 0.
 *
 * Lessons are soft-deleted, so learner progress rows are kept and down() can
 * bring them back. Idempotent: does nothing once only the Module 4 video
 * remains.
 */
return new class extends Migration
{
    private const MODULE_TITLE_PREFIX = 'Part Three: Career Mapping';

    private const MODULE_FOUR_VIDEO_ID = '14DKTo6u9O9XiBIiEl_ECwEjOy-YAyxa5';

    public function up(): void
    {
        $moduleId = $this->moduleId();

        if (! $moduleId) {
            return;
        }

        DB::transaction(function () use ($moduleId) {
            DB::table('lessons')
                ->where('course_module_id', $moduleId)
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('video_path')->orWhere('video_path', 'not like', '%'.self::MODULE_FOUR_VIDEO_ID.'%'))
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            $remaining = DB::table('lessons')
                ->where('course_module_id', $moduleId)
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id');

            foreach ($remaining as $index => $lessonId) {
                DB::table('lessons')->where('id', $lessonId)->update(['sort_order' => $index]);
            }
        });
    }

    public function down(): void
    {
        $moduleId = $this->moduleId();

        if (! $moduleId) {
            return;
        }

        DB::transaction(function () use ($moduleId) {
            // Restore in their original relative order, ahead of whatever
            // remains (the removed lesson was Module 4's first).
            $restored = DB::table('lessons')
                ->where('course_module_id', $moduleId)
                ->whereNotNull('deleted_at')
                ->count();

            if ($restored === 0) {
                return;
            }

            DB::table('lessons')
                ->where('course_module_id', $moduleId)
                ->whereNull('deleted_at')
                ->increment('sort_order', $restored);

            $ids = DB::table('lessons')
                ->where('course_module_id', $moduleId)
                ->whereNotNull('deleted_at')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $index => $lessonId) {
                DB::table('lessons')->where('id', $lessonId)->update(['deleted_at' => null, 'sort_order' => $index]);
            }
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
            ->where('title', 'like', self::MODULE_TITLE_PREFIX.'%')
            ->value('id');
    }
};
