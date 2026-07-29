<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseModule;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CourseModuleController extends Controller
{
    public function index(string $courseId)
    {
        $course = Course::findOrFail($courseId);

        return response()->json(['data' => $course->modules()->with('lessons')->get()]);
    }

    public function store(Request $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $module = $course->modules()->create([
            'title' => $data['title'],
            'sort_order' => $course->modules()->max('sort_order') + 1,
        ]);

        return response()->json(['data' => $module], 201);
    }

    public function update(Request $request, string $moduleId)
    {
        $module = CourseModule::findOrFail($moduleId);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $module->update($data);

        return response()->json(['data' => $module->fresh()]);
    }

    public function destroy(string $moduleId)
    {
        CourseModule::findOrFail($moduleId)->delete();

        return response()->json(['data' => ['success' => true]]);
    }
}
