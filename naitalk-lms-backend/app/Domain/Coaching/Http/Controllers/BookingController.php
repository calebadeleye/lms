<?php

namespace App\Domain\Coaching\Http\Controllers;

use App\Domain\Coaching\Exceptions\BookingConflictException;
use App\Domain\Coaching\Models\Booking;
use App\Domain\Coaching\Models\CoachingService;
use App\Domain\Coaching\Models\CoachingSession;
use App\Domain\Coaching\Services\BookingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function bookOneToOne(Request $request, string $serviceId)
    {
        $service = CoachingService::where('is_active', true)->findOrFail($serviceId);
        $data = $request->validate(['scheduled_start' => ['required', 'date', 'after:now']]);

        try {
            $booking = $this->bookings->bookOneToOne($request->user(), $service, Carbon::parse($data['scheduled_start']));
        } catch (BookingConflictException $e) {
            throw ValidationException::withMessages(['scheduled_start' => [$e->getMessage()]]);
        }

        return response()->json(['data' => $booking->load('session.service')], 201);
    }

    public function bookIntoSession(Request $request, string $sessionId)
    {
        $session = CoachingSession::where('status', 'scheduled')->with('service')->findOrFail($sessionId);

        try {
            $booking = $this->bookings->bookIntoSession($request->user(), $session);
        } catch (BookingConflictException $e) {
            throw ValidationException::withMessages(['session' => [$e->getMessage()]]);
        }

        return response()->json(['data' => $booking->load('session.service')], 201);
    }

    public function myBookings(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with('session.service', 'session.coach.user:id,name')
            ->orderByDesc('booked_at')
            ->get();

        return response()->json(['data' => $bookings]);
    }

    public function cancel(Request $request, string $bookingId)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($bookingId);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->bookings->cancel($booking, $data['reason'] ?? null);

        return response()->json(['data' => $booking->fresh()]);
    }

    public function reschedule(Request $request, string $bookingId)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($bookingId);
        $data = $request->validate(['scheduled_start' => ['required', 'date', 'after:now']]);

        try {
            $booking = $this->bookings->reschedule($booking, Carbon::parse($data['scheduled_start']));
        } catch (BookingConflictException $e) {
            throw ValidationException::withMessages(['scheduled_start' => [$e->getMessage()]]);
        }

        return response()->json(['data' => $booking->load('session')]);
    }

    /** Coach/admin roster for a session, plus a place to mark attendance. */
    public function sessionRoster(string $sessionId)
    {
        $session = CoachingSession::with('bookings.user:id,name,email', 'attendance')->findOrFail($sessionId);

        return response()->json(['data' => [
            'session' => $session->only(['id', 'scheduled_start', 'scheduled_end', 'status', 'meeting_url', 'notes']),
            'bookings' => $session->bookings,
        ]]);
    }

    public function markAttendance(Request $request, string $sessionId)
    {
        $session = CoachingSession::findOrFail($sessionId);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'attended' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $attendance = $session->attendance()->updateOrCreate(
            ['user_id' => $data['user_id']],
            ['attended' => $data['attended'], 'joined_at' => $data['attended'] ? now() : null, 'notes' => $data['notes'] ?? null]
        );

        return response()->json(['data' => $attendance]);
    }
}
