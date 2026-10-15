<?php

use App\Domain\Learning\Models\Course;

function runCareerFitPriceMigration(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_10_15_000001_set_career_fit_course_price_to_2650.php');
    $migration->{$direction}();
}

function careerFitCourseAt(int $priceCents): Course
{
    return Course::create([
        'title' => 'Find Your Career Fit', 'slug' => 'find-your-career-fit', 'status' => 'published',
        'pricing_type' => 'paid', 'price_cents' => $priceCents, 'currency' => 'NGN', 'published_at' => now(),
    ]);
}

it('re-prices Career Fit to ₦2,650 (265,000 kobo) and changes nothing else', function () {
    $course = careerFitCourseAt(250_000);
    $before = $course->only(['title', 'status', 'pricing_type', 'currency']);

    runCareerFitPriceMigration();

    $course->refresh();
    expect($course->price_cents)->toBe(265_000);
    expect($course->only(['title', 'status', 'pricing_type', 'currency']))->toBe($before);
});

it('is safe to run twice, leaves other courses alone, and restores ₦2,500 on rollback', function () {
    $course = careerFitCourseAt(250_000);
    $other = Course::create(['title' => 'Other', 'slug' => 'other', 'status' => 'published', 'pricing_type' => 'paid', 'price_cents' => 500_000, 'currency' => 'NGN']);

    runCareerFitPriceMigration();
    runCareerFitPriceMigration();

    expect($course->fresh()->price_cents)->toBe(265_000);
    expect($other->fresh()->price_cents)->toBe(500_000);

    runCareerFitPriceMigration('down');

    expect($course->fresh()->price_cents)->toBe(250_000);
});

it('does nothing when the Career Fit course does not exist', function () {
    runCareerFitPriceMigration();

    expect(Course::count())->toBe(0);
});

it('seeds a fresh install at ₦2,650', function () {
    $this->seed(\Database\Seeders\CareerFitCourseSeeder::class);

    expect(Course::where('slug', 'find-your-career-fit')->value('price_cents'))->toBe(265_000);
});
