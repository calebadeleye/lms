<?php

namespace App\Domain\Coaching\Http\Controllers;

use App\Domain\Coaching\Models\Coach;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function index(string $coachId)
    {
        $coach = Coach::findOrFail($coachId);

        return response()->json(['data' => $coach->availabilityRules]);
    }

    public function store(Request $request, string $coachId)
    {
        $coach = Coach::findOrFail($coachId);

        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'timezone' => ['string', 'timezone'],
        ]);

        $rule = $coach->availabilityRules()->create([
            ...$data,
            'timezone' => $data['timezone'] ?? $coach->timezone,
        ]);

        return response()->json(['data' => $rule], 201);
    }

    public function destroy(string $ruleId)
    {
        \App\Domain\Coaching\Models\AvailabilityRule::findOrFail($ruleId)->delete();

        return response()->json(['data' => ['success' => true]]);
    }
}
