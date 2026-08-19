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
 * free course — the one course in the catalogue now that the placeholder HR
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
            'pricing_type' => 'free',
            'difficulty_level' => 'beginner',
            'certificate_enabled' => true,
            'published_at' => now(),
        ]);

        if ($instructor) {
            $course->instructors()->attach($instructor->id, ['role' => 'primary']);
        }

        $moduleIndex = 0;

        // Module 0: framework overview
        $intro = $course->modules()->create(['title' => 'About This Course', 'sort_order' => $moduleIndex++]);
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

        // Module 1: Part One - Personality Assessment
        $partOne = $course->modules()->create(['title' => 'Part One: Personality Assessment – Discover Who You Are', 'sort_order' => $moduleIndex++]);
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
        $partOne->lessons()->create([
            'title' => 'Learning Objectives & Outcomes',
            'type' => 'rich_text',
            'content' => ['body' =>
                "Learning Objectives\n\nBy the end of Part One, you will be able to:\n".
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
            'sort_order' => 1,
        ]);

        // Module 2: Part Two - Career Mapping
        $partTwo = $course->modules()->create(['title' => 'Part Two: Career Mapping – Design Your Future', 'sort_order' => $moduleIndex++]);
        $partTwo->lessons()->create([
            'title' => 'Why This Matters',
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
            'title' => 'Learning Objectives & Outcomes',
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

        // Module 3: wrap-up
        $wrapUp = $course->modules()->create(['title' => 'Course Summary & Promise', 'sort_order' => $moduleIndex++]);
        $wrapUp->lessons()->create([
            'title' => 'Overall Course Objectives & Outcomes',
            'type' => 'rich_text',
            'content' => ['body' =>
                "Overall Course Objectives\n\nAt the end of this course, you will be able to:\n".
                "- Develop deep self-awareness through personality and strengths assessment.\n".
                "- Understand how your personality, values, and interests influence career success.\n".
                "- Identify careers that align with your unique strengths and aspirations.\n".
                "- Create a personalized career roadmap with clear milestones.\n".
                "- Make informed career decisions based on evidence rather than assumptions.\n".
                "- Build confidence to pursue opportunities that align with your purpose.\n".
                "- Develop a strategy for continuous learning and career growth in a dynamic workplace.\n\n".
                "Overall Course Outcomes\n\nUpon successful completion of this course, you will have:\n".
                "- A clear understanding of your personality, strengths, values, and purpose.\n".
                "- Confidence in choosing a career that aligns with who you are.\n".
                "- A personalized Career Fit Profile to guide your career decisions.\n".
                "- A practical Career Mapping Blueprint with actionable next steps.\n".
                "- Greater clarity about your professional direction and long-term aspirations.\n".
                "- The knowledge and tools to make intentional, purpose-driven career decisions with confidence.",
            ],
            'is_mandatory' => true,
            'sort_order' => 0,
        ]);
        $wrapUp->lessons()->create([
            'title' => 'Course Promise',
            'type' => 'rich_text',
            'content' => ['body' =>
                "Your future shouldn't be determined by chance—it should be shaped by clarity.\n\n".
                "This course will empower you to stop guessing, start discovering, and confidently pursue a career that reflects your strengths, personality, and purpose. Because when you find the right fit, you don't just build a career—you build a life of impact, fulfillment, and lasting success.",
            ],
            'is_mandatory' => true,
            'sort_order' => 1,
        ]);

        $this->command?->line('Find Your Career Fit course seeded.');
    }
}
