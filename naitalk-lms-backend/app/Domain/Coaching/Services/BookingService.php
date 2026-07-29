<?php

namespace App\Domain\Coaching\Services;

use App\Domain\Coaching\Exceptions\BookingConflictException;
use App\Domain\Coaching\Models\Booking;
use App\Domain\Coaching\Models\CoachingService;
use App\Domain\Coaching\Models\CoachingSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Real conflict detection, not just a UI hint: a new one-to-one session is
 * rejected if it overlaps an existing scheduled session for the same coach,
 * or falls outside that coach's declared availability_rules for the
 * requested day/time.
 */
class BookingService
{
    /** For session_type = one_to_one — creates a dedicated CoachingSession
     * for this single booking after checking availability + conflicts. */
    public function bookOneToOne(User $user, CoachingService $service, Carbon $scheduledStart): Booking
    {
        if ($service->isGroup()) {
            throw new \InvalidArgumentException('Use bookIntoSession() for group services.');
        }

        $scheduledEnd = $scheduledStart->clone()->addMinutes($service->duration_minutes);
        $coach = $service->coach;

        $this->assertWithinAvailability($coach, $scheduledStart, $scheduledEnd);
        $this->assertNoConflict($coach->id, $scheduledStart, $scheduledEnd);

        return DB::transaction(function () use ($user, $service, $coach, $scheduledStart, $scheduledEnd) {
            $session = CoachingSession::create([
                'coaching_service_id' => $service->id,
                'coach_id' => $coach->id,
                'scheduled_start' => $scheduledStart,
                'scheduled_end' => $scheduledEnd,
                'status' => 'scheduled',
            ]);

            return Booking::create([
                'coaching_session_id' => $session->id,
                'user_id' => $user->id,
                'status' => $service->is_free ? 'confirmed' : 'pending',
                'booked_at' => now(),
            ]);
        });
    }

    /** For session_type = group — the CoachingSession already exists
     * (created ahead of time by the coach/admin); this just reserves a seat. */
    public function bookIntoSession(User $user, CoachingSession $session): Booking
    {
        if (Booking::where('coaching_session_id', $session->id)->where('user_id', $user->id)->exists()) {
            throw new BookingConflictException('You already have a booking for this session.');
        }

        if ($session->activeBookingsCount() >= $session->service->max_participants) {
            throw new BookingConflictException('This session is fully booked.');
        }

        return Booking::create([
            'coaching_session_id' => $session->id,
            'user_id' => $user->id,
            'status' => $session->service->is_free ? 'confirmed' : 'pending',
            'booked_at' => now(),
        ]);
    }

    public function cancel(Booking $booking, ?string $reason = null): void
    {
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);

        // A dedicated one-to-one session has no purpose once its only
        // booking is cancelled — free the coach's calendar back up.
        $session = $booking->session;
        if (! $session->service->isGroup() && $session->activeBookingsCount() === 0) {
            $session->update(['status' => 'cancelled']);
        }
    }

    public function reschedule(Booking $booking, Carbon $newStart): Booking
    {
        $session = $booking->session;

        if ($session->service->isGroup()) {
            throw new \InvalidArgumentException('Group sessions are rescheduled by the coach, not per-booking.');
        }

        $newEnd = $newStart->clone()->addMinutes($session->service->duration_minutes);
        $coach = $session->coach;

        $this->assertWithinAvailability($coach, $newStart, $newEnd);
        $this->assertNoConflict($coach->id, $newStart, $newEnd, excludeSessionId: $session->id);

        $session->update(['scheduled_start' => $newStart, 'scheduled_end' => $newEnd]);

        return $booking->fresh();
    }

    private function assertWithinAvailability($coach, Carbon $start, Carbon $end): void
    {
        $localStart = $start->clone()->setTimezone($coach->timezone);
        $localEnd = $end->clone()->setTimezone($coach->timezone);

        if ($localStart->toDateString() !== $localEnd->toDateString()) {
            throw new BookingConflictException('Sessions cannot span across midnight in the coach\'s timezone.');
        }

        $fits = $coach->availabilityRules()
            ->where('day_of_week', $localStart->dayOfWeek)
            ->where('start_time', '<=', $localStart->format('H:i:s'))
            ->where('end_time', '>=', $localEnd->format('H:i:s'))
            ->exists();

        if (! $fits) {
            throw new BookingConflictException('This time is outside the coach\'s available hours.');
        }
    }

    private function assertNoConflict(int $coachId, Carbon $start, Carbon $end, ?int $excludeSessionId = null): void
    {
        $overlap = CoachingSession::where('coach_id', $coachId)
            ->where('status', 'scheduled')
            ->when($excludeSessionId, fn ($q) => $q->where('id', '!=', $excludeSessionId))
            ->where('scheduled_start', '<', $end)
            ->where('scheduled_end', '>', $start)
            ->exists();

        if ($overlap) {
            throw new BookingConflictException('This coach already has a session booked at that time.');
        }
    }
}
