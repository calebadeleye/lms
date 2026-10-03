<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Find Your Career Fit" has four modules, but its titles said "Part One /
 * Two / Three" (so Module 2 read "Part One") and two lessons were both
 * called "Learning Objectives & Outcomes". The UI now numbers the modules
 * itself ("Module 1…4"), so the "Part …" prefixes are dropped, and the
 * lessons that shared a generic name are prefixed with their module's name.
 *
 * Matches by the old/new title, so it only touches the Career Fit course
 * and is safe to run twice. down() restores the old titles.
 */
return new class extends Migration
{
    /** [old module title prefix => new module title] */
    private const MODULES = [
        'Part One: Personality Assessment – Discover Who You Are' => 'Personality Assessment – Discover Who You Are',
        'Part Two: Discover Your Personality Type' => 'Discover Your Personality Type',
        'Part Three: Career Mapping – Design Your Future' => 'Career Mapping – Design Your Future',
    ];

    /** [module's new title => [old lesson title => new lesson title]] */
    private const LESSONS = [
        'Personality Assessment – Discover Who You Are' => [
            'Why This Matters' => 'Personality Assessment: Why This Matters',
            'Learning Objectives & Outcomes' => 'Personality Assessment: Learning Objectives & Outcomes',
        ],
        'Career Mapping – Design Your Future' => [
            'Learning Objectives & Outcomes' => 'Career Mapping: Learning Objectives & Outcomes',
        ],
    ];

    public function up(): void
    {
        $courseId = $this->courseId();

        if (! $courseId) {
            return;
        }

        DB::transaction(function () use ($courseId) {
            foreach (self::MODULES as $old => $new) {
                DB::table('course_modules')->where('course_id', $courseId)->where('title', $old)->update(['title' => $new]);
            }

            foreach (self::LESSONS as $moduleTitle => $renames) {
                $this->renameLessons($courseId, $moduleTitle, $renames);
            }
        });
    }

    public function down(): void
    {
        $courseId = $this->courseId();

        if (! $courseId) {
            return;
        }

        DB::transaction(function () use ($courseId) {
            foreach (self::LESSONS as $moduleTitle => $renames) {
                $this->renameLessons($courseId, $moduleTitle, array_flip($renames));
            }

            foreach (self::MODULES as $old => $new) {
                DB::table('course_modules')->where('course_id', $courseId)->where('title', $new)->update(['title' => $old]);
            }
        });
    }

    /** @param array<string, string> $renames */
    private function renameLessons(int $courseId, string $moduleTitle, array $renames): void
    {
        // The lessons are looked up under whichever title the module has at
        // this point — new on the way up, and (after the reverse module
        // rename hasn't happened yet) still new on the way down.
        $moduleId = DB::table('course_modules')->where('course_id', $courseId)->where('title', $moduleTitle)->value('id');

        if (! $moduleId) {
            return;
        }

        foreach ($renames as $from => $to) {
            DB::table('lessons')
                ->where('course_module_id', $moduleId)
                ->whereNull('deleted_at')
                ->where('title', $from)
                ->update(['title' => $to]);
        }
    }

    private function courseId(): ?int
    {
        return DB::table('courses')->where('slug', 'find-your-career-fit')->value('id');
    }
};
