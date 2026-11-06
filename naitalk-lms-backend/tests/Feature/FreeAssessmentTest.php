<?php

use App\Domain\Marketing\Models\AssessmentLead;
use App\Domain\Marketing\Notifications\FreeAssessmentNotification;
use App\Domain\Marketing\Notifications\NewAssessmentLeadNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    RateLimiter::clear('free-assessment:'.sha1('ada@example.com'));
});

it('saves the lead and emails the assessment and course links', function () {
    Notification::fake();

    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada Obi', 'email' => 'Ada@Example.com', 'source' => 'landing'])
        ->assertCreated()
        ->assertJsonPath('data.email_sent', true)
        ->assertJsonMissingPath('data.assessment_url');

    $lead = AssessmentLead::firstOrFail();
    expect($lead->email)->toBe('ada@example.com');
    expect($lead->source)->toBe('landing');
    expect($lead->email_sent_at)->not->toBeNull();

    Notification::assertSentOnDemand(FreeAssessmentNotification::class, function ($notification, $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === ['ada@example.com' => 'Ada Obi']
            && $mail->viewData['firstName'] === 'Ada'
            && $mail->viewData['assessmentLink'] === 'https://bit.ly/MySelfTest'
            && str_ends_with($mail->viewData['courseLink'], '/courses/find-your-career-fit');
    });
});

it('renders the email with both buttons', function () {
    $lead = new AssessmentLead(['full_name' => 'Ada Obi', 'email' => 'ada@example.com']);
    $html = (string) (new FreeAssessmentNotification($lead))->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain('Hello Ada,')
        ->toContain('TAKE YOUR FREE ASSESSMENT NOW')
        ->toContain('https://bit.ly/MySelfTest')
        ->toContain('ENROL IN OUR COURSE TODAY')
        ->toContain('/courses/find-your-career-fit')
        // The branded template: logo, hero photo and sign-off.
        ->toContain('/branding/logo.png')
        ->toContain('/email/free-assessment-youth.jpg')
        ->toContain('Come with us and take the next step.')
        ->toContain('DISCOVER HOW TO GET A FULFILLING JOB AND MAKE MONEY WITH YOUR STRENGTHS.')
        ->toContain('Warm regards,');
});

it('updates the same lead when someone asks again', function () {
    Notification::fake();

    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada Obi', 'email' => 'ada@example.com'])->assertCreated();
    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada N. Obi', 'email' => 'ADA@example.com', 'source' => 'home'])->assertCreated();

    expect(AssessmentLead::count())->toBe(1);
    $lead = AssessmentLead::first();
    expect($lead->request_count)->toBe(2);
    expect($lead->full_name)->toBe('Ada N. Obi');
    Notification::assertSentOnDemandTimes(FreeAssessmentNotification::class, 2);
});

it('stops sending to the same address after three requests in an hour', function () {
    Notification::fake();

    foreach (range(1, 3) as $i) {
        $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada Obi', 'email' => 'ada@example.com'])->assertCreated();
    }

    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada Obi', 'email' => 'ada@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'already sent'));
    Notification::assertSentOnDemandTimes(FreeAssessmentNotification::class, 3);
});

it('quietly ignores bots that fill the hidden field', function () {
    Notification::fake();

    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Bot', 'email' => 'bot@example.com', 'website' => 'spam.example'])
        ->assertCreated();

    expect(AssessmentLead::count())->toBe(0);
    Notification::assertNothingSent();
});

it('validates the name and email', function () {
    $this->postJson('/api/v1/assessment-leads', ['full_name' => '', 'email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name', 'email']);
});

it('keeps the lead and returns the link when the email cannot be sent', function () {
    Notification::shouldReceive('route')->andThrow(new RuntimeException('SMTP down'));

    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada Obi', 'email' => 'ada@example.com'])
        ->assertCreated()
        ->assertJsonPath('data.email_sent', false)
        ->assertJsonPath('data.assessment_url', 'https://bit.ly/MySelfTest');

    expect(AssessmentLead::first()->email_sent_at)->toBeNull();
});

it('lets staff with students.manage list and export leads, but not students', function () {
    AssessmentLead::create(['full_name' => 'Ada Obi', 'email' => 'ada@example.com', 'source' => 'home', 'last_requested_at' => now(), 'email_sent_at' => now()]);

    $student = makeUserWithRole('student');
    $this->actingAs($student)->getJson('/api/v1/admin/assessment-leads')->assertForbidden();

    $admin = makeUserWithRole('administrator');
    $this->actingAs($admin)->getJson('/api/v1/admin/assessment-leads')
        ->assertOk()
        ->assertJsonPath('data.0.email', 'ada@example.com')
        ->assertJsonPath('data.0.email_sent', true)
        ->assertJsonPath('meta.pagination.total', 1);

    $csv = $this->actingAs($admin)->get('/api/v1/admin/assessment-leads/export')->assertOk()->streamedContent();
    expect($csv)->toContain('"Full name",Email,Source')->toContain('"Ada Obi",ada@example.com,home');
});

function xlsxSheetXml(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
    file_put_contents($path, $bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    return $xml;
}

it('emails the team about a new sign-up with an updated Excel file of everyone', function () {
    Notification::fake();
    AssessmentLead::create(['full_name' => 'Earlier Person', 'email' => 'earlier@example.com', 'source' => 'home']);

    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada Obi', 'email' => 'ada@example.com', 'source' => 'landing'])->assertCreated();

    Notification::assertSentOnDemand(NewAssessmentLeadNotification::class, function ($notification, $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);
        $attachment = $mail->rawAttachments[0] ?? null;
        $sheet = $attachment ? xlsxSheetXml($attachment['data']) : '';

        return $notifiable->routes['mail'] === 'info@hrgemscoachnetwork.com'
            && $mail->subject === 'New free assessment sign-up: Ada Obi'
            && str_ends_with($attachment['name'], '.xlsx')
            && str_contains($sheet, 'Ada Obi') && str_contains($sheet, 'ada@example.com')
            && str_contains($sheet, 'Earlier Person') && str_contains($sheet, 'Free assessment page');
    });
});

it('does not email the team again when the same person asks a second time', function () {
    Notification::fake();

    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada Obi', 'email' => 'ada@example.com'])->assertCreated();
    $this->postJson('/api/v1/assessment-leads', ['full_name' => 'Ada Obi', 'email' => 'ada@example.com'])->assertCreated();

    Notification::assertSentOnDemandTimes(NewAssessmentLeadNotification::class, 1);
    Notification::assertSentOnDemandTimes(FreeAssessmentNotification::class, 2);
});

it('lets staff download every sign-up as an Excel file, but not students', function () {
    AssessmentLead::create(['full_name' => 'Ada Obi', 'email' => 'ada@example.com', 'source' => 'home', 'last_requested_at' => now()]);

    $this->actingAs(makeUserWithRole('student'))->get('/api/v1/admin/assessment-leads/export-excel')->assertForbidden();

    $response = $this->actingAs(makeUserWithRole('administrator'))->get('/api/v1/admin/assessment-leads/export-excel')->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect(xlsxSheetXml($response->getContent()))->toContain('Ada Obi')->toContain('Homepage');
});
