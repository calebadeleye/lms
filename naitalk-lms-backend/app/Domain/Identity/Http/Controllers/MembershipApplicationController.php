<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Models\MembershipApplication;
use App\Domain\Identity\Services\MembershipApplicationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Admin review queue (members.approve) for pending self-registrations. */
class MembershipApplicationController extends Controller
{
    public function __construct(private MembershipApplicationService $applications) {}

    public function index(Request $request)
    {
        $applications = MembershipApplication::query()
            ->where('status', $request->string('status')->isNotEmpty() ? $request->string('status') : 'pending')
            ->with('user:id,name,email,email_verified_at')
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($applications->items())->map(fn (MembershipApplication $app) => $this->present($app)),
            'meta' => ['pagination' => [
                'page' => $applications->currentPage(), 'per_page' => $applications->perPage(), 'total' => $applications->total(),
            ]],
        ]);
    }

    public function show(string $applicationId)
    {
        $application = MembershipApplication::with('user:id,name,email,email_verified_at')->findOrFail($applicationId);

        return response()->json(['data' => $this->present($application, detailed: true)]);
    }

    public function approve(Request $request, string $applicationId)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $application = MembershipApplication::where('status', 'pending')->findOrFail($applicationId);
        $application = $this->applications->approve($application, $request->user(), $data['note'] ?? null);

        return response()->json(['data' => $this->present($application)]);
    }

    public function reject(Request $request, string $applicationId)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $application = MembershipApplication::where('status', 'pending')->findOrFail($applicationId);
        $application = $this->applications->reject($application, $request->user(), $data['note']);

        return response()->json(['data' => $this->present($application)]);
    }

    private function present(MembershipApplication $application, bool $detailed = false): array
    {
        $base = [
            'id' => $application->id,
            'user' => $application->user->only(['id', 'name', 'email', 'email_verified_at']),
            'status' => $application->status,
            'has_photo' => $application->photo_path !== null,
            'submitted_at' => $application->created_at,
        ];

        if (! $detailed) {
            return $base;
        }

        return [
            ...$base,
            'ack_impact_beyond_earning' => $application->ack_impact_beyond_earning,
            'ack_growth_mindset' => $application->ack_growth_mindset,
            'ack_interest_in_coaching' => $application->ack_interest_in_coaching,
            'ack_positive_impact' => $application->ack_positive_impact,
            'motivation' => $application->motivation,
            'photo_url' => $application->photo_path ? "/api/v1/members/{$application->user_id}/photo" : null,
            'reviewed_by' => $application->reviewer?->only(['id', 'name']),
            'reviewed_at' => $application->reviewed_at,
            'review_note' => $application->review_note,
        ];
    }
}
