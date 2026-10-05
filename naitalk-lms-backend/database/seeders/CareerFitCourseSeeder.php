<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the "Find Your Career Fit" course (the Career FIT™ framework), a
 * paid course at ₦2,500 (BOT asked for a price so learners take it
 * seriously) — the one course in the catalogue now that the placeholder HR
 * course set has been removed (see InstructorAndTestimonialSeeder). Run
 * standalone with:
 *   php artisan db:seed --class=CareerFitCourseSeeder
 */
class CareerFitCourseSeeder extends Seeder
{
    public function run(): void
    {
        $slug = 'find-your-career-fit';

        if (Course::where('slug', $slug)->exists()) {
            $this->command?->line('Find Your Career Fit already exists, skipping.');

            return;
        }

        $category = CourseCategory::firstOrCreate(
            ['slug' => Str::slug('Career Development')],
            ['name' => 'Career Development']
        );

        $instructor = User::whereHas('role', fn ($q) => $q->where('slug', 'instructor'))->first();

        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Find Your Career Fit',
            'slug' => $slug,
            'excerpt' => 'A practical, self-paced Career Discovery System — discover who you are, then map a career that fits.',
            'description' => "Finding the right career isn't about luck—it's about knowing yourself.\n\n".
                "Many people choose careers based on external influences such as family expectations, peer pressure, salary prospects, or market trends, only to discover years later that they are unfulfilled and working in roles that don't align with who they truly are.\n\n".
                "Find Your Career Fit is a practical, self-paced online course designed to help you discover your unique strengths, understand your personality, and intentionally map out a career that aligns with your natural abilities, interests, values, and long-term aspirations.\n\n".
                "This is a Career Discovery System rather than just an online course, built around the Career FIT™ Framework:\n\n".
                "F – Find Yourself: Discover your personality, strengths, values, and purpose.\n".
                "I – Identify Your Best-Fit Career: Match who you are with careers where you can thrive.\n".
                "T – Take Action: Build a practical roadmap to launch and grow a meaningful career.\n\n".
                "The course is divided into two transformative parts, taking you from self-awareness to career clarity.\n\n".
                "Your future shouldn't be determined by chance—it should be shaped by clarity. This course will empower you to stop guessing, start discovering, and confidently pursue a career that reflects your strengths, personality, and purpose. Because when you find the right fit, you don't just build a career—you build a life of impact, fulfillment, and lasting success.",
            'status' => 'published',
            'pricing_type' => 'paid',
            // Kobo, not naira: 250_000 = ₦2,500. Keep in sync with the
            // 2026_10_02_000001 data migration that re-prices the live row.
            'price_cents' => 250_000,
            'currency' => 'NGN',
            'difficulty_level' => 'beginner',
            'certificate_enabled' => true,
            'published_at' => now(),
        ]);

        if ($instructor) {
            $course->instructors()->attach($instructor->id, ['role' => 'primary']);
        }

        $moduleIndex = 0;

        // Introduction (unnumbered — not one of the course's modules): framework overview
        $intro = $course->modules()->create(['title' => 'Introduction', 'is_introduction' => true, 'sort_order' => $moduleIndex++]);
        $intro->lessons()->create([
            'title' => 'Welcome to the Career FIT™ Framework',
            'type' => 'rich_text',
            'content' => ['body' =>
                "This is a Career Discovery System rather than just an online course. A memorable framework can become part of your personal brand.\n\n".
                "The Career FIT™ Framework\n\n".
                "F – Find Yourself: Discover your personality, strengths, values, and purpose.\n".
                "I – Identify Your Best-Fit Career: Match who you are with careers where you can thrive.\n".
                "T – Take Action: Build a practical roadmap to launch and grow a meaningful career.\n\n".
                "This is a life changing tool that gives learners a clear, memorable journey and sets them apart as a transformational experience rather than just another collection of videos.",
            ],
            'is_preview' => true,
            'is_mandatory' => true,
            'sort_order' => 0,
        ]);

        // Module 1: Why This Matters
        $partOne = $course->modules()->create(['title' => 'Why This Matters', 'sort_order' => $moduleIndex++]);
        $partOne->lessons()->create([
            'title' => 'Why This Matters',
            'type' => 'rich_text',
            'content' => ['body' =>
                "Your personality influences how you think, communicate, make decisions, solve problems, relate with others, and perform at work.\n\n".
                "When your career aligns with your personality and natural strengths, work becomes more enjoyable, productive, and fulfilling.\n\n".
                "In this section, you'll gain deeper self-awareness through structured assessments and reflective exercises that reveal what makes you unique.\n\n".
                "You'll Discover:\n".
                "- Your dominant personality traits\n".
                "- Your natural strengths and talents\n".
                "- Your preferred work style\n".
                "- Your communication and collaboration style\n".
                "- Your core values and motivations\n".
                "- What energizes and drains you\n".
                "- The type of work environment where you thrive\n\n".
                "By the end of this section, you'll have a clear understanding of who you are and the unique value you bring.",
            ],
            'is_preview' => true,
            'is_mandatory' => true,
            'sort_order' => 0,
        ]);
        // Module 2: Understanding Your Personality
        $understanding = $course->modules()->create(['title' => 'Understanding Your Personality', 'sort_order' => $moduleIndex++]);
        $understanding->lessons()->create([
            'title' => 'Understanding Your Personality: Learning Objectives & Outcomes',
            'type' => 'rich_text',
            'content' => ['body' =>
                "Learning Objectives\n\nBy the end of this module, you will be able to:\n".
                "- Understand your personality profile\n".
                "- Identify your strengths and development areas\n".
                "- Recognize your values and intrinsic motivators\n".
                "- Build greater self-awareness and confidence\n".
                "- Appreciate how personality influences career satisfaction and performance\n\n".
                "Learning Outcomes\n\nYou will leave this section with:\n".
                "- A comprehensive understanding of yourself\n".
                "- A documented strengths profile\n".
                "- Greater confidence in your abilities\n".
                "- Increased self-awareness for informed career decisions",
            ],
            'is_mandatory' => true,
            'sort_order' => 0,
        ]);

        // Module 3: Discover Your Personality Type. Each lesson is
        // one personality type's video, tagged with its code — the learner
        // picks theirs (POST /courses/{id}/personality-type) and only that
        // one lesson is ever shown to them (see CourseController::show()).
        // Real content has 16 MBTI types; seeding 2 representative ones here
        // keeps fresh/staging environments fast while covering the feature.
        // The module opens with an introduction video (no type code, so
        // everyone sees it) before the learner picks their type.
        $personalityTypes = $course->modules()->create(['title' => 'Discover Your Personality Type', 'sort_order' => $moduleIndex++]);
        $personalityTypes->lessons()->create([
            'title' => 'Matching Personality to Careers',
            'type' => 'video',
            'video_path' => 'https://drive.google.com/file/d/1B0XHj-NMrTZCRMoJoWg84Py5Iu4oIJt_/view',
            'is_mandatory' => true,
            'sort_order' => 0,
        ]);
        foreach (['INTJ', 'ENFP'] as $index => $code) {
            $personalityTypes->lessons()->create([
                'title' => "Personality Type: {$code}",
                'type' => 'video',
                'personality_type_code' => $code,
                'is_mandatory' => true,
                'sort_order' => $index + 1,
            ]);
        }

        // Module 4: Career Mapping
        $partTwo = $course->modules()->create(['title' => 'Career Mapping – Design Your Future', 'sort_order' => $moduleIndex++]);
        $partTwo->lessons()->create([
            'title' => 'Career Mapping: Why This Matters',
            'type' => 'rich_text',
            'content' => ['body' =>
                "Self-awareness is only the beginning.\n\n".
                "The next step is knowing how to translate your strengths, personality, passions, and potential into a meaningful and successful career.\n\n".
                "Career Mapping provides a practical roadmap to help you intentionally choose a career path that aligns with who you are and where you want to go.\n\n".
                "Rather than following the crowd, you'll develop a personalized career strategy based on your unique profile.\n\n".
                "You'll Learn How To:\n".
                "- Match your personality to suitable careers\n".
                "- Identify industries and professions that fit your strengths\n".
                "- Evaluate career options objectively\n".
                "- Develop a personal career vision\n".
                "- Build the competencies needed for your chosen career\n".
                "- Create a practical career development roadmap\n".
                "- Position yourself for future career success\n\n".
                "You'll also explore how the world of work is evolving and identify future-ready skills that will help you remain competitive in a rapidly changing workplace.",
            ],
            'is_mandatory' => true,
            'sort_order' => 0,
        ]);
        $partTwo->lessons()->create([
            'title' => 'Career Mapping: Build Your Career Road Map',
            'type' => 'rich_text',
            'content' => ['body' =>
                "Learning Objectives\n\nBy the end of Part Two, you will be able to:\n".
                "- Align your personality with suitable career options\n".
                "- Develop a structured career development plan\n".
                "- Make informed career decisions with confidence\n".
                "- Identify skill gaps and create a learning plan\n".
                "- Set meaningful short-, medium-, and long-term career goals\n\n".
                "Learning Outcomes\n\nYou will complete this section with:\n".
                "- A personalized Career Map\n".
                "- Clearly defined career goals\n".
                "- A practical action plan for achieving those goals\n".
                "- Greater confidence in your career direction\n".
                "- A roadmap for continuous professional growth",
            ],
            'is_mandatory' => true,
            'sort_order' => 1,
        ]);

        $this->command?->line('Find Your Career Fit course seeded.');
    }
}
