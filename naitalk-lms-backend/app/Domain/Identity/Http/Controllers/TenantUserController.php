<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Billing\Services\TenantUsageService;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantUserController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private TenantUsageService $usage,
    ) {}

    /**
     * Admin — every member of this tenant. Defaults to active-only (the
     * shape every existing picker — assigning a coach, an instructor —
     * expects); the Users management page asks for everyone explicitly so
     * it can also show and reactivate deactivated members.
     *
     * Pagination is opt-in via `page`/`per_page` — pickers call this with
     * neither and still get the full array they've always gotten (they
     * need every member to populate a <select>, not a page of them). Only
     * the Users management page passes `page`, so only it gets the
     * {data, meta} shape back.
     */
    public function index(Request $request)
    {
        $query = TenantUser::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('status', 'active'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->with('user:id,name,email', 'role:id,name,slug');

        $format = fn (TenantUser $tu) => [
            'id' => $tu->user->id,
            'name' => $tu->user->name,
            'email' => $tu->user->email,
            'role' => $tu->role->name,
            'role_id' => $tu->role->id,
            'status' => $tu->status,
            'joined_at' => $tu->joined_at,
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

    /** Assignable roles for this tenant, for the invite/role-change forms. */
    public function roles()
    {
        return response()->json(['data' => Role::forTenant($this->tenantContext->id())->orderBy('name')->get()]);
    }

    public function updateRole(Request $request, string $userId)
    {
        $data = $request->validate(['role_id' => ['required', 'exists:roles,id']]);

        if ((int) $userId === $request->user()->id) {
            throw ValidationException::withMessages(['role_id' => ['You cannot change your own role.']]);
        }

        $tenant = $this->tenantContext->tenant();
        $role = Role::forTenant($tenant->id)->findOrFail($data['role_id']);
        $member = TenantUser::where('user_id', $userId)->firstOrFail();

        $oldMetric = $member->role ? $this->usage->metricForRole($member->role->slug) : null;
        $newMetric = $this->usage->metricForRole($role->slug);

        // Only a genuine increase (moving into a counted role from one that
        // wasn't counted toward the same metric) needs a capacity check —
        // e.g. tenant-owner -> tenant-administrator is a lateral move
        // within the same "administrators" seat, not a new one.
        if ($newMetric && $newMetric !== $oldMetric && ! $this->usage->hasCapacity($tenant, $newMetric)) {
            throw ValidationException::withMessages([
                'role_id' => ["Your plan's limit for this role has been reached. Upgrade your plan to add more."],
            ]);
        }

        $member->update(['role_id' => $role->id]);

        if ($oldMetric) {
            $this->usage->forget($tenant, $oldMetric);
        }
        if ($newMetric) {
            $this->usage->forget($tenant, $newMetric);
        }

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

        $member = TenantUser::where('user_id', $userId)->firstOrFail();
        $member->update(['status' => 'inactive']);

        if ($member->role && ($metric = $this->usage->metricForRole($member->role->slug))) {
            $this->usage->forget($this->tenantContext->tenant(), $metric);
        }

        return response()->json(['data' => ['success' => true]]);
    }

    public function reactivate(string $userId)
    {
        $tenant = $this->tenantContext->tenant();
        $member = TenantUser::where('user_id', $userId)->firstOrFail();
        $metric = $member->role ? $this->usage->metricForRole($member->role->slug) : null;

        if ($metric && ! $this->usage->hasCapacity($tenant, $metric)) {
            throw ValidationException::withMessages([
                'user' => ["Your plan's limit for this role has been reached. Upgrade your plan to reactivate them."],
            ]);
        }

        $member->update(['status' => 'active']);

        if ($metric) {
            $this->usage->forget($tenant, $metric);
        }

        return response()->json(['data' => ['success' => true]]);
    }
}
