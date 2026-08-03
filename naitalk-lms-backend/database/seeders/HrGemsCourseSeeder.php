<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Course catalogue, matching the attached mockup's course cards (titles,
 * levels, ratings, prices, instructor). One additional free course is
 * seeded beyond the mockup so the enrol/learn/quiz flow has something real
 * to demonstrate end-to-end.
 */
class HrGemsCourseSeeder extends Seeder
{
    public function run(): void
    {
        $instructorRole = Role::where('slug', 'instructor')->firstOrFail();

        // email_verified_at isn't in User::$fillable (deliberately — nothing
        // should mass-assign it from request input), so passing it into
        // firstOrCreate()'s attributes silently drops it. forceFill after.
        $instructor = User::firstOrCreate(
            ['email' => env('OWNER_SEED_EMAIL', 'lara.yeku@hrgems.test')],
            [
                'name' => env('OWNER_SEED_NAME', 'Lara Yeku'),
                'password' => env('OWNER_SEED_PASSWORD', 'password'),
                'role_id' => $instructorRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]
        );
        if (! $instructor->email_verified_at) {
            $instructor->forceFill(['email_verified_at' => now()])->save();
        }

        $categories = collect([
            'HR Fundamentals', 'Leadership', 'Recruitment', 'People Management', 'HR Analytics', 'Compliance',
        ])->mapWithKeys(function ($name) {
            $category = CourseCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);

            return [$name => $category];
        });

        $catalogue = [
            ['title' => 'HR Fundamentals: Building Blocks for Success', 'category' => 'HR Fundamentals', 'level' => 'beginner', 'price' => 1500000, 'full_curriculum' => true],
            ['title' => 'Performance Management Masterclass', 'category' => 'People Management', 'level' => 'intermediate', 'price' => 1000000],
            ['title' => 'Recruitment & Selection Excellence', 'category' => 'Recruitment', 'level' => 'beginner', 'price' => 1200000],
            ['title' => 'HR Analytics for Data-Driven Decisions', 'category' => 'HR Analytics', 'level' => 'advanced', 'price' => 1100000],
            ['title' => 'Employee Relations and Engagement', 'category' => 'People Management', 'level' => 'beginner', 'price' => 1200000],
            ['title' => 'Compensation & Benefits Management', 'category' => 'HR Fundamentals', 'level' => 'intermediate', 'price' => 1800000],
        ];

        foreach ($catalogue as $entry) {
            $slug = Str::slug($entry['title']);

            if (Course::where('slug', $slug)->exists()) {
                continue;
            }

            $course = Course::create([
                'category_id' => $categories[$entry['category']]->id,
                'title' => $entry['title'],
                'slug' => $slug,
                'excerpt' => $entry['title'].' — practical, on-demand HR training.',
                'description' => "A practical course on {$entry['title']}, designed for working HR professionals.",
                'status' => 'published',
                'pricing_type' => 'paid',
                'price_cents' => $entry['price'],
                'currency' => 'NGN',
                'difficulty_level' => $entry['level'],
                'certificate_enabled' => true,
                'published_at' => now(),
            ]);

            $course->instructors()->attach($instructor->id, ['role' => 'primary']);

            if ($entry['full_curriculum'] ?? false) {
                $this->seedFullCurriculum($course);
            } else {
                $module = $course->modules()->create(['title' => 'Getting Started', 'sort_order' => 0]);
                $module->lessons()->create([
                    'title' => 'Course Overview', 'type' => 'rich_text',
                    'content' => ['body' => 'Welcome to '.$entry['title'].'.'],
                    'is_preview' => true, 'is_mandatory' => true, 'sort_order' => 0,
                ]);
            }
        }

        $this->seedFreeDemoCourse($categories['HR Fundamentals']);

        \App\Domain\Site\Models\Testimonial::firstOrCreate(
            ['author' => 'Funke A., HR Manager'],
            ['quote' => 'The coaching I received from HR Gems transformed the way I lead my team. Highly recommended!']
        );

