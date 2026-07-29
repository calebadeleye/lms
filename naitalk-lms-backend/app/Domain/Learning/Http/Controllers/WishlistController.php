<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Wishlist;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $wishlist = Wishlist::where('user_id', $request->user()->id)
            ->with('course.category')
            ->get();

        return response()->json(['data' => $wishlist]);
    }

    public function store(Request $request, string $courseId)
    {
        $item = Wishlist::firstOrCreate(['user_id' => $request->user()->id, 'course_id' => $courseId]);

        return response()->json(['data' => $item], 201);
    }

    public function destroy(Request $request, string $courseId)
    {
        Wishlist::where('user_id', $request->user()->id)->where('course_id', $courseId)->delete();

        return response()->json(['data' => ['success' => true]]);
    }
}
