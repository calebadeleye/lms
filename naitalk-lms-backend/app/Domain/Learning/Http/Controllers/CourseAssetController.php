<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Public — no auth. Serves a course's thumbnail from the otherwise-private
 * `uploads` disk so a logged-out browser (browsing the public course
 * catalogue) can load it. The route's regex constraints (see routes/api.php)
 * are what keep this from reaching any other file on the disk — `courseId`
 * is digits-only and `filename` must match `thumbnail.<ext>`, so there's no
 * user-suppliable path segment that could traverse elsewhere.
 */
class CourseAssetController extends Controller
{
    public function show(string $courseId, string $filename)
    {
        $path = "courses/{$courseId}/{$filename}";

        if (! Storage::disk('uploads')->exists($path)) {
            abort(404);
        }

        return Storage::disk('uploads')->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
