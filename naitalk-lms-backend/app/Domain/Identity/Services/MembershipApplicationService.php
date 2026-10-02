<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\MembershipApplication;
use App\Domain\Identity\Notifications\MembershipApprovedNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of both `membership_applications.status` (the review
 * record) and `users.status` (the actual access gate, read by
 * EnsureMemberApproved) — keeping them in one transaction is what prevents
 * the two from drifting out of sync.
 */
class MembershipApplicationService
{
    public function approve(MembershipApplication $application, User $reviewer, ?string $note = null): MembershipApplication
    {
        if (! $application->isPaid()) {
            throw ValidationException::withMessages([
                'payment' => ['This applicant has not yet paid the registration fee.'],
            ]);
        }

        DB::transaction(function () use ($application, $reviewer, $note) {
            $application->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $application->user->update(['status' => 'active']);
        });

        $application->user->notify(new MembershipApprovedNotification);

        return $application->fresh();
    }

    public function reject(MembershipApplication $application, User $reviewer, ?string $note = null): MembershipApplication
    {
        DB::transaction(function () use ($application, $reviewer, $note) {
            $application->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $application->user->update(['status' => 'rejected']);
        });

        return $application->fresh();
    }
}
