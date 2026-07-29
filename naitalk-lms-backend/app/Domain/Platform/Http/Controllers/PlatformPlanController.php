<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Billing\Models\PlatformPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformPlanController extends Controller
{
    public function index()
    {
        return response()->json(['data' => PlatformPlan::with('features')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:255', 'unique:platform_plans,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'billing_period' => ['required', 'in:monthly,annual,free,trial,custom'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_public' => ['boolean'],
            'trial_days' => ['integer', 'min:0'],
            'features' => ['array'],
            'features.*.feature_key' => ['required_with:features', 'string'],
            'features.*.value' => ['required_with:features', 'string'],
        ]);

        $data['code'] ??= Str::slug($data['name']);

        $plan = PlatformPlan::create($data);

        foreach ($data['features'] ?? [] as $feature) {
            $plan->features()->create($feature);
        }

        return response()->json(['data' => $plan->load('features')], 201);
    }

    public function update(Request $request, PlatformPlan $plan)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'billing_period' => ['sometimes', 'in:monthly,annual,free,trial,custom'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_public' => ['boolean'],
            'trial_days' => ['integer', 'min:0'],
            'sort_order' => ['integer'],
        ]);

        $plan->update($data);

        return response()->json(['data' => $plan->fresh('features')]);
    }

    public function setFeature(Request $request, PlatformPlan $plan)
    {
        $data = $request->validate([
            'feature_key' => ['required', 'string'],
            'value' => ['required', 'string'],
        ]);

        $plan->features()->updateOrCreate(['feature_key' => $data['feature_key']], ['value' => $data['value']]);

        return response()->json(['data' => $plan->fresh('features')]);
    }

    public function destroy(PlatformPlan $plan)
    {
        $plan->delete();

        return response()->json(['data' => ['success' => true]]);
    }
}
