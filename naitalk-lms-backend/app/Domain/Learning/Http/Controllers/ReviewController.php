<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Review;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function index(string $courseId)
    {
        $reviews = Review::where('course_id', $courseId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $reviews]);
    }

    /** Only enrolled students may review a course. */
    public function store(Request $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);

        $isEnrolled = Enrolment::where('course_id', $course->id)->where('user_id', $request->user()->id)->exists();

        if (! $isEnrolled) {
            throw ValidationException::withMessages(['course' => ['You must be enrolled in this course to review it.']]);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = Review::updateOrCreate(
            ['course_id' => $course->id, 'user_id' => $request->user()->id],
            $data
        );

        return response()->json(['data' => $review->fresh('user')], 201);
    }

    public function destroy(Request $request, string $reviewId)
    {
        Review::where('user_id', $request->user()->id)->findOrFail($reviewId)->delete();

        return response()->json(['data' => ['success' => true]]);
    }
}
