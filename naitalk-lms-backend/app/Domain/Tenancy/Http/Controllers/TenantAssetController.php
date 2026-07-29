<?php

namespace App\Domain\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Public — no `tenant` or `auth` middleware. Serves exactly one thing: a
 * tenant's own logo/favicon/hero image from the (otherwise private)
 * `tenants` disk, so a logged-out browser can actually load them. The route
 * itself constrains `filename` to `logo.*`/`favicon.*`/`hero.*` (see
 * routes/api.php) and `tenantId` to the UUID shape, so this can never be
 * used to reach any other file under that disk (exports, certificates) —
 * there is no user-suppliable path segment here, only an exact filename
 * match inside a fixed subdirectory.
 */
class TenantAssetController extends Controller
{
    public function show(string $tenantId, string $filename)
    {
        $path = "{$tenantId}/branding/{$filename}";

        if (! Storage::disk('tenants')->exists($path)) {
            abort(404);
        }

        return Storage::disk('tenants')->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
