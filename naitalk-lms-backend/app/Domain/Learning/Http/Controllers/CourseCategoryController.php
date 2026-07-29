<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\CourseCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseCategoryController extends Controller
{
    /** Shared by the admin category picker (needs every category, to assign
     * a course still in draft) and the public course catalogue's sidebar
     * (only_with_published=1 — showing a category with nothing publicly
     * browsable in it just reads as a dead link). */
    public function index(Request $request)
    {
        $categories = CourseCategory::query()
            ->when(
                $request->boolean('only_with_published'),
                fn ($q) => $q->whereHas('courses', fn ($c) => $c->where('status', 'published'))
            )
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
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
