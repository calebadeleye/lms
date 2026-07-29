<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Coaching\Models\AvailabilityRule;
use App\Domain\Coaching\Models\Booking;
use App\Domain\Coaching\Models\Coach;
use App\Domain\Coaching\Models\CoachingService;
use App\Domain\Coaching\Models\CoachingSession;
use App\Domain\Coaching\Models\SessionAttendance;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\OrderItem;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\PaymentAllocation;
use App\Domain\Commerce\Models\Refund;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Learning\Models\Assignment;
use App\Domain\Learning\Models\AssignmentSubmission;
use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseCategory;
use App\Domain\Learning\Models\CourseModule;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Lesson;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Quiz;
use App\Domain\Learning\Models\QuizAnswer;
use App\Domain\Learning\Models\QuizAttempt;
use App\Domain\Learning\Models\QuizOption;
use App\Domain\Learning\Models\QuizQuestion;
use App\Domain\Learning\Models\Review;
use App\Domain\Learning\Models\Wishlist;
use App\Domain\Membership\Models\LearnerMembershipPlan;
use App\Domain\Membership\Models\LearnerSubscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantBranding;
use App\Domain\Tenancy\Models\TenantDomain;
use App\Models\User;

/**
 * Gathers a complete, structured snapshot of a tenant's own data — every
 * Phase 1-3 domain — as a plain array ready to `json_encode`. Read-only and
 * additive: never touches the tenant being exported. Deliberately excludes
 * `tenant_payment_configs` (payment credentials, even encrypted, are never
 * written to an export file), `webhook_events` (internal processing log, not
 * tenant data), platform billing (`PlatformPlan`/`TenantSubscription` — NAI
 * TALK's relationship with the tenant, not the tenant's own product data),
 * and ephemeral identity rows (`Invitation`, `UserSession`).
 *
 * Caller must have already set TenantContext to the tenant being exported —
 * every query below relies on `BelongsToTenant`'s automatic scoping rather
 * than filtering by tenant_id explicitly.
 */
class TenantExportService
{
    public function export(Tenant $tenant): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'tenant' => $tenant->only(['id', 'slug', 'name', 'legal_name', 'support_email']),
            'branding' => TenantBranding::first()?->toArray(),
            'domains' => TenantDomain::all()->toArray(),
            'users' => $this->users($tenant),
            'roles' => Role::all()->toArray(),
            'tenant_users' => TenantUser::all()->toArray(),

            'course_categories' => CourseCategory::all()->toArray(),
            'courses' => Course::withTrashed()->get()->toArray(),
            'course_modules' => CourseModule::all()->toArray(),
            'lessons' => Lesson::withTrashed()->get()->toArray(),
            'quizzes' => Quiz::all()->toArray(),
            'quiz_questions' => QuizQuestion::all()->toArray(),
            'quiz_options' => QuizOption::all()->toArray(),
            'assignments' => Assignment::all()->toArray(),
            'enrolments' => Enrolment::all()->toArray(),
            'lesson_progress' => LessonProgress::all()->toArray(),
            'quiz_attempts' => QuizAttempt::all()->toArray(),
            'quiz_answers' => QuizAnswer::all()->toArray(),
            'assignment_submissions' => AssignmentSubmission::all()->toArray(),
            'reviews' => Review::all()->toArray(),
            'wishlists' => Wishlist::all()->toArray(),
            'certificates' => Certificate::all()->toArray(),

            'membership_plans' => LearnerMembershipPlan::withTrashed()->get()->toArray(),
            'learner_subscriptions' => LearnerSubscription::all()->toArray(),

            'coaches' => Coach::all()->toArray(),
            'coaching_services' => CoachingService::withTrashed()->get()->toArray(),
            'availability_rules' => AvailabilityRule::all()->toArray(),
            'coaching_sessions' => CoachingSession::all()->toArray(),
            'bookings' => Booking::all()->toArray(),
            'session_attendance' => SessionAttendance::all()->toArray(),

            'orders' => Order::all()->toArray(),
            'order_items' => OrderItem::all()->toArray(),
            'payments' => Payment::all()->toArray(),
            'payment_allocations' => PaymentAllocation::all()->toArray(),
            'refunds' => Refund::all()->toArray(),
        ];
    }

    /** Only the tenant's members, and never the password hash. */
    private function users(Tenant $tenant): array
    {
        $userIds = TenantUser::pluck('user_id');

        return User::whereIn('id', $userIds)
            ->get(['id', 'public_id', 'name', 'email', 'email_verified_at'])
            ->toArray();
    }
}
