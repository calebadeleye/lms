<?php

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseCategory;
use App\Domain\Learning\Models\CourseModule;

const CAREER_FIT_INTRO_VIDEO = 'https://drive.google.com/file/d/1B0XHj-NMrTZCRMoJoWg84Py5Iu4oIJt_/view';

function careerFitPersonalityModule(): CourseModule
{
    $category = CourseCategory::create(['name' => 'Career Development', 'slug' => 'career-development']);

    $course = Course::create([
        'category_id' => $category->id,
        'title' => 'Find Your Career Fit',
        'slug' => 'find-your-career-fit',
        'status' => 'published',
        'pricing_type' => 'paid',
        'price_cents' => 250_000,
        'currency' => 'NGN',
        'difficulty_level' => 'beginner',
    ]);

    $module = $course->modules()->create(['title' => 'Part Two: Discover Your Personality Type', 'sort_order' => 2]);

    foreach (['ENFP', 'INTJ'] as $index => $code) {
        $module->lessons()->create([
            'title' => "Personality Type: {$code}",
            'type' => 'video',
            'personality_type_code' => $code,
            'sort_order' => $index,
        ]);
    }

    return $module;
}

function runCareerFitIntroMigration(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_10_03_000001_add_career_fit_personality_module_intro_video.php');
    $migration->{$direction}();
}

it('adds the module 3 intro video first, ahead of the personality type videos', function () {
    $module = careerFitPersonalityModule();

    runCareerFitIntroMigration();

    $lessons = $module->lessons()->orderBy('sort_order')->get();

    expect($lessons->pluck('sort_order')->all())->toBe([0, 1, 2]);
    expect($lessons[0]->video_path)->toBe(CAREER_FIT_INTRO_VIDEO);
    expect($lessons[0]->type)->toBe('video');
    // No personality code — every enrolled learner sees it, before picking a type.
    expect($lessons[0]->personality_type_code)->toBeNull();
    expect($lessons[0]->is_mandatory)->toBeTrue();
    expect($lessons->pluck('personality_type_code')->slice(1)->values()->all())->toBe(['ENFP', 'INTJ']);
});

