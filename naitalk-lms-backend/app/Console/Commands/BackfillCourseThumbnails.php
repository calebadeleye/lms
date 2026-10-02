<?php

namespace App\Console\Commands;

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Services\CourseThumbnailFallbackService;
use Illuminate\Console\Command;

/**
 * One-off catch-up for courses that were published before
 * CourseThumbnailFallbackService existed (or were published before Pexels
 * was configured) — new courses get this automatically at publish time via
 * CourseController::publish(), this just backfills the rest.
 */
class BackfillCourseThumbnails extends Command
{
    protected $signature = 'courses:backfill-thumbnails';

    protected $description = 'Fill in a Pexels stock photo for any published course missing a thumbnail';

    public function handle(CourseThumbnailFallbackService $thumbnails): int
    {
        $courses = Course::where('status', 'published')->whereNull('thumbnail_path')->get();

        if ($courses->isEmpty()) {
            $this->info('Every published course already has a thumbnail.');

            return self::SUCCESS;
        }

        foreach ($courses as $course) {
            $thumbnails->fillMissing($course);
            $status = $course->fresh()->thumbnail_path ? 'done' : 'skipped (no match or Pexels unavailable)';
            $this->line("- {$course->title}: {$status}");
        }

        return self::SUCCESS;
    }
}
