<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\Role;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * For the case InvitationController::store() takes the fast path — the
 * invited email already has an account, so it's added directly rather than
 * going through the token/accept flow. Purely informational, no action
 * needed: they can already log in.
 *
 * Deliberately NOT ShouldQueue — see TenantInvitationNotification's
 * docblock; this project has no queue worker running anywhere.
 */
class AddedToTenantNotification extends Notification
{
    public function __construct(private Tenant $tenant, private Role $role) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been added to {$this->tenant->name}")
            ->greeting('Hello!')
            ->line("You've been added to **{$this->tenant->name}** as a **{$this->role->name}**.")
            ->action('Sign in', $this->tenant->frontendUrl().'/login')
            ->line('You already have an account, so you can sign in with your existing password.');
    }
}
