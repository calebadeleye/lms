<?php

namespace App\Domain\Coaching\Http\Controllers;

use App\Domain\Coaching\Models\Coach;
use App\Domain\Coaching\Models\CoachingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoachingServiceController extends Controller
{
    /** Public — coaching catalogue. */
    public function index()
    {
        $services = CoachingService::where('is_active', true)->with('coach.user:id,name')->get();

        return response()->json(['data' => $services]);
    }

    public function store(Request $request, string $coachId)
    {
        $coach = Coach::findOrFail($coachId);
        $data = $this->validateService($request);

        $service = $coach->services()->create($data);

        return response()->json(['data' => $service], 201);
    }

    public function update(Request $request, string $serviceId)
    {
        $service = CoachingService::findOrFail($serviceId);
        $data = $this->validateService($request, partial: true);

        $service->update($data);

        return response()->json(['data' => $service->fresh()]);
    }

    public function destroy(string $serviceId)
    {
        CoachingService::findOrFail($serviceId)->update(['is_active' => false]);

        return response()->json(['data' => ['success' => true]]);
    }

    private function validateService(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'session_type' => ['required', 'in:one_to_one,group'],
            'duration_minutes' => ['integer', 'min:5'],
            'is_free' => ['boolean'],
            'price_cents' => ['integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'max_participants' => ['integer', 'min:1'],
        ];

        if ($partial) {
            $rules = collect($rules)->mapWithKeys(fn ($rule, $key) => [$key => array_merge(['sometimes'], (array) $rule)])->all();
        }

        return $request->validate($rules);
    }
}
