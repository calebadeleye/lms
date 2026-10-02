<?php

namespace App\Console\Commands;

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseModule;
use App\Domain\Learning\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off restructuring of the live "Find Your Career Fit" course: pulls the
 * 16 MBTI personality-type videos out of the Personality Assessment module
 * (where they were previously all mandatory, forcing every learner to watch
 * all 16) into their own "Discover Your Personality Type" module, tags each
 * with its MBTI code so CourseController/LessonController/
 * CourseCompletionService can filter down to just the learner's selected
 * type, bumps Career Mapping to Module 4, and removes the old "Course
 * Summary & Promise" module entirely so the course now ends at Module 4.
 *
 * Safe to re-run: every step checks current state before mutating.
 */
class RestructureCareerFitPersonalityVideos extends Command
{
    protected $signature = 'courses:restructure-career-fit-personality-videos {--dry-run}';

    protected $description = 'Move the 16 MBTI video lessons into their own module, tag them, reorder modules, and drop Course Summary & Promise';

    private const ABOUT_MODULE_TITLE = 'About This Course';

    private const ASSESSMENT_MODULE_TITLE = 'Part One: Personality Assessment – Discover Who You Are';

    private const PERSONALITY_MODULE_TITLE = 'Part Two: Discover Your Personality Type';

    private const CAREER_MAPPING_OLD_TITLE = 'Part Two: Career Mapping – Design Your Future';

    private const CAREER_MAPPING_NEW_TITLE = 'Part Three: Career Mapping – Design Your Future';

    private const SUMMARY_MODULE_TITLE = 'Course Summary & Promise';

    public function handle(): int
    {
        $course = Course::where('slug', 'find-your-career-fit')->first();

        if (! $course) {
            $this->error('Course "find-your-career-fit" not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info("Course: {$course->title} (id {$course->id})".($dryRun ? ' — DRY RUN, no changes will be written' : ''));
        $this->line('');

        if ($dryRun) {
            $this->restructure($course);
        } else {
            DB::transaction(fn () => $this->restructure($course));
        }

        $this->line('');
        $this->info($dryRun ? 'Dry run complete — no changes written.' : 'Done.');

        return self::SUCCESS;
    }

    private function restructure(Course $course): void
    {
        $dryRun = (bool) $this->option('dry-run');

        $aboutModule = $course->modules()->where('title', self::ABOUT_MODULE_TITLE)->first();
        $assessmentModule = $course->modules()->where('title', self::ASSESSMENT_MODULE_TITLE)->first();
        $careerMappingModule = $course->modules()
            ->whereIn('title', [self::CAREER_MAPPING_OLD_TITLE, self::CAREER_MAPPING_NEW_TITLE])
            ->first();
        $summaryModule = $course->modules()->where('title', self::SUMMARY_MODULE_TITLE)->first();
        $personalityModule = $course->modules()->where('title', self::PERSONALITY_MODULE_TITLE)->first();

        $personalityLessons = Lesson::query()
            ->whereHas('courseModule', fn ($q) => $q->where('course_id', $course->id))
            ->where('title', 'like', 'Personality Type: %')
            ->orderBy('title')
            ->get();

        if ($personalityLessons->isEmpty() && ! $personalityModule) {
            $this->warn('No "Personality Type: XXXX" lessons found and no personality module exists yet — nothing to restructure.');

            return;
        }

        // 1. Tag each personality lesson with its MBTI code.
        foreach ($personalityLessons as $lesson) {
            if ($lesson->personality_type_code) {
                continue;
            }

            if (! preg_match('/Personality Type:\s*([A-Z]{4})/', $lesson->title, $m)) {
                $this->warn("  Skipping lesson #{$lesson->id} \"{$lesson->title}\" — couldn't parse a 4-letter code from its title.");

                continue;
            }

            $this->line("  Tag lesson #{$lesson->id} \"{$lesson->title}\" -> personality_type_code={$m[1]}");

            if (! $dryRun) {
                $lesson->update(['personality_type_code' => $m[1]]);
            }
        }

        // 2. Ensure the personality-type module exists.
        if (! $personalityModule) {
            $this->line('Create module "'.self::PERSONALITY_MODULE_TITLE.'"');

            if (! $dryRun) {
                $personalityModule = $course->modules()->create([
                    'title' => self::PERSONALITY_MODULE_TITLE,
                    'sort_order' => 2,
                ]);
            }
        }

        // 3. Move the 16 lessons into it, renumbering sort_order alphabetically.
        foreach ($personalityLessons as $index => $lesson) {
            $needsMove = $personalityModule && $lesson->course_module_id !== $personalityModule->id;
            $needsReorder = $lesson->sort_order !== $index;

            if ($needsMove || $needsReorder) {
                $this->line("  Move lesson #{$lesson->id} \"{$lesson->title}\" -> module \"".self::PERSONALITY_MODULE_TITLE."\", sort_order={$index}");
            }

            if (! $dryRun && $personalityModule) {
                $lesson->update(['course_module_id' => $personalityModule->id, 'sort_order' => $index]);
            }
        }

        // 4. Retitle Career Mapping and reorder every module into its final position.
        if ($careerMappingModule && $careerMappingModule->title !== self::CAREER_MAPPING_NEW_TITLE) {
            $this->line('Retitle "'.self::CAREER_MAPPING_OLD_TITLE.'" -> "'.self::CAREER_MAPPING_NEW_TITLE.'"');

            if (! $dryRun) {
                $careerMappingModule->update(['title' => self::CAREER_MAPPING_NEW_TITLE]);
            }
        }

        $desiredOrder = [
            [$aboutModule, 0],
            [$assessmentModule, 1],
            [$personalityModule, 2],
            [$careerMappingModule, 3],
        ];

        foreach ($desiredOrder as [$module, $sortOrder]) {
            if ($module && $module->sort_order !== $sortOrder) {
                $this->line("  Set \"{$module->title}\" sort_order={$sortOrder}");

                if (! $dryRun) {
                    $module->update(['sort_order' => $sortOrder]);
                }
            }
        }

        // 5. Remove the old Course Summary & Promise module entirely.
        if ($summaryModule) {
            $lessonCount = $summaryModule->lessons()->count();
            $this->line("Delete module \"{$summaryModule->title}\" (id {$summaryModule->id}) and its {$lessonCount} lesson(s)");

            if (! $dryRun) {
                $summaryModule->delete();
            }
        } else {
            $this->line('"'.self::SUMMARY_MODULE_TITLE.'" already removed.');
        }
    }
}
