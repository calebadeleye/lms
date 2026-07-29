<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Public — no `tenant` or `auth` middleware. Serves a course's thumbnail
 * from the otherwise-private `tenants` disk so a logged-out browser
 * (browsing the public course catalogue) can load it. Mirrors
 * TenantAssetController's reasoning exactly: the route's regex constraints
 * (see routes/api.php) are what keep this from reaching any other file on
 * the disk — `courseId` is digits-only and `filename` must match
 * `thumbnail.<ext>`, so there's no user-suppliable path segment that could
 * traverse elsewhere.
 */
class CourseAssetController extends Controller
{
    public function show(string $tenantId, string $courseId, string $filename)
    {
        $path = "{$tenantId}/courses/{$courseId}/{$filename}";

        if (! Storage::disk('tenants')->exists($path)) {
            abort(404);
        }

        return Storage::disk('tenants')->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
