<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Models\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    /**
     * Admin — every member. Defaults to active-only (the shape every
     * existing picker — assigning a coach, an instructor — expects); the
     * Users management page asks for everyone explicitly so it can also
     * show and reactivate deactivated members.
     *
     * Pagination is opt-in via `page`/`per_page` — pickers call this with
     * neither and still get the full array they've always gotten (they
     * need every member to populate a <select>, not a page of them). Only
     * the Users management page passes `page`, so only it gets the
     * {data, meta} shape back.
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('status', 'active'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($uq) => $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->with('role:id,name,slug');

        $format = fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
            'role_id' => $user->role_id,
            'status' => $user->status,
            'joined_at' => $user->joined_at,
        ];

        if ($request->hasAny(['page', 'per_page'])) {
            $members = $query->orderBy('joined_at', 'desc')->paginate($request->integer('per_page', 20));

            return response()->json([
                'data' => collect($members->items())->map($format),
                'meta' => ['pagination' => [
                    'page' => $members->currentPage(), 'per_page' => $members->perPage(), 'total' => $members->total(),
                ]],
            ]);
        }

        return response()->json(['data' => $query->get()->map($format)]);
    }

    /** Assignable roles, for the invite/role-change forms. */
    public function roles()
    {
        return response()->json(['data' => Role::orderBy('name')->get()]);
    }

    public function updateRole(Request $request, string $userId)
    {
        $data = $request->validate(['role_id' => ['required', 'exists:roles,id']]);

        if ((int) $userId === $request->user()->id) {
            throw ValidationException::withMessages(['role_id' => ['You cannot change your own role.']]);
        }

        $member = User::findOrFail($userId);
        $member->update(['role_id' => $data['role_id']]);

        return response()->json(['data' => ['success' => true]]);
    }

    /** Deactivate — never a hard delete, matching how every other
     * "remove someone" action in this app works (course instructors,
     * coaches, community channels all soft-deactivate). */
    public function destroy(Request $request, string $userId)
    {
        if ((int) $userId === $request->user()->id) {
            throw ValidationException::withMessages(['user' => ['You cannot remove yourself.']]);
        }

        User::findOrFail($userId)->update(['status' => 'inactive']);

        return response()->json(['data' => ['success' => true]]);
    }

    public function reactivate(string $userId)
    {
        User::findOrFail($userId)->update(['status' => 'active']);

        return response()->json(['data' => ['success' => true]]);
    }
}
