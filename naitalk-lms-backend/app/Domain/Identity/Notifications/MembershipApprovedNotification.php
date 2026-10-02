<?php

namespace App\Domain\Identity\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent the moment an admin approves a pending membership application — the
 * "grand welcome" the client's manual process used to send after reviewing
 * a Google Form submission.
 *
 * Deliberately NOT ShouldQueue: matches every other transactional email in
 * this app — no queue worker runs in this project.
 */
class MembershipApprovedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');
        $frontendUrl = rtrim((string) config('services.frontend.url'), '/');

        return (new MailMessage)
            ->subject("Welcome to {$appName}!")
            ->greeting('You\'re in! 🎉')
            ->line("Your application to join **{$appName}** has been approved.")
            ->action('Go to your dashboard', "{$frontendUrl}/dashboard")
            ->line('We\'re glad to have you as part of the community.');
    }
}
