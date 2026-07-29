<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Models\TenantUser;
use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Enrolment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The tenant-wide learner roster — every student across every course, with
 * aggregate progress. Distinct from EnrolmentController::forCourse(), which
 * is scoped to one course's own learner list.
 */
class AdminStudentController extends Controller
{
    public function index(Request $request)
    {
        $students = TenantUser::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('status', 'active'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->with('user:id,name,email')
            ->orderByDesc('joined_at')
            ->paginate($request->integer('per_page', 50));

        $userIds = collect($students->items())->pluck('user.id');

        $enrolmentStats = Enrolment::whereIn('user_id', $userIds)
            ->selectRaw('user_id, count(*) as total, sum(status = "completed") as completed')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $certificateCounts = Certificate::whereIn('user_id', $userIds)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return response()->json([
            'data' => collect($students->items())->map(fn (TenantUser $tu) => [
                'id' => $tu->user->id,
                'name' => $tu->user->name,
                'email' => $tu->user->email,
                'status' => $tu->status,
                'joined_at' => $tu->joined_at,
                'enrolments_count' => (int) ($enrolmentStats[$tu->user->id]->total ?? 0),
                'completed_count' => (int) ($enrolmentStats[$tu->user->id]->completed ?? 0),
                'certificates_count' => (int) ($certificateCounts[$tu->user->id]->total ?? 0),
            ]),
            'meta' => ['pagination' => [
                'page' => $students->currentPage(), 'per_page' => $students->perPage(), 'total' => $students->total(),
            ]],
        ]);
    }

    public function destroy(Request $request, string $userId)
    {
        if ((int) $userId === $request->user()->id) {
            throw ValidationException::withMessages(['user' => ['You cannot remove yourself.']]);
        }

        $member = TenantUser::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->where('user_id', $userId)->firstOrFail();
        $member->update(['status' => 'inactive']);

        return response()->json(['data' => ['success' => true]]);
    }

    public function reactivate(string $userId)
    {
        $member = TenantUser::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->where('user_id', $userId)->firstOrFail();
        $member->update(['status' => 'active']);

        return response()->json(['data' => ['success' => true]]);
    }
}
