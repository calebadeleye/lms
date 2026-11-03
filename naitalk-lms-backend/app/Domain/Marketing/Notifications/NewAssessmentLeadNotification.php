<?php

namespace App\Domain\Marketing\Notifications;

use App\Domain\Marketing\Models\AssessmentLead;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the HR GEMs team (services.free_assessment.notify_email) about a new
 * free-assessment sign-up, with an up-to-date Excel file of every sign-up
 * attached. Sent once per new person, not on repeat requests.
 *
 * Deliberately NOT ShouldQueue, like every other email in this app.
 */
class NewAssessmentLeadNotification extends Notification
{
    public function __construct(private AssessmentLead $lead) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;
        $total = AssessmentLead::count();
        $signedUpAt = $lead->created_at?->copy()->setTimezone('Africa/Lagos')->format('j M Y, g:i a').' (WAT)';
        $source = AssessmentLead::SOURCE_LABELS[$lead->source] ?? ($lead->source ?: '—');
        $adminUrl = rtrim((string) config('services.frontend.url'), '/').'/admin/assessment-leads';

        return (new MailMessage)
            ->subject("New free assessment sign-up: {$lead->full_name}")
            ->greeting('New free assessment sign-up')
            ->line("**Name:** {$lead->full_name}")
            ->line("**Email:** {$lead->email}")
            ->line("**Signed up from:** {$source}")
            ->line("**When:** {$signedUpAt}")
            ->line("That makes **{$total}** ".($total === 1 ? 'sign-up' : 'sign-ups').' in total. The attached spreadsheet lists everyone so far.')
            ->action('View all sign-ups', $adminUrl)
            ->attachData(
                AssessmentLead::spreadsheet(),
                'free-assessment-signups-'.now('Africa/Lagos')->format('Y-m-d').'.xlsx',
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            );
    }
}
