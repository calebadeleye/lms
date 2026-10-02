<?php

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Models\MembershipApplication;
use App\Domain\Identity\Services\PermissionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Auth-gated — unlike course thumbnails, these are photographs of real
 * people submitted for a "grand welcome," not public marketing assets.
 * Readable only by the owning user or someone holding members.approve.
 */
class MemberPhotoController extends Controller
{
    public function __construct(private PermissionService $permissions) {}

    public function show(Request $request, string $userId)
    {
        $viewer = $request->user();

        if ((int) $userId !== $viewer->id && ! $this->permissions->userHasPermission($viewer, 'members.approve')) {
            abort(403);
        }

        $application = MembershipApplication::where('user_id', $userId)->firstOrFail();

        if (! $application->photo_path || ! Storage::disk('uploads')->exists($application->photo_path)) {
            abort(404);
        }

        return Storage::disk('uploads')->response($application->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
