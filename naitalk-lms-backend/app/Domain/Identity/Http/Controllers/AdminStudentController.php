<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Enrolment;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The site-wide learner roster — every student across every course, with
 * aggregate progress. Distinct from EnrolmentController::forCourse(), which
 * is scoped to one course's own learner list.
 */
class AdminStudentController extends Controller
{
    public function index(Request $request)
    {
        $students = User::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('status', 'active'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($uq) => $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('joined_at')
            ->paginate($request->integer('per_page', 50));

        $userIds = collect($students->items())->pluck('id');

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
            'data' => collect($students->items())->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'joined_at' => $user->joined_at,
                'enrolments_count' => (int) ($enrolmentStats[$user->id]->total ?? 0),
                'completed_count' => (int) ($enrolmentStats[$user->id]->completed ?? 0),
                'certificates_count' => (int) ($certificateCounts[$user->id]->total ?? 0),
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

        User::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->where('id', $userId)->firstOrFail()
            ->update(['status' => 'inactive']);

        return response()->json(['data' => ['success' => true]]);
    }

    public function reactivate(string $userId)
    {
        User::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->where('id', $userId)->firstOrFail()
            ->update(['status' => 'active']);

        return response()->json(['data' => ['success' => true]]);
    }
}
