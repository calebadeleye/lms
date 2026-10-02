<?php

namespace App\Domain\Site\Http\Controllers;

use App\Domain\Site\Models\Testimonial;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Testimonial::orderBy('created_at')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'quote' => ['required', 'string', 'max:1000'],
            'author' => ['required', 'string', 'max:255'],
        ]);

        $testimonial = Testimonial::create($data);

        return response()->json(['data' => $testimonial], 201);
    }

    public function destroy(string $testimonialId)
    {
        Testimonial::findOrFail($testimonialId)->delete();

        return response()->json(['data' => ['success' => true]]);
    }
}
