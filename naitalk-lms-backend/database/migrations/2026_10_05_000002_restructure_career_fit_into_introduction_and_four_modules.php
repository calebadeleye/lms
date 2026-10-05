<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Find Your Career Fit" used to number its welcome lesson as Module 1. The
 * welcome video is a course introduction, not a module, so it becomes an
 * unnumbered "Introduction", and the four numbered modules are:
 *
 *   Introduction                       Welcome to the Career FIT™ Framework
 *   Module 1: Why This Matters         Why This Matters
 *   Module 2: Understanding Your Personality
 *                                      Understanding Your Personality: Learning Objectives & Outcomes
 *   Module 3: Discover Your Personality Type   (intro + the 16 type videos — unchanged)
 *   Module 4: Career Mapping – Design Your Future (unchanged)
 *
 * The old "Personality Assessment" module held two lessons; its first one
 * stays and the module becomes "Why This Matters", and its second one moves
 * into a new "Understanding Your Personality" module. Lessons move rather
 * than being recreated, so videos, durations and learner progress follow.
 *
 * Matches by title within the Career Fit course only, and is safe to run
 * twice. down() puts both lessons back in one module and restores the titles.
 */
return new class extends Migration
{
    private const INTRO_OLD = 'About This Course';

    private const INTRO_NEW = 'Introduction';

    private const ASSESSMENT = 'Personality Assessment – Discover Who You Are';

    private const WHY = 'Why This Matters';

    private const UNDERSTANDING = 'Understanding Your Personality';

    private const TYPES = 'Discover Your Personality Type';

    private const CAREER = 'Career Mapping – Design Your Future';

    private const WHY_LESSON_OLD = 'Personality Assessment: Why This Matters';

    private const OBJECTIVES_LESSON_OLD = 'Personality Assessment: Learning Objectives & Outcomes';

    private const OBJECTIVES_LESSON_NEW = 'Understanding Your Personality: Learning Objectives & Outcomes';

    public function up(): void
    {
        $courseId = $this->courseId();

        if (! $courseId) {
            return;
        }

        DB::transaction(function () use ($courseId) {
            DB::table('course_modules')->where('course_id', $courseId)->where('title', self::INTRO_OLD)
                ->update(['title' => self::INTRO_NEW]);
            DB::table('course_modules')->where('course_id', $courseId)->where('title', self::INTRO_NEW)
                ->update(['is_introduction' => true]);

            $whyId = $this->moduleId($courseId, [self::ASSESSMENT, self::WHY]);

            if ($whyId && ! $this->moduleId($courseId, [self::UNDERSTANDING])) {
                DB::table('course_modules')->where('id', $whyId)->update(['title' => self::WHY]);

                $understandingId = DB::table('course_modules')->insertGetId([
                    'course_id' => $courseId,
                    'title' => self::UNDERSTANDING,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('lessons')->where('course_module_id', $whyId)->whereNull('deleted_at')
                    ->where('title', self::WHY_LESSON_OLD)
                    ->update(['title' => self::WHY, 'sort_order' => 0]);

                DB::table('lessons')->where('course_module_id', $whyId)->whereNull('deleted_at')
                    ->where('title', self::OBJECTIVES_LESSON_OLD)
                    ->update(['course_module_id' => $understandingId, 'title' => self::OBJECTIVES_LESSON_NEW, 'sort_order' => 0]);
            }

            $this->setOrder($courseId, [
                [[self::INTRO_NEW, self::INTRO_OLD], 0],
                [[self::WHY], 1],
                [[self::UNDERSTANDING], 2],
                [[self::TYPES], 3],
                [[self::CAREER], 4],
            ]);
        });
    }

    public function down(): void
    {
        $courseId = $this->courseId();

        if (! $courseId) {
            return;
        }

        DB::transaction(function () use ($courseId) {
            $whyId = $this->moduleId($courseId, [self::WHY]);
            $understandingId = $this->moduleId($courseId, [self::UNDERSTANDING]);

            if ($whyId && $understandingId) {
                DB::table('lessons')->where('course_module_id', $whyId)->whereNull('deleted_at')
                    ->where('title', self::WHY)
                    ->update(['title' => self::WHY_LESSON_OLD, 'sort_order' => 0]);

                DB::table('lessons')->where('course_module_id', $understandingId)->whereNull('deleted_at')
                    ->where('title', self::OBJECTIVES_LESSON_NEW)
                    ->update(['course_module_id' => $whyId, 'title' => self::OBJECTIVES_LESSON_OLD, 'sort_order' => 1]);

                // Only drop the module once it is really empty (soft-deleted
                // lessons included, so nothing is orphaned).
                if (! DB::table('lessons')->where('course_module_id', $understandingId)->exists()) {
                    DB::table('course_modules')->where('id', $understandingId)->delete();
                }

                DB::table('course_modules')->where('id', $whyId)->update(['title' => self::ASSESSMENT]);
            }

            DB::table('course_modules')->where('course_id', $courseId)->where('title', self::INTRO_NEW)
                ->update(['title' => self::INTRO_OLD, 'is_introduction' => false]);

            $this->setOrder($courseId, [
                [[self::INTRO_OLD], 0],
                [[self::ASSESSMENT], 1],
                [[self::TYPES], 2],
                [[self::CAREER], 3],
            ]);
        });
    }

    /** @param array<int, array{0: array<int, string>, 1: int}> $order */
    private function setOrder(int $courseId, array $order): void
    {
        foreach ($order as [$titles, $sortOrder]) {
            DB::table('course_modules')->where('course_id', $courseId)->whereIn('title', $titles)
                ->update(['sort_order' => $sortOrder]);
        }
    }

    /** @param array<int, string> $titles */
    private function moduleId(int $courseId, array $titles): ?int
    {
        return DB::table('course_modules')->where('course_id', $courseId)->whereIn('title', $titles)->value('id');
    }

    private function courseId(): ?int
    {
        return DB::table('courses')->where('slug', 'find-your-career-fit')->value('id');
    }
};
