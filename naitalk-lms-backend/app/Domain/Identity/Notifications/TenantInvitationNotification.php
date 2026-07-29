<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent via Notification::route('mail', $email)->notify(...) rather than
 * $user->notify() — the invitee doesn't have a User row yet, that's the
 * entire point of this notification.
 *
 * Deliberately NOT ShouldQueue: matches every other transactional email in
 * this app (VerifyEmail, ResetPassword are Laravel's built-ins and send
 * synchronously by default) — this project has no queue worker running
 * anywhere, so a queued mail would just sit unsent in Redis forever.
 */
class TenantInvitationNotification extends Notification
{
    public function __construct(private Invitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->invitation->tenant->name;
        $roleName = $this->invitation->role->name;

        return (new MailMessage)
            ->subject("You've been invited to join {$tenantName}")
            ->greeting('Hello!')
            ->line("You've been invited to join **{$tenantName}** as a **{$roleName}**.")
            ->action('Accept invitation', $this->invitation->acceptUrl())
            ->line('This invitation expires on '.$this->invitation->expires_at->toFormattedDateString().'.')
            ->line("If you weren't expecting this, you can safely ignore this email.");
    }
}
