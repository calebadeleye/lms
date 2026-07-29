<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\CourseCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseCategoryController extends Controller
{
    public function index()
    {
        return response()->json(['data' => CourseCategory::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category = CourseCategory::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, string $categoryId)
    {
        $category = CourseCategory::findOrFail($categoryId);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $category->update(['name' => $data['name']]);

        return response()->json(['data' => $category->fresh()]);
    }

    public function destroy(string $categoryId)
    {
        CourseCategory::findOrFail($categoryId)->delete();

        return response()->json(['data' => ['success' => true]]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (CourseCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
