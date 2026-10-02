<?php

namespace App\Domain\Membership\Http\Controllers;

use App\Domain\Membership\Models\LearnerMembershipPlan;
use App\Domain\Membership\Models\LearnerSubscription;
use App\Domain\Membership\Services\MembershipService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MembershipPlanController extends Controller
{
    public function __construct(private MembershipService $memberships) {}

    /** Public — active plans for the pricing page. */
    public function index()
    {
        return response()->json(['data' => LearnerMembershipPlan::where('is_active', true)->get()]);
    }

    public function adminIndex()
    {
        return response()->json(['data' => LearnerMembershipPlan::withCount('subscriptions')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePlan($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        $plan = LearnerMembershipPlan::create($data);

        return response()->json(['data' => $plan], 201);
    }

    public function update(Request $request, string $planId)
    {
        $plan = LearnerMembershipPlan::findOrFail($planId);
        $data = $this->validatePlan($request, partial: true);

        $plan->update($data);

        return response()->json(['data' => $plan->fresh()]);
    }

    public function destroy(string $planId)
    {
        LearnerMembershipPlan::findOrFail($planId)->update(['is_active' => false]);

        return response()->json(['data' => ['success' => true]]);
    }

    public function mySubscription(Request $request)
    {
        $subscription = LearnerSubscription::where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'past_due'])
            ->with('plan')
            ->latest()
            ->first();

        return response()->json(['data' => $subscription]);
    }

    public function cancel(Request $request, string $subscriptionId)
    {
        $subscription = LearnerSubscription::where('user_id', $request->user()->id)->findOrFail($subscriptionId);
        $this->memberships->cancel($subscription, atPeriodEnd: true);

        return response()->json(['data' => $subscription->fresh()]);
    }

    private function validatePlan(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'billing_period' => ['required', 'in:monthly,annual'],
            'price_cents' => ['integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'benefits' => ['array'],
            'is_active' => ['boolean'],
        ];

        if ($partial) {
            $rules = collect($rules)->mapWithKeys(fn ($rule, $key) => [$key => array_merge(['sometimes'], (array) $rule)])->all();
        }

        return $request->validate($rules);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (LearnerMembershipPlan::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
