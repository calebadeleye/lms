<?php

namespace App\Domain\Coaching\Http\Controllers;

use App\Domain\Coaching\Models\Coach;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    /** Public — coach catalogue. */
    public function index()
    {
        $coaches = Coach::where('is_active', true)
            ->with(['user:id,name', 'services' => fn ($q) => $q->where('is_active', true)])
            ->get();

        return response()->json(['data' => $coaches->map(fn (Coach $c) => $this->present($c))]);
    }

    public function show(string $coachId)
    {
        $coach = Coach::where('is_active', true)
            ->with(['user:id,name', 'services' => fn ($q) => $q->where('is_active', true), 'availabilityRules'])
            ->findOrFail($coachId);

        return response()->json(['data' => [
            ...$this->present($coach),
            'availability_rules' => $coach->availabilityRules,
        ]]);
    }

    public function adminIndex()
    {
        return response()->json(['data' => Coach::with('user:id,name,email')->get()]);
    }

    /** Admin — a single coach with every service and availability rule
     * (including inactive services, unlike the public show()), for the
     * coach management/edit screen. */
    public function adminShow(string $coachId)
    {
        $coach = Coach::with('user:id,name,email', 'services', 'availabilityRules')->findOrFail($coachId);

        return response()->json(['data' => $coach]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'years_experience' => ['nullable', 'integer', 'min:0'],
            'timezone' => ['string', 'timezone'],
        ]);

        $coach = Coach::create($data);

        return response()->json(['data' => $coach], 201);
    }

    public function update(Request $request, string $coachId)
    {
        $coach = Coach::findOrFail($coachId);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'bio' => ['sometimes', 'string'],
            'years_experience' => ['sometimes', 'integer', 'min:0'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $coach->update($data);

        return response()->json(['data' => $coach->fresh()]);
    }

    private function present(Coach $coach): array
    {
        return [
            'id' => $coach->id,
            'name' => $coach->user->name,
            'title' => $coach->title,
            'bio' => $coach->bio,
            'years_experience' => $coach->years_experience,
            'timezone' => $coach->timezone,
            'services' => $coach->services->map->only([
                'id', 'title', 'description', 'session_type', 'duration_minutes', 'is_free', 'price_cents', 'currency',
            ]),
        ];
    }
}
