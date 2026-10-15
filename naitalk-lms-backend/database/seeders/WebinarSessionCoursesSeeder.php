<?php

namespace Database\Seeders;

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseCategory;
use App\Domain\Learning\Services\CourseThumbnailFallbackService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Three recorded HR GEMs Knowledge Sharing Sessions (KSS), each published as
 * a free single-video course whose lesson plays the YouTube recording. Safe
 * to run more than once — a course whose slug already exists is skipped, so
 * it never overwrites edits made in the admin. Run standalone with:
 *   php artisan db:seed --class=WebinarSessionCoursesSeeder
 */
class WebinarSessionCoursesSeeder extends Seeder
{
    private const SESSIONS = [
        [
            'slug' => 'free-career-clarity-webinar',
            'title' => 'Free Career Clarity (F.I.T) Webinar',
            'category' => 'Career Development',
            'excerpt' => 'Recording of the HR GEMs KSS Career F.I.T webinar, designed to provide clarity on career-related questions.',
            'description' => "Recording of the HR GEMs Knowledge Sharing Session (KSS) Career F.I.T webinar, designed to provide clarity on career-related questions.\n\nWatch the session at your own pace, then mark it complete.",
            'video' => 'https://youtu.be/noNcoYHlI10',
        ],
        [
            'slug' => 'mindfulness-and-inner-power',
            'title' => 'Mindfulness & Inner Power',
            'category' => 'Personal Development',
            'excerpt' => 'Recording of the September 2026 HR GEMs KSS session on Mindfulness & Inner Power, by Dr. Osaz.',
            'description' => "Recording of the September 2026 HR GEMs Knowledge Sharing Session (KSS) on Mindfulness & Inner Power, by Dr. Osaz.\n\nWatch the session at your own pace, then mark it complete.",
            'video' => 'https://youtu.be/U1RyIkc_3wo',
        ],
        [
            'slug' => 'identity-shift-redefining-success',
            'title' => 'Identity Shift: Redefining Success',
            'category' => 'Personal Development',
            'excerpt' => 'Recording of the HR GEMs KSS session with Ada Onyeri on Identity Shift: Redefining Success.',
            'description' => "Recording of the HR GEMs Knowledge Sharing Session (KSS) with Ada Onyeri on Identity Shift: Redefining Success.\n\nWatch the session at your own pace, then mark it complete.",
            'video' => 'https://youtu.be/owRj8VN1UAE',
        ],
    ];

    public function run(): void
    {
        foreach (self::SESSIONS as $session) {
            if (Course::where('slug', $session['slug'])->exists()) {
                $this->command?->line("{$session['title']} already exists, skipping.");

                continue;
            }

            $category = CourseCategory::firstOrCreate(
                ['slug' => Str::slug($session['category'])],
                ['name' => $session['category']]
            );

            $course = Course::create([
                'category_id' => $category->id,
                'title' => $session['title'],
                'slug' => $session['slug'],
                'excerpt' => $session['excerpt'],
                'description' => $session['description'],
                'status' => 'published',
                'pricing_type' => 'free',
                'difficulty_level' => 'beginner',
                'certificate_enabled' => false,
                'published_at' => now(),
            ]);

            $module = $course->modules()->create(['title' => 'Watch the Session', 'sort_order' => 0]);
            $module->lessons()->create([
                'title' => $session['title'],
                'type' => 'video',
                'video_path' => $session['video'],
                'is_mandatory' => true,
                'sort_order' => 0,
            ]);

            // Same stock-photo fallback publishing a course in the admin
            // uses (a no-op when no Pexels key is configured).
            app(CourseThumbnailFallbackService::class)->fillMissing($course);

            $this->command?->line("{$session['title']} seeded.");
        }
    }
}
