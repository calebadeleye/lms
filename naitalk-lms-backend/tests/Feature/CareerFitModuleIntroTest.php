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
