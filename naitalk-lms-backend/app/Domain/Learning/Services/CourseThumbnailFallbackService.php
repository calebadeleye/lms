<?php

namespace App\Domain\Learning\Services;

use App\Domain\Learning\Models\Course;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Picks a fitting stock photo from Pexels for a course whose admin never
 * uploaded a thumbnail of their own, keyed off the course title. Stores the
 * result through the exact same courses/{id}/thumbnail.{ext} convention
 * CourseController::uploadThumbnail() uses, so CourseAssetController and
 * Course::thumbnailUrl() need no changes to serve it — and a later manual
 * upload overwrites it exactly like it would overwrite any other thumbnail.
 *
 * Deliberately called once, at publish time (see CourseController::publish()),
 * not lazily inside thumbnailUrl() — that accessor runs on every course in
 * every list response, so fetching there would mean a live Pexels call (plus
 * an image download) per unthumbnailed course on every page load.
 *
 * Uses Pexels' "medium" size (a few hundred pixels wide, ballpark
 * 20-40KB) rather than "large"/"original" specifically to keep the course
 * catalogue's page weight down.
 */
class CourseThumbnailFallbackService
{
    private const SEARCH_URL = 'https://api.pexels.com/v1/search';

    public function fillMissing(Course $course): void
    {
        if ($course->thumbnail_path) {
            return;
        }

        $apiKey = config('services.pexels.key');
        if (! $apiKey) {
            return;
        }

        try {
            $search = Http::withHeaders(['Authorization' => $apiKey])
                ->timeout(5)
                ->get(self::SEARCH_URL, [
                    'query' => $course->title,
                    'per_page' => 1,
                    'orientation' => 'landscape',
                ]);

            if (! $search->successful()) {
                Log::warning("Pexels search failed for course #{$course->id}: {$search->status()}");

                return;
            }

            $imageUrl = $search->json('photos.0.src.medium');
            if (! $imageUrl) {
                return;
            }

            $image = Http::timeout(5)->get($imageUrl);
            if (! $image->successful()) {
                return;
            }

            $directory = "courses/{$course->id}";
            Storage::disk('uploads')->put("{$directory}/thumbnail.jpg", $image->body());
            $course->update(['thumbnail_path' => "{$directory}/thumbnail.jpg"]);
        } catch (\Throwable $e) {
            // Never let a Pexels/network hiccup block publishing the course.
            Log::warning("Pexels thumbnail fallback failed for course #{$course->id}: {$e->getMessage()}");
        }
    }
}
