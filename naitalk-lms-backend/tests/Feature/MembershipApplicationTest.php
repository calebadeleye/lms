<?php

use App\Domain\Identity\Models\MembershipApplication;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('uploads');
});

function registerApplicant(array $overrides = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/auth/register', array_merge([
        'name' => 'Jane Learner',
        'email' => 'jane@example.com',
        'password' => 'Passw0rd123',
        'password_confirmation' => 'Passw0rd123',
        'ack_impact_beyond_earning' => '1',
        'ack_growth_mindset' => '1',
        'ack_interest_in_coaching' => '1',
        'ack_positive_impact' => '1',
    ], $overrides));
}

it('accepts an optional welcome photo and motivation on registration', function () {
    registerApplicant([
        'motivation' => 'I want to grow as a coach.',
        'photo' => UploadedFile::fake()->image('welcome.jpg'),
    ])->assertCreated();

    $application = MembershipApplication::firstOrFail();
    expect($application->motivation)->toBe('I want to grow as a coach.');
    expect($application->photo_path)->not->toBeNull();
    Storage::disk('uploads')->assertExists($application->photo_path);
});

it('registers without a photo or motivation just fine', function () {
    registerApplicant()->assertCreated();

    $application = MembershipApplication::firstOrFail();
    expect($application->photo_path)->toBeNull();
    expect($application->motivation)->toBeNull();
});

it('reports the caller\'s own application status', function () {
    registerApplicant()->assertCreated();
    $user = User::where('email', 'jane@example.com')->firstOrFail();
    $token = $user->createToken('t')->plainTextToken;

    $this->getJson('/api/v1/auth/application', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

it('rejects a non-approver from the review queue', function () {
    $student = makeUserWithRole('student');
    $token = $student->createToken('t')->plainTextToken;

    $this->getJson('/api/v1/admin/applications', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});

it('lets an owner list, view, and approve a pending application', function () {
    registerApplicant(['motivation' => 'Excited to join.'])->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();

    $owner = makeUserWithRole('owner');
    $ownerToken = $owner->createToken('t')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$ownerToken}"];

    $list = $this->getJson('/api/v1/admin/applications', $headers)->assertOk();
    expect(collect($list->json('data'))->pluck('user.email'))->toContain('jane@example.com');

    $applicationId = MembershipApplication::where('user_id', $applicant->id)->firstOrFail()->id;

    $this->getJson("/api/v1/admin/applications/{$applicationId}", $headers)
        ->assertOk()
        ->assertJsonPath('data.motivation', 'Excited to join.')
        ->assertJsonPath('data.ack_growth_mindset', true);

    $this->postJson("/api/v1/admin/applications/{$applicationId}/approve", ['note' => 'Welcome!'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($applicant->fresh()->status)->toBe('active');
    expect(MembershipApplication::find($applicationId)->reviewed_by)->toBe($owner->id);

    // Sanctum's RequestGuard caches the resolved user across HTTP calls
    // within one test — without this the check below would silently pass
    // as a replay of the owner's guard resolution rather than actually
    // exercising the applicant's own token.
    $this->app->make('auth')->forgetGuards();

    // Approval is the real access gate — the applicant can now reach a
    // member-only route (email verification is a separate, earlier gate,
    // so it's satisfied directly here rather than tested twice).
    $applicant->forceFill(['email_verified_at' => now()])->save();
    $applicantToken = $applicant->createToken('t')->plainTextToken;
    $this->getJson('/api/v1/my/enrolments', ['Authorization' => "Bearer {$applicantToken}"])->assertOk();
});

it('requires a note to reject an application, and rejecting revokes access', function () {
    registerApplicant()->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();
    $applicationId = MembershipApplication::where('user_id', $applicant->id)->firstOrFail()->id;

    $owner = makeUserWithRole('owner');
    $headers = ['Authorization' => 'Bearer '.$owner->createToken('t')->plainTextToken];

    $this->postJson("/api/v1/admin/applications/{$applicationId}/reject", [], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');

    $this->postJson("/api/v1/admin/applications/{$applicationId}/reject", ['note' => 'Not a fit right now.'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($applicant->fresh()->status)->toBe('rejected');

    // Sanctum's RequestGuard caches the resolved user across HTTP calls
    // within one test — without this, the request below would keep
    // resolving the owner from the reject() call above regardless of the
    // applicant's own bearer token.
    $this->app->make('auth')->forgetGuards();

    // Verify email first so the earlier `verified` gate doesn't mask the
    // `membership_rejected` check this test is actually about.
    $applicant->forceFill(['email_verified_at' => now()])->save();
    $applicantToken = $applicant->createToken('t')->plainTextToken;
    $response = $this->getJson('/api/v1/my/enrolments', ['Authorization' => "Bearer {$applicantToken}"])->assertStatus(403);
    expect($response->json('errors.0.code'))->toBe('membership_rejected');
});

it('serves the welcome photo only to the owning applicant or an approver', function () {
    registerApplicant(['photo' => UploadedFile::fake()->image('welcome.jpg')])->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();

    $other = makeUserWithRole('student');
    $otherToken = $other->createToken('t')->plainTextToken;
    $this->getJson("/api/v1/members/{$applicant->id}/photo", ['Authorization' => "Bearer {$otherToken}"])
        ->assertStatus(403);

    // Sanctum's RequestGuard caches the resolved user across HTTP calls
    // within one test — each actor switch below needs a fresh guard.
    $this->app->make('auth')->forgetGuards();

    $applicantToken = $applicant->createToken('t')->plainTextToken;
    $this->getJson("/api/v1/members/{$applicant->id}/photo", ['Authorization' => "Bearer {$applicantToken}"])
        ->assertOk();

    $this->app->make('auth')->forgetGuards();

    $owner = makeUserWithRole('owner');
    $ownerToken = $owner->createToken('t')->plainTextToken;
    $this->getJson("/api/v1/members/{$applicant->id}/photo", ['Authorization' => "Bearer {$ownerToken}"])
        ->assertOk();
});
