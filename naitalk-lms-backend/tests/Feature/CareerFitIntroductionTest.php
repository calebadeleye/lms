<?php

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseCategory;
use App\Domain\Learning\Models\Lesson;

/** The Career Fit course exactly as it is live before the restructure. */
function careerFitBeforeRestructure(): Course
{
    $category = CourseCategory::create(['name' => 'Career Development', 'slug' => 'career-development']);

    $course = Course::create([
        'category_id' => $category->id, 'title' => 'Find Your Career Fit', 'slug' => 'find-your-career-fit',
        'status' => 'published', 'pricing_type' => 'paid', 'price_cents' => 250_000, 'currency' => 'NGN',
        'difficulty_level' => 'beginner', 'published_at' => now(),
    ]);

    $about = $course->modules()->create(['title' => 'About This Course', 'sort_order' => 0]);
    $about->lessons()->create(['title' => 'Welcome to the Career FIT™ Framework', 'type' => 'video', 'video_path' => '/videos/welcome.mp4', 'is_preview' => true, 'sort_order' => 0]);

    $assessment = $course->modules()->create(['title' => 'Personality Assessment – Discover Who You Are', 'sort_order' => 1]);
    $assessment->lessons()->create(['title' => 'Personality Assessment: Why This Matters', 'type' => 'video', 'video_path' => '/videos/why.mp4', 'is_preview' => true, 'sort_order' => 0]);
    $assessment->lessons()->create(['title' => 'Personality Assessment: Learning Objectives & Outcomes', 'type' => 'video', 'video_path' => '/videos/objectives.mp4', 'sort_order' => 1]);

    $types = $course->modules()->create(['title' => 'Discover Your Personality Type', 'sort_order' => 2]);
    $types->lessons()->create(['title' => 'Introduction to Discover Your Personality Type', 'type' => 'video', 'sort_order' => 0]);
    $types->lessons()->create(['title' => 'Personality Type: INTJ', 'type' => 'video', 'personality_type_code' => 'INTJ', 'sort_order' => 1]);

    $career = $course->modules()->create(['title' => 'Career Mapping – Design Your Future', 'sort_order' => 3]);
    $career->lessons()->create(['title' => 'Career Mapping: Learning Objectives & Outcomes', 'type' => 'video', 'sort_order' => 0]);

    return $course;
}

function runCareerFitRestructure(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_10_05_000002_restructure_career_fit_into_introduction_and_four_modules.php');
    $migration->{$direction}();
}

/** @return array<int, array{string, bool, array<int, string>}> title, is_introduction, lesson titles — in display order */
function careerFitOutline(Course $course): array
{
    return $course->modules()->orderBy('sort_order')->get()
        ->map(fn ($m) => [$m->title, $m->is_introduction, $m->lessons->pluck('title')->all()])
        ->all();
}

