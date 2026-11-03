<?php

namespace App\Domain\Marketing\Notifications;

use App\Domain\Marketing\Models\AssessmentLead;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The free personality assessment link, plus the nudge to enrol in the paid
 * Find Your Career Fit course. Sent to an on-demand address (no user account).
 *
 * Deliberately NOT ShouldQueue, like every other transactional email in this
 * app — AssessmentLeadController sends it inline and shows the link on screen
 * if sending fails.
 */
class FreeAssessmentNotification extends Notification
{
    public function __construct(private AssessmentLead $lead) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteUrl = rtrim((string) config('services.frontend.url'), '/');

        // A fully branded template rather than Laravel's default markdown
        // layout (logo, teal hero with photo, two brand-coloured buttons).
        return (new MailMessage)
            ->subject('Your FREE Personality Assessment is ready!')
            ->view(['html' => 'mail.free-assessment-html', 'text' => 'mail.free-assessment-text'], [
                'firstName' => $this->lead->firstName(),
                'assessmentLink' => self::assessmentUrl(),
                'courseLink' => self::courseUrl(),
                'siteUrl' => $siteUrl,
                'logoUrl' => $siteUrl.'/branding/logo.png',
                'heroUrl' => $siteUrl.'/email/free-assessment-group.jpg',
            ]);
    }

    public static function assessmentUrl(): string
    {
        return (string) config('services.free_assessment.url');
    }

    public static function courseUrl(): string
    {
        return rtrim((string) config('services.frontend.url'), '/').'/courses/'.config('services.free_assessment.course_slug');
    }
}