it('does nothing when the intro video is already there', function () {
    $module = careerFitPersonalityModule();

    runCareerFitIntroMigration();
    runCareerFitIntroMigration();

    expect($module->lessons()->count())->toBe(3);
    expect($module->lessons()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([0, 1, 2]);
});

it('does nothing when the Career Fit course does not exist', function () {
    runCareerFitIntroMigration();

    expect(DB::table('lessons')->count())->toBe(0);
});

it('removes the intro and restores the type video order on rollback', function () {
    $module = careerFitPersonalityModule();

    runCareerFitIntroMigration();
    runCareerFitIntroMigration('down');

    $lessons = $module->lessons()->orderBy('sort_order')->get();

    expect($lessons->pluck('sort_order')->all())->toBe([0, 1]);
    expect($lessons->pluck('personality_type_code')->all())->toBe(['ENFP', 'INTJ']);
});

function careerFitModuleFour(): CourseModule
{
    $course = Course::where('slug', 'find-your-career-fit')->first() ?? careerFitPersonalityModule()->course;

    $module = $course->modules()->create(['title' => 'Part Three: Career Mapping – Design Your Future', 'sort_order' => 3]);
    // "Why This Matters" was given the Module 3 video by mistake.
    $module->lessons()->create(['title' => 'Why This Matters', 'type' => 'video', 'video_path' => CAREER_FIT_INTRO_VIDEO, 'sort_order' => 0]);
    $module->lessons()->create(['title' => 'Learning Objectives & Outcomes', 'type' => 'video', 'video_path' => 'https://drive.google.com/file/d/14DKTo6u9O9XiBIiEl_ECwEjOy-YAyxa5/view', 'sort_order' => 1]);

    return $module;
}

function runModuleFourMigration(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_10_03_000002_keep_only_module_four_video_in_career_fit_module_four.php');
    $migration->{$direction}();
}

it('leaves only the Module 4 video in module 4 and keeps the other modules alone', function () {
    $personality = careerFitPersonalityModule();
    $module = careerFitModuleFour();

    runModuleFourMigration();

    $lessons = $module->lessons()->get();
    expect($lessons)->toHaveCount(1);
    expect($lessons[0]->video_path)->toContain('14DKTo6u9O9XiBIiEl_ECwEjOy-YAyxa5');
    expect($lessons[0]->sort_order)->toBe(0);
    // The removed lesson is soft-deleted, not destroyed (progress rows survive).
    expect($module->lessons()->onlyTrashed()->count())->toBe(1);
    expect($personality->lessons()->count())->toBe(2);
});

it('is a no-op once module 4 only has the Module 4 video', function () {
    $module = careerFitModuleFour();

    runModuleFourMigration();
    runModuleFourMigration();

    expect($module->lessons()->count())->toBe(1);
    expect($module->lessons()->onlyTrashed()->count())->toBe(1);
});

it('restores the removed lesson in its original place on rollback', function () {
    $module = careerFitModuleFour();

    runModuleFourMigration();
    runModuleFourMigration('down');

    $lessons = $module->lessons()->orderBy('sort_order')->get();
    expect($lessons->pluck('title')->all())->toBe(['Why This Matters', 'Learning Objectives & Outcomes']);
    expect($lessons->pluck('sort_order')->all())->toBe([0, 1]);
});

function runRenameMigration(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_10_03_000003_rename_career_fit_modules_and_lessons.php');
    $migration->{$direction}();
}

/** The live course as it was before the rename: "Part …" titles, and two modules with a generic "Learning Objectives & Outcomes". */
function careerFitCourseBeforeRename(): Course
{
    $personality = careerFitPersonalityModule();
    $course = $personality->course;

    $course->modules()->create(['title' => 'About This Course', 'sort_order' => 0]);
    $partOne = $course->modules()->create(['title' => 'Part One: Personality Assessment – Discover Who You Are', 'sort_order' => 1]);
    $partOne->lessons()->create(['title' => 'Why This Matters', 'type' => 'video', 'sort_order' => 0]);
    $partOne->lessons()->create(['title' => 'Learning Objectives & Outcomes', 'type' => 'video', 'sort_order' => 1]);

    $partThree = $course->modules()->create(['title' => 'Part Three: Career Mapping – Design Your Future', 'sort_order' => 3]);
    $partThree->lessons()->create(['title' => 'Learning Objectives & Outcomes', 'type' => 'video', 'sort_order' => 0]);

    return $course;
}

function careerFitTitles(Course $course): array
{
    return $course->modules()->orderBy('sort_order')->get()
        ->mapWithKeys(fn ($m) => [$m->title => $m->lessons()->orderBy('sort_order')->pluck('title')->all()])
        ->all();
}

it('drops the Part prefixes and names the duplicate lessons after their module', function () {
    $course = careerFitCourseBeforeRename();

    runRenameMigration();

    expect(careerFitTitles($course))->toBe([
        'About This Course' => [],
        'Personality Assessment – Discover Who You Are' => [
            'Personality Assessment: Why This Matters',
            'Personality Assessment: Learning Objectives & Outcomes',
        ],
        'Discover Your Personality Type' => ['Personality Type: ENFP', 'Personality Type: INTJ'],
        'Career Mapping – Design Your Future' => ['Career Mapping: Learning Objectives & Outcomes'],
    ]);
});

it('does nothing the second time the rename runs', function () {
    $course = careerFitCourseBeforeRename();

    runRenameMigration();
    $once = careerFitTitles($course);
    runRenameMigration();

    expect(careerFitTitles($course))->toBe($once);
});

it('restores the original module and lesson titles on rollback', function () {
    $course = careerFitCourseBeforeRename();
    $before = careerFitTitles($course);

    runRenameMigration();
    runRenameMigration('down');

    expect(careerFitTitles($course))->toBe($before);
});

it('leaves other courses alone', function () {
    $course = careerFitCourseBeforeRename();
    $other = Course::create([
        'category_id' => $course->category_id, 'title' => 'Other', 'slug' => 'other', 'status' => 'published',
        'pricing_type' => 'free', 'currency' => 'NGN', 'difficulty_level' => 'beginner',
    ]);
    $module = $other->modules()->create(['title' => 'Part One: Personality Assessment – Discover Who You Are', 'sort_order' => 0]);
    $module->lessons()->create(['title' => 'Why This Matters', 'type' => 'video', 'sort_order' => 0]);

    runRenameMigration();

    expect(careerFitTitles($other))->toBe(['Part One: Personality Assessment – Discover Who You Are' => ['Why This Matters']]);
});