it('moves Welcome into an unnumbered Introduction and splits the assessment into Modules 1 and 2', function () {
    $course = careerFitBeforeRestructure();
    $objectivesId = Lesson::where('title', 'Personality Assessment: Learning Objectives & Outcomes')->value('id');

    runCareerFitRestructure();

    expect(careerFitOutline($course))->toBe([
        ['Introduction', true, ['Welcome to the Career FIT™ Framework']],
        ['Why This Matters', false, ['Why This Matters']],
        ['Understanding Your Personality', false, ['Understanding Your Personality: Learning Objectives & Outcomes']],
        ['Discover Your Personality Type', false, ['Introduction to Discover Your Personality Type', 'Personality Type: INTJ']],
        ['Career Mapping – Design Your Future', false, ['Career Mapping: Learning Objectives & Outcomes']],
    ]);

    // The lesson was moved, not recreated: same row, same video — so learner progress follows it.
    $moved = Lesson::findOrFail($objectivesId);
    expect($moved->courseModule->title)->toBe('Understanding Your Personality');
    expect($moved->video_path)->toBe('/videos/objectives.mp4');
    expect($course->modules()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([0, 1, 2, 3, 4]);
});

it('is safe to run twice', function () {
    $course = careerFitBeforeRestructure();

    runCareerFitRestructure();
    $once = careerFitOutline($course);
    runCareerFitRestructure();

    expect(careerFitOutline($course))->toBe($once);
    expect($course->modules()->count())->toBe(5);
});

it('does nothing when the Career Fit course does not exist', function () {
    runCareerFitRestructure();

    expect(DB::table('course_modules')->count())->toBe(0);
});

it('puts everything back on rollback', function () {
    $course = careerFitBeforeRestructure();
    $before = careerFitOutline($course);

    runCareerFitRestructure();
    runCareerFitRestructure('down');

    expect(careerFitOutline($course))->toBe($before);
});

it('tells the frontend which module is the introduction so it can skip it when numbering', function () {
    careerFitBeforeRestructure();
    runCareerFitRestructure();

    $modules = $this->getJson('/api/v1/courses/find-your-career-fit')->assertOk()->json('data.modules');

    expect(collect($modules)->map(fn ($m) => [$m['title'], $m['is_introduction']])->all())->toBe([
        ['Introduction', true],
        ['Why This Matters', false],
        ['Understanding Your Personality', false],
        ['Discover Your Personality Type', false],
        ['Career Mapping – Design Your Future', false],
    ]);
});

it('seeds a fresh install with the same Introduction plus four modules', function () {
    $this->seed(\Database\Seeders\CareerFitCourseSeeder::class);

    $course = Course::where('slug', 'find-your-career-fit')->firstOrFail();

    expect(collect(careerFitOutline($course))->map(fn ($m) => [$m[0], $m[1]])->all())->toBe([
        ['Introduction', true],
        ['Why This Matters', false],
        ['Understanding Your Personality', false],
        ['Discover Your Personality Type', false],
        ['Career Mapping: Build Your Career Road Map', false],
    ]);
});

function runCareerFitLessonRenames(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_10_05_000003_rename_career_fit_module_three_and_four_lessons.php');
    $migration->{$direction}();
}

it('retitles the module 3 and module 4 lessons without touching anything else about them', function () {
    $course = careerFitBeforeRestructure();
    $before = Lesson::whereIn('title', ['Introduction to Discover Your Personality Type', 'Career Mapping: Learning Objectives & Outcomes'])
        ->get()->keyBy('title')->map(fn ($l) => $l->only(['id', 'course_module_id', 'video_path', 'sort_order']));

    runCareerFitLessonRenames();

    $types = $course->modules()->where('title', 'Discover Your Personality Type')->firstOrFail()->lessons;
    $career = $course->modules()->where('title', 'Career Mapping – Design Your Future')->firstOrFail()->lessons;

    expect($types->pluck('title')->all())->toBe(['Matching Personality to Careers', 'Personality Type: INTJ']);
    expect($career->pluck('title')->all())->toBe(['Career Mapping: Build Your Career Road Map']);

    // Same rows: only the title changed.
    expect($types[0]->only(['id', 'course_module_id', 'video_path', 'sort_order']))
        ->toBe($before['Introduction to Discover Your Personality Type']);
    expect($career[0]->only(['id', 'course_module_id', 'video_path', 'sort_order']))
        ->toBe($before['Career Mapping: Learning Objectives & Outcomes']);
});

it('is safe to run twice, ignores other courses, and restores the old titles on rollback', function () {
    $course = careerFitBeforeRestructure();
    $other = Course::create(['title' => 'Other', 'slug' => 'other', 'status' => 'published', 'pricing_type' => 'free']);
    $other->modules()->create(['title' => 'Career Mapping – Design Your Future', 'sort_order' => 0])
        ->lessons()->create(['title' => 'Career Mapping: Learning Objectives & Outcomes', 'type' => 'video', 'sort_order' => 0]);
    $original = careerFitOutline($course);

    runCareerFitLessonRenames();
    runCareerFitLessonRenames();

    expect(Lesson::where('title', 'Career Mapping: Learning Objectives & Outcomes')->count())->toBe(1); // the other course's, untouched

    runCareerFitLessonRenames('down');

    expect(careerFitOutline($course))->toBe($original);
});

it('does nothing to lesson titles when the Career Fit course does not exist', function () {
    runCareerFitLessonRenames();

    expect(DB::table('lessons')->count())->toBe(0);
});

it('seeds the new lesson titles on a fresh install', function () {
    $this->seed(\Database\Seeders\CareerFitCourseSeeder::class);

    $titles = Lesson::pluck('title');

    expect($titles)->toContain('Matching Personality to Careers')->toContain('Career Mapping: Build Your Career Road Map');
    expect($titles)->not->toContain('Introduction to Discover Your Personality Type')
        ->not->toContain('Career Mapping: Learning Objectives & Outcomes');
});

function runCareerFitModuleRename(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_10_05_000004_rename_career_fit_module_four.php');
    $migration->{$direction}();
}

it('retitles module 4 and leaves its lessons, order and the other modules alone', function () {
    $course = careerFitBeforeRestructure();
    runCareerFitRestructure();
    $before = careerFitOutline($course);

    runCareerFitModuleRename();

    $after = careerFitOutline($course);
    expect(collect($after)->pluck(0)->all())->toBe([
        'Introduction', 'Why This Matters', 'Understanding Your Personality',
        'Discover Your Personality Type', 'Career Mapping: Build Your Career Road Map',
    ]);
    // Everything but the one title is identical.
    $after[4][0] = $before[4][0];
    expect($after)->toBe($before);
});

it('is safe to run twice, ignores other courses, and restores the old title on rollback', function () {
    $course = careerFitBeforeRestructure();
    $other = Course::create(['title' => 'Other', 'slug' => 'other', 'status' => 'published', 'pricing_type' => 'free']);
    $other->modules()->create(['title' => 'Career Mapping – Design Your Future', 'sort_order' => 0]);
    $original = careerFitOutline($course);

    runCareerFitModuleRename();
    runCareerFitModuleRename();

    expect($course->modules()->where('title', 'Career Mapping: Build Your Career Road Map')->count())->toBe(1);
    expect($other->modules()->first()->title)->toBe('Career Mapping – Design Your Future'); // untouched

    runCareerFitModuleRename('down');

    expect(careerFitOutline($course))->toBe($original);
});

it('does nothing to the module title when the Career Fit course does not exist', function () {
    runCareerFitModuleRename();

    expect(DB::table('course_modules')->count())->toBe(0);
});
