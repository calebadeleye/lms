<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retitles one lesson in each of Career Fit's last two modules, as asked by
 * the client:
 *
 *   Module 3 (Discover Your Personality Type)
 *     "Introduction to Discover Your Personality Type" -> "Matching Personality to Careers"
 *   Module 4 (Career Mapping – Design Your Future)
 *     "Career Mapping: Learning Objectives & Outcomes" -> "Career Mapping: Build Your Career Road Map"
 *
 * Only the lesson rows' titles change — ids, videos, durations and learner
 * progress are untouched. Matches by module title + old lesson title inside
 * the Career Fit course, so it is safe to run twice. down() restores the
 * old titles.
 */
return new class extends Migration
{
    /** [module title => [old lesson title => new lesson title]] */
    private const RENAMES = [
        'Discover Your Personality Type' => [
            'Introduction to Discover Your Personality Type' => 'Matching Personality to Careers',
        ],
        'Career Mapping – Design Your Future' => [
            'Career Mapping: Learning Objectives & Outcomes' => 'Career Mapping: Build Your Career Road Map',
        ],
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_map('array_flip', self::RENAMES));
    }

    /** @param array<string, array<string, string>> $renames */
    private function rename(array $renames): void
    {
        $courseId = DB::table('courses')->where('slug', 'find-your-career-fit')->value('id');

        if (! $courseId) {
            return;
        }

        DB::transaction(function () use ($courseId, $renames) {
            foreach ($renames as $moduleTitle => $titles) {
                $moduleId = DB::table('course_modules')->where('course_id', $courseId)->where('title', $moduleTitle)->value('id');

                if (! $moduleId) {
                    continue;
                }

                foreach ($titles as $from => $to) {
                    DB::table('lessons')
                        ->where('course_module_id', $moduleId)
                        ->whereNull('deleted_at')
                        ->where('title', $from)
                        ->update(['title' => $to]);
                }
            }
        });
    }
};
