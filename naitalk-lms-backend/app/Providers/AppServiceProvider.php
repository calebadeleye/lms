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
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        // Laravel's default VerifyEmail link is an absolute signed URL built
        // from the host of the *current request* — and here that request
        // arrives via the Next.js server-side proxy, so the host is the
        // internal 127.0.0.1:<port> address and the email ends up linking to
        // localhost. Sign only the path (verified with `signed:relative` on
        // the route, so the host never matters) and hand the link to the
        // frontend's own /api/auth/verify-email route, which relays it to the
        // backend and lands the user on a proper page.
        VerifyEmail::createUrlUsing(function (User $notifiable) {
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());

            $relative = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
                ['id' => $id, 'hash' => $hash],
                absolute: false,
            );

            $frontendUrl = rtrim((string) config('services.frontend.url'), '/');

            return "{$frontendUrl}/api/auth/verify-email/{$id}/{$hash}?".parse_url($relative, PHP_URL_QUERY);
        });

        VerifyEmail::toMailUsing(function (User $notifiable, string $url) {
            $minutes = (int) Config::get('auth.verification.expire', 60);

            return (new MailMessage)
                ->subject('Verify your email address')
                ->greeting("Hello {$notifiable->name},")
                ->line('Welcome to '.config('app.name').'! Please confirm your email address to finish setting up your account.')
                ->action('Verify email address', $url)
                ->line("This link will expire in {$minutes} minutes.")
                ->line('If you did not create an account, no further action is required.');
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
