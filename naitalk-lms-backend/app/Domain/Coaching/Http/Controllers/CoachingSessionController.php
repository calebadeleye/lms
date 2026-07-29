<?php

namespace App\Domain\Coaching\Http\Controllers;

use App\Domain\Coaching\Models\CoachingService;
use App\Domain\Coaching\Models\CoachingSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoachingSessionController extends Controller
{
    /** Public — upcoming scheduled occurrences of a service (group sessions
     * to book into, or a coach's already-committed one-to-one slots). */
    public function index(string $serviceId)
    {
        $sessions = CoachingSession::where('coaching_service_id', $serviceId)
            ->where('status', 'scheduled')
            ->where('scheduled_start', '>', now())
            ->withCount(['bookings' => fn ($q) => $q->where('status', 'confirmed')])
            ->orderBy('scheduled_start')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /** Admin — schedule a group session occurrence ahead of time. */
    public function store(Request $request, string $serviceId)
    {
        $service = CoachingService::where('session_type', 'group')->findOrFail($serviceId);

        $data = $request->validate([
            'scheduled_start' => ['required', 'date', 'after:now'],
            'meeting_url' => ['nullable', 'url'],
        ]);

        $start = \Illuminate\Support\Carbon::parse($data['scheduled_start']);

        $session = CoachingSession::create([
            'coaching_service_id' => $service->id,
            'coach_id' => $service->coach_id,
            'scheduled_start' => $start,
            'scheduled_end' => $start->clone()->addMinutes($service->duration_minutes),
            'meeting_url' => $data['meeting_url'] ?? null,
            'status' => 'scheduled',
        ]);

        return response()->json(['data' => $session], 201);
    }

    public function update(Request $request, string $sessionId)
    {
        $session = CoachingSession::findOrFail($sessionId);
        $data = $request->validate([
            'meeting_url' => ['nullable', 'url'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled'],
        ]);

        $session->update($data);

        return response()->json(['data' => $session->fresh()]);
    }
}