        $this->command?->line('Course catalogue seeded.');
    }

    private function seedFullCurriculum(Course $course): void
    {
        $modules = [
            'Introduction to HR' => ['Welcome & Course Overview', 'What is Human Resources?', 'The Role of HR in Business', 'HR Career Paths', 'Module Knowledge Check'],
            'The HR Function' => ['Recruitment Basics', 'Onboarding New Hires', 'Performance Basics', 'Employee Relations 101', 'Compensation Overview', 'Module Knowledge Check'],
            'HR Policies & Compliance' => ['Employment Law Essentials', 'Workplace Policies', 'Data Protection for HR', 'Module Knowledge Check'],
            'HR Best Practices' => ['Building an HR Strategy', 'HR Metrics That Matter', 'Case Study Assignment', 'Course Wrap-up', 'Final Assessment'],
        ];

        $moduleIndex = 0;
        foreach ($modules as $moduleTitle => $lessonTitles) {
            $module = $course->modules()->create(['title' => $moduleTitle, 'sort_order' => $moduleIndex]);

            foreach ($lessonTitles as $lessonIndex => $lessonTitle) {
                $isCheck = str_contains($lessonTitle, 'Knowledge Check') || str_contains($lessonTitle, 'Final Assessment');
                $isAssignment = str_contains($lessonTitle, 'Assignment');

                $lesson = $module->lessons()->create([
                    'title' => $lessonTitle,
                    'type' => $isCheck ? 'quiz' : ($isAssignment ? 'assignment' : 'video'),
                    'content' => $isCheck || $isAssignment ? null : ['transcript' => "Transcript for {$lessonTitle}."],
                    'duration_seconds' => $isCheck || $isAssignment ? null : 600,
                    'is_preview' => $moduleIndex === 0 && $lessonIndex === 0,
                    'is_mandatory' => true,
                    'sort_order' => $lessonIndex,
                ]);

                if ($isCheck) {
                    $quiz = $lesson->quiz()->create(['passing_score_percent' => 70, 'max_attempts' => 3]);
                    $q = $quiz->questions()->create(['type' => 'multiple_choice', 'question_text' => 'HR primarily exists to support which of the following?', 'points' => 10, 'sort_order' => 0]);
                    $q->options()->create(['option_text' => 'The organisation\'s people strategy', 'is_correct' => true, 'sort_order' => 0]);
                    $q->options()->create(['option_text' => 'The IT department only', 'is_correct' => false, 'sort_order' => 1]);
                }

                if ($isAssignment) {
                    $lesson->assignment()->create([
                        'title' => 'Case Study: Diagnose an HR Problem',
                        'instructions' => 'Write a 500-word analysis of an HR challenge at a company of your choice, and propose a resolution.',
                        'max_points' => 100,
                    ]);
                }
            }

            $moduleIndex++;
        }
    }

    private function seedFreeDemoCourse($category): void
    {
        $slug = 'getting-started-with-hr-free-preview';

        if (Course::where('slug', $slug)->exists()) {
            return;
        }

        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Getting Started with HR (Free Preview)',
            'slug' => $slug,
            'excerpt' => 'A free introduction — enrol instantly and try the full learning experience.',
            'description' => 'A short, free course so you can try enrolment, lessons, progress tracking, and a quiz before purchasing a paid course.',
            'status' => 'published',
            'pricing_type' => 'free',
            'difficulty_level' => 'beginner',
            'certificate_enabled' => false,
            'published_at' => now(),
        ]);

        $module = $course->modules()->create(['title' => 'Free Preview', 'sort_order' => 0]);

        $video = $module->lessons()->create([
            'title' => 'Welcome to HR GEMS', 'type' => 'video', 'duration_seconds' => 120,
            'content' => ['transcript' => 'Welcome! This is a short intro video.'],
            'is_preview' => true, 'is_mandatory' => true, 'sort_order' => 0,
        ]);

        $reading = $module->lessons()->create([
            'title' => 'Why HR Matters', 'type' => 'rich_text',
            'content' => ['body' => 'HR sits at the intersection of people and business strategy...'],
            'is_mandatory' => true, 'sort_order' => 1,
        ]);

        $quizLesson = $module->lessons()->create([
            'title' => 'Quick Check', 'type' => 'quiz', 'is_mandatory' => true, 'sort_order' => 2,
        ]);
        $quiz = $quizLesson->quiz()->create(['passing_score_percent' => 50, 'max_attempts' => null]);
        $q = $quiz->questions()->create(['type' => 'true_false', 'question_text' => 'HR is only about hiring.', 'points' => 10, 'sort_order' => 0]);
        $q->options()->create(['option_text' => 'True', 'is_correct' => false, 'sort_order' => 0]);
        $q->options()->create(['option_text' => 'False', 'is_correct' => true, 'sort_order' => 1]);
    }
}
