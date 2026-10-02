<?php

namespace App\Providers;

use App\Domain\Coaching\Models\Booking;
use App\Domain\Coaching\Models\CoachingService;
use App\Domain\Commerce\Models\Order;
use App\Domain\Identity\Models\MembershipApplication;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Membership\Models\LearnerMembershipPlan;
use App\Domain\Membership\Models\LearnerSubscription;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // This API has no Blade views of its own, so password-reset links
        // must point into the Next.js frontend rather than a named backend
        // route (which is what Laravel's default ResetPassword notification
        // assumes exists).
        ResetPassword::createUrlUsing(function (User $notifiable, string $token) {
            $frontendUrl = rtrim((string) config('services.frontend.url'), '/');

            return "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($notifiable->getEmailForPasswordReset());
        });

        // Short, stable aliases for every polymorphic *_type column
        // (order_items.itemable_type, payment_allocations.allocatable_type).
        // Stored values never change even if a model class is renamed or
        // moved, and never leak internal namespace structure.
        //
        // Uses morphMap() rather than enforceMorphMap(): the latter is
        // global and strict, so it also governs polymorphic relations we
        // don't own (e.g. Sanctum's PersonalAccessToken::tokenable), and
        // throws ClassMorphViolationException for any class left out of
        // the list — including App\Models\User, which every createToken()
        // call touches.
        Relation::morphMap([
            'course' => Course::class,
            'membership_plan' => LearnerMembershipPlan::class,
            'coaching_service' => CoachingService::class,
            'enrolment' => Enrolment::class,
            'learner_subscription' => LearnerSubscription::class,
            'booking' => Booking::class,
            'order' => Order::class,
            'membership_application' => MembershipApplication::class,
        ]);
    }
}
