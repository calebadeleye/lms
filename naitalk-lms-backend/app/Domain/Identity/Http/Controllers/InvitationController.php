<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Identity\Notifications\AddedToTenantNotification;
use App\Domain\Identity\Notifications\TenantInvitationNotification;
use App\Domain\Identity\Services\AuthService;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthService $auth,
    ) {}

    /** Admin — pending invitations for this tenant. */
    public function index()
    {
        $invitations = Invitation::where('status', 'pending')->with('role:id,name')->orderByDesc('created_at')->get();

        return response()->json(['data' => $invitations]);
    }

    /**
     * Admin — invite someone by email. Two paths: if that email already has
     * an account (anywhere — a User row isn't tenant-scoped), they're added
     * to this tenant directly since there's no need to make them set a
     * password again; otherwise a real invitation + accept-by-token flow.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $tenant = $this->tenantContext->tenant();
        $role = Role::forTenant($tenant->id)->findOrFail($data['role_id']);
        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser) {
            if (TenantUser::where('user_id', $existingUser->id)->exists()) {
                throw ValidationException::withMessages(['email' => ['This person is already a member of this tenant.']]);
            }

            TenantUser::create([
                'user_id' => $existingUser->id, 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now(),
            ]);

            Notification::route('mail', $existingUser->email)->notify(new AddedToTenantNotification($tenant, $role));

            return response()->json(['data' => ['status' => 'added_existing_user']], 201);
        }

        if (Invitation::where('email', $data['email'])->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['email' => ['An invitation has already been sent to this email.']]);
        }

        $invitation = Invitation::create([
            'email' => $data['email'],
            'role_id' => $role->id,
            'token' => Str::random(48),
            'invited_by' => $request->user()->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $data['email'])->notify(new TenantInvitationNotification($invitation));

        return response()->json(['data' => $invitation->load('role:id,name')], 201);
    }

    public function destroy(string $invitationId)
    {
        $invitation = Invitation::where('status', 'pending')->findOrFail($invitationId);
        $invitation->update(['status' => 'revoked']);

        return response()->json(['data' => ['success' => true]]);
    }

    /** Public — preview for the accept-invitation page ("You've been
     * invited to join X as Y"), before the invitee commits to anything. */
    public function show(string $token)
    {
        $invitation = Invitation::where('token', $token)->where('status', 'pending')->firstOrFail();

        if ($invitation->isExpired()) {
            return response()->json(['errors' => [['code' => 'invitation_expired', 'message' => 'This invitation has expired.']]], 410);
        }

        return response()->json(['data' => [
            'email' => $invitation->email,
            'tenant_name' => $invitation->tenant->name,
            'role_name' => $invitation->role->name,
        ]]);
    }

    /** Public — creates the account and logs them straight in, same as
     * register(). Invited accounts skip email verification: an admin
     * already vouched for this exact address by inviting it, unlike a
     * self-registration where no one has confirmed anything yet. */
    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->where('status', 'pending')->firstOrFail();

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['token' => ['This invitation has expired.']]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $tenant = $invitation->tenant;

        $user = DB::transaction(function () use ($data, $invitation, $tenant) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            TenantUser::create([
                'user_id' => $user->id,
                'role_id' => $invitation->role_id,
                'status' => 'active',
                'invited_by' => $invitation->invited_by,
                'joined_at' => now(),
            ]);

            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);

            return $user;
        });

        $issued = $this->auth->issueToken($user, $request, $tenant->id);

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'public_id', 'name', 'email']),
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ],
        ], 201);
    }
}
