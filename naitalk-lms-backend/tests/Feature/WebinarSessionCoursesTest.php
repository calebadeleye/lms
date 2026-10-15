<?php

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\WebinarSessionCoursesSeeder;

const WEBINAR_SESSION_VIDEOS = [
    'Free Career Clarity (F.I.T) Webinar' => 'https://youtu.be/noNcoYHlI10',
    'Mindfulness & Inner Power' => 'https://youtu.be/U1RyIkc_3wo',
    'Identity Shift: Redefining Success' => 'https://youtu.be/owRj8VN1UAE',
];

it('publishes the three sessions as free single-video courses that play the YouTube recording', function () {
    $this->seed(WebinarSessionCoursesSeeder::class);

    expect(Course::count())->toBe(3);

    foreach (WEBINAR_SESSION_VIDEOS as $title => $video) {
        $course = Course::where('title', $title)->firstOrFail();

        expect($course->status)->toBe('published');
        expect($course->pricing_type)->toBe('free');
        expect($course->price_cents)->toBe(0);

        $lessons = $course->modules->flatMap->lessons;
        expect($lessons)->toHaveCount(1);
        expect($lessons[0]->type)->toBe('video');
        expect($lessons[0]->video_path)->toBe($video);
        expect($lessons[0]->hasExternalVideoEmbed())->toBeTrue();
    }
});

it('lists them in the public catalogue as free', function () {
    $this->seed(WebinarSessionCoursesSeeder::class);

    $titles = collect($this->getJson('/api/v1/courses')->assertOk()->json('data'))->pluck('title');

    expect($titles->all())->toContain(...array_keys(WEBINAR_SESSION_VIDEOS));
});

it('is safe to run twice and never overwrites an admin edit', function () {
    $this->seed(WebinarSessionCoursesSeeder::class);
    Course::where('slug', 'mindfulness-and-inner-power')->update(['title' => 'Edited in the admin']);

    $this->seed(WebinarSessionCoursesSeeder::class);

    expect(Course::count())->toBe(3);
    expect(Course::where('slug', 'mindfulness-and-inner-power')->value('title'))->toBe('Edited in the admin');
});

it('lets a learner enrol for free and complete the session', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(WebinarSessionCoursesSeeder::class);
    $course = Course::where('slug', 'identity-shift-redefining-success')->firstOrFail();
    $lesson = $course->modules->flatMap->lessons->first();
    $student = makeUserWithRole('student');

    $this->actingAs($student)->postJson("/api/v1/courses/{$course->id}/enrol")->assertCreated();
    $this->actingAs($student)->postJson("/api/v1/lessons/{$lesson->id}/progress/complete")->assertOk();

    expect(Enrolment::where('user_id', $student->id)->where('course_id', $course->id)->value('status'))->toBe('completed');
});
