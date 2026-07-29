<?php

use App\Domain\Billing\Http\Controllers\TenantSubscriptionController;
use App\Domain\Coaching\Http\Controllers\AvailabilityController;
use App\Domain\Coaching\Http\Controllers\BookingController;
use App\Domain\Coaching\Http\Controllers\CoachController;
use App\Domain\Coaching\Http\Controllers\CoachingServiceController;
use App\Domain\Coaching\Http\Controllers\CoachingSessionController;
use App\Domain\Commerce\Http\Controllers\CheckoutController;
use App\Domain\Commerce\Http\Controllers\OrderController;
use App\Domain\Commerce\Http\Controllers\PaymentConfigController;
use App\Domain\Commerce\Http\Controllers\RefundController;
use App\Domain\Commerce\Http\Controllers\WebhookController;
use App\Domain\Identity\Http\Controllers\AdminStudentController;
use App\Domain\Identity\Http\Controllers\AuthController;
use App\Domain\Identity\Http\Controllers\InvitationController;
use App\Domain\Identity\Http\Controllers\PlatformAuthController;
use App\Domain\Identity\Http\Controllers\TenantUserController;
use App\Domain\Learning\Http\Controllers\AssignmentController;
use App\Domain\Learning\Http\Controllers\CertificateController;
use App\Domain\Learning\Http\Controllers\CourseAssetController;
use App\Domain\Learning\Http\Controllers\CourseCategoryController;
use App\Domain\Learning\Http\Controllers\CourseController;
use App\Domain\Learning\Http\Controllers\CourseModuleController;
use App\Domain\Learning\Http\Controllers\EnrolmentController;
use App\Domain\Learning\Http\Controllers\LessonController;
use App\Domain\Learning\Http\Controllers\ProgressController;
use App\Domain\Learning\Http\Controllers\QuizAttemptController;
use App\Domain\Learning\Http\Controllers\QuizController;
use App\Domain\Learning\Http\Controllers\ReviewController;
use App\Domain\Learning\Http\Controllers\WishlistController;
use App\Domain\Membership\Http\Controllers\MembershipPlanController;
use App\Domain\Platform\Http\Controllers\PlatformDomainController;
use App\Domain\Platform\Http\Controllers\PlatformExportController;
use App\Domain\Platform\Http\Controllers\PlatformManagedPaymentsController;
use App\Domain\Platform\Http\Controllers\PlatformPlanController;
use App\Domain\Platform\Http\Controllers\PlatformSubscriptionController;
use App\Domain\Platform\Http\Controllers\PlatformTenantController;
use App\Domain\Tenancy\Http\Controllers\BrandingController;
use App\Domain\Tenancy\Http\Controllers\DomainController;
use App\Domain\Tenancy\Http\Controllers\ExportController;
use App\Domain\Tenancy\Http\Controllers\TenantAssetController;
use App\Domain\Tenancy\Http\Controllers\TenantConfigController;
use App\Domain\Tenancy\Http\Controllers\TestimonialController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['data' => ['pong' => true]]));

// Public — no `tenant` middleware. Comes directly from the payment
// provider's servers, never through a tenant hostname; the tenant is
// resolved from $webhookToken instead. See WebhookController and
// ARCHITECTURE.md §11.
Route::post('/webhooks/{provider}/{webhookToken}', [WebhookController::class, 'handle']);

// Public — no `tenant` middleware. A certificate verifier (an employer
// scanning a printed QR code) has no tenant hostname context; the tenant is
// resolved from the globally-unique verification code instead. See
// ARCHITECTURE.md §12.
Route::get('/certificates/verify/{code}', [CertificateController::class, 'verify']);

// Public, hostname-resolved tenant config — no auth. Cached by the
// controller; this is what the Next.js layout fetches on every request.
Route::middleware('tenant')->get('/tenant-config', [TenantConfigController::class, 'show']);

// Public — no `tenant`/`auth` middleware. Serves a tenant's logo/favicon/hero
// image from the otherwise-private `tenants` disk so a logged-out browser can
// load them. The regex constraints are security-critical: they're what
// keeps this from reaching any other file on the disk. See
// TenantAssetController's docblock.
Route::get('/tenant-assets/branding/{tenantId}/{filename}', [TenantAssetController::class, 'show'])
    ->where('tenantId', '[0-9a-f-]{36}')
    ->where('filename', '(logo|favicon|hero)\.[a-zA-Z0-9]+');

// Public — no `tenant`/`auth` middleware. Serves a course's thumbnail from
// the otherwise-private `tenants` disk so the public course catalogue can
// load it while logged out. See CourseAssetController's docblock.
Route::get('/course-assets/{tenantId}/{courseId}/{filename}', [CourseAssetController::class, 'show'])
    ->where('tenantId', '[0-9a-f-]{36}')
    ->where('courseId', '[0-9]+')
    ->where('filename', 'thumbnail\.[a-zA-Z0-9]+');

// Named `verification.verify` to match what Laravel's VerifyEmail
// notification generates via route(). Signed, so the middleware validates
// the URL hasn't been tampered with independent of any tenant/auth guard.
Route::get('/auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Tenant-domain auth (learners, instructors, tenant staff)
|--------------------------------------------------------------------------
| All routes here resolve tenant context from the Host header. See
| ARCHITECTURE.md §2 for why this is a BFF pattern rather than shared
| cookies.
*/
// Public — invitee has no account yet (or isn't logged in). Tenant-scoped
// like register/login: resolved from the Host header, not an auth guard.
Route::middleware('tenant')->group(function () {
    Route::get('/invitations/{token}', [InvitationController::class, 'show']);
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);
});

Route::prefix('auth')->middleware('tenant')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/mfa/verify', [AuthController::class, 'verifyMfa']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/email/resend', [AuthController::class, 'resendVerification']);
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeSession']);
    });
});

/*
|--------------------------------------------------------------------------
| Tenant-scoped authenticated API (staff/admin actions within a tenant)
|--------------------------------------------------------------------------
*/
Route::prefix('tenant')->middleware(['tenant', 'auth:sanctum'])->group(function () {
    Route::get('/subscription', [TenantSubscriptionController::class, 'show'])->middleware('permission:subscription.manage');

    Route::get('/branding', [BrandingController::class, 'show']);
    Route::put('/branding', [BrandingController::class, 'update'])->middleware('permission:branding.manage');
    Route::post('/branding/logo', [BrandingController::class, 'uploadLogo'])->middleware('permission:branding.manage');
    Route::post('/branding/favicon', [BrandingController::class, 'uploadFavicon'])->middleware('permission:branding.manage');
    Route::post('/branding/hero-image', [BrandingController::class, 'uploadHeroImage'])->middleware('permission:branding.manage');

    Route::get('/testimonials', [TestimonialController::class, 'index'])->middleware('permission:branding.manage');
    Route::post('/testimonials', [TestimonialController::class, 'store'])->middleware('permission:branding.manage');
    Route::delete('/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])->middleware('permission:branding.manage');

    Route::get('/domains', [DomainController::class, 'index'])->middleware('permission:domains.manage');
    Route::post('/domains', [DomainController::class, 'store'])->middleware('permission:domains.manage');
    Route::post('/domains/{domain}/verify', [DomainController::class, 'verify'])->middleware('permission:domains.manage');
    Route::post('/domains/{domain}/make-primary', [DomainController::class, 'makePrimary'])->middleware('permission:domains.manage');
    Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->middleware('permission:domains.manage');
});

/*
|--------------------------------------------------------------------------
| Learning — public catalogue (no auth required)
|--------------------------------------------------------------------------
*/
Route::middleware('tenant')->group(function () {
    Route::get('/course-categories', [CourseCategoryController::class, 'index']);
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{courseId}/reviews', [ReviewController::class, 'index'])->whereNumber('courseId');

    Route::get('/membership-plans', [MembershipPlanController::class, 'index']);
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/coaches/{coachId}', [CoachController::class, 'show']);
    Route::get('/coaching-services', [CoachingServiceController::class, 'index']);
    Route::get('/coaching-services/{serviceId}/sessions', [CoachingSessionController::class, 'index']);
});

// Public, but personalizes the response (unlocks already-enrolled lessons)
// when the visitor happens to be logged in — needs 'optional-auth' ahead of
// 'tenant' so both this route and ResolveTenant's own membership check see
// the correctly resolved user. See TryAuthenticateSanctum's docblock.
Route::middleware(['optional-auth', 'tenant'])->group(function () {
    Route::get('/courses/{slug}', [CourseController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Learning — authenticated student actions
|--------------------------------------------------------------------------
| `verified` here (not on /auth/* or /admin/*) — self-registration is the
| only flow that produces a genuinely-unverified user, and it always
| assigns the student role. Staff/owner accounts are provisioned directly
| (TenantProvisioningService, invitations) rather than self-registered, and
| are marked verified at creation time.
*/
Route::middleware(['tenant', 'auth:sanctum', 'verified'])->group(function () {
    Route::post('/courses/{courseId}/enrol', [CourseController::class, 'enrol']);
    Route::post('/courses/{courseId}/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{reviewId}', [ReviewController::class, 'destroy']);

    Route::get('/my/enrolments', [EnrolmentController::class, 'index']);
    Route::get('/my/certificates', [CertificateController::class, 'mine']);
    Route::get('/my/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{courseId}', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{courseId}', [WishlistController::class, 'destroy']);

    Route::get('/lessons/{lessonId}', [LessonController::class, 'show']);
    Route::get('/lessons/{lessonId}/material', [LessonController::class, 'downloadMaterial']);
    Route::post('/lessons/{lessonId}/progress/position', [ProgressController::class, 'updatePosition']);
    Route::post('/lessons/{lessonId}/progress/complete', [ProgressController::class, 'markComplete']);

    Route::get('/lessons/{lessonId}/quiz', [QuizController::class, 'show']);
    Route::post('/lessons/{lessonId}/quiz/attempts', [QuizAttemptController::class, 'start']);
    Route::post('/quiz-attempts/{attemptId}/submit', [QuizAttemptController::class, 'submit']);
    Route::get('/quiz-attempts/{attemptId}', [QuizAttemptController::class, 'show']);

    Route::get('/lessons/{lessonId}/assignment', [AssignmentController::class, 'show']);
    Route::post('/lessons/{lessonId}/assignment/submit', [AssignmentController::class, 'submit']);

    // Memberships
    Route::post('/membership-plans/{planId}/subscribe', [MembershipPlanController::class, 'subscribe']);
    Route::get('/my/membership', [MembershipPlanController::class, 'mySubscription']);
    Route::post('/my/membership/{subscriptionId}/cancel', [MembershipPlanController::class, 'cancel']);

    // Checkout (paid courses, memberships, coaching)
    Route::post('/checkout/courses/{courseId}', [CheckoutController::class, 'course']);
    Route::post('/checkout/membership-plans/{planId}', [CheckoutController::class, 'membership']);
    Route::post('/checkout/coaching-bookings/{bookingId}', [CheckoutController::class, 'coachingBooking']);
    Route::get('/checkout/orders/{orderId}', [CheckoutController::class, 'status']);
    Route::get('/my/orders', [OrderController::class, 'mine']);

    // Coaching bookings
    Route::post('/coaching-services/{serviceId}/book', [BookingController::class, 'bookOneToOne']);
    Route::post('/coaching-sessions/{sessionId}/book', [BookingController::class, 'bookIntoSession']);
    Route::get('/my/bookings', [BookingController::class, 'myBookings']);
    Route::post('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{bookingId}/reschedule', [BookingController::class, 'reschedule']);
});

/*
|--------------------------------------------------------------------------
| Learning — tenant admin / instructor management
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['tenant', 'auth:sanctum'])->group(function () {
    Route::middleware('permission:courses.create')->group(function () {
        Route::post('/course-categories', [CourseCategoryController::class, 'store']);
        Route::post('/courses', [CourseController::class, 'store']);
    });

    Route::middleware('permission:courses.update')->group(function () {
        Route::get('/courses', [CourseController::class, 'adminIndex']);
        Route::get('/courses/{courseId}', [CourseController::class, 'adminShow']);
        Route::put('/course-categories/{categoryId}', [CourseCategoryController::class, 'update']);
        Route::put('/courses/{courseId}', [CourseController::class, 'update']);
        Route::post('/courses/{courseId}/thumbnail', [CourseController::class, 'uploadThumbnail']);
        Route::post('/courses/{courseId}/instructors', [CourseController::class, 'attachInstructor']);
        Route::delete('/courses/{courseId}/instructors/{userId}', [CourseController::class, 'detachInstructor']);

        Route::post('/courses/{courseId}/modules', [CourseModuleController::class, 'store']);
        Route::get('/courses/{courseId}/modules', [CourseModuleController::class, 'index']);
        Route::put('/modules/{moduleId}', [CourseModuleController::class, 'update']);

        Route::post('/modules/{moduleId}/lessons', [LessonController::class, 'store']);
        Route::put('/lessons/{lessonId}', [LessonController::class, 'update']);
        Route::post('/lessons/{lessonId}/material', [LessonController::class, 'uploadMaterial']);

        Route::post('/lessons/{lessonId}/quiz', [QuizController::class, 'save']);
        Route::get('/lessons/{lessonId}/quiz', [QuizController::class, 'adminShow']);
        Route::post('/lessons/{lessonId}/assignment', [AssignmentController::class, 'save']);
    });

    Route::middleware('permission:courses.publish')->group(function () {
        Route::post('/courses/{courseId}/publish', [CourseController::class, 'publish']);
        Route::post('/courses/{courseId}/unpublish', [CourseController::class, 'unpublish']);
    });

    Route::middleware('permission:courses.delete')->group(function () {
        Route::delete('/course-categories/{categoryId}', [CourseCategoryController::class, 'destroy']);
        Route::delete('/courses/{courseId}', [CourseController::class, 'destroy']);
        Route::delete('/modules/{moduleId}', [CourseModuleController::class, 'destroy']);
        Route::delete('/lessons/{lessonId}', [LessonController::class, 'destroy']);
    });

    Route::middleware('permission:students.manage')->group(function () {
        Route::get('/courses/{courseId}/enrolments', [EnrolmentController::class, 'forCourse']);
        Route::get('/lessons/{lessonId}/assignment-submissions', [AssignmentController::class, 'submissions']);
        Route::post('/assignment-submissions/{submissionId}/grade', [AssignmentController::class, 'grade']);

        Route::get('/students', [AdminStudentController::class, 'index']);
        Route::delete('/students/{userId}', [AdminStudentController::class, 'destroy']);
        Route::post('/students/{userId}/reactivate', [AdminStudentController::class, 'reactivate']);
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/tenant-users', [TenantUserController::class, 'index']);
        Route::get('/roles', [TenantUserController::class, 'roles']);
        Route::put('/tenant-users/{userId}/role', [TenantUserController::class, 'updateRole']);
        Route::delete('/tenant-users/{userId}', [TenantUserController::class, 'destroy']);
        Route::post('/tenant-users/{userId}/reactivate', [TenantUserController::class, 'reactivate']);

        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::delete('/invitations/{invitationId}', [InvitationController::class, 'destroy']);
    });

    Route::middleware('permission:certificates.issue')->group(function () {
        Route::get('/certificates', [CertificateController::class, 'adminIndex']);
        Route::post('/enrolments/{enrolmentId}/issue-certificate', [CertificateController::class, 'issueManually']);
    });

    Route::middleware('permission:certificates.revoke')->group(function () {
        Route::post('/certificates/{certificateId}/revoke', [CertificateController::class, 'revoke']);
    });

    Route::middleware('permission:exports.request')->group(function () {
        Route::get('/exports', [ExportController::class, 'index']);
        Route::post('/exports', [ExportController::class, 'store']);
        Route::get('/exports/{exportJobId}/download', [ExportController::class, 'download']);
    });

    Route::middleware('permission:payment_gateway.manage')->group(function () {
        Route::get('/payment-config', [PaymentConfigController::class, 'show']);
        Route::put('/payment-config/client-owned', [PaymentConfigController::class, 'setClientOwned']);
        Route::put('/payment-config/managed', [PaymentConfigController::class, 'setManaged']);
        Route::post('/payment-config/test-connection', [PaymentConfigController::class, 'testConnection']);
    });

    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
    });

    Route::middleware('permission:payments.refund')->group(function () {
        Route::post('/payments/{paymentId}/refund', [RefundController::class, 'store']);
        Route::post('/orders/{orderId}/reconcile', [OrderController::class, 'reconcile']);
    });

    Route::middleware('permission:memberships.manage')->group(function () {
        Route::get('/membership-plans', [MembershipPlanController::class, 'adminIndex']);
        Route::post('/membership-plans', [MembershipPlanController::class, 'store']);
        Route::put('/membership-plans/{planId}', [MembershipPlanController::class, 'update']);
        Route::delete('/membership-plans/{planId}', [MembershipPlanController::class, 'destroy']);
    });

    Route::middleware('permission:coaching.manage')->group(function () {
        Route::get('/coaches', [CoachController::class, 'adminIndex']);
        Route::get('/coaches/{coachId}', [CoachController::class, 'adminShow']);
        Route::post('/coaches', [CoachController::class, 'store']);
        Route::put('/coaches/{coachId}', [CoachController::class, 'update']);

        Route::get('/coaches/{coachId}/availability', [AvailabilityController::class, 'index']);
        Route::post('/coaches/{coachId}/availability', [AvailabilityController::class, 'store']);
        Route::delete('/availability/{ruleId}', [AvailabilityController::class, 'destroy']);

        Route::post('/coaches/{coachId}/services', [CoachingServiceController::class, 'store']);
        Route::put('/coaching-services/{serviceId}', [CoachingServiceController::class, 'update']);
        Route::delete('/coaching-services/{serviceId}', [CoachingServiceController::class, 'destroy']);

        Route::post('/coaching-services/{serviceId}/sessions', [CoachingSessionController::class, 'store']);
        Route::put('/coaching-sessions/{sessionId}', [CoachingSessionController::class, 'update']);
        Route::get('/coaching-sessions/{sessionId}/roster', [BookingController::class, 'sessionRoster']);
        Route::post('/coaching-sessions/{sessionId}/attendance', [BookingController::class, 'markAttendance']);
    });

});

/*
|--------------------------------------------------------------------------
| Platform control plane (NAI TALK staff only) — never resolves a
| TenantContext. See ARCHITECTURE.md §3.
|--------------------------------------------------------------------------
*/
Route::prefix('platform')->group(function () {
    Route::post('/auth/login', [PlatformAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'platform'])->group(function () {
        Route::get('/auth/me', [PlatformAuthController::class, 'me']);
        Route::post('/auth/logout', [PlatformAuthController::class, 'logout']);

        Route::get('/tenants', [PlatformTenantController::class, 'index'])->middleware('permission:tenants.manage');
        Route::post('/tenants', [PlatformTenantController::class, 'store'])->middleware('permission:tenants.manage');
        Route::get('/tenants/{tenant}', [PlatformTenantController::class, 'show'])->middleware('permission:tenants.manage');
        Route::post('/tenants/{tenant}/suspend', [PlatformTenantController::class, 'suspend'])->middleware('permission:tenants.manage');
        Route::post('/tenants/{tenant}/reactivate', [PlatformTenantController::class, 'reactivate'])->middleware('permission:tenants.manage');
        Route::post('/tenants/{tenant}/schedule-deletion', [PlatformTenantController::class, 'scheduleDeletion'])->middleware('permission:deletions.manage');
        Route::post('/tenants/{tenant}/cancel-deletion', [PlatformTenantController::class, 'cancelDeletion'])->middleware('permission:deletions.manage');

        Route::get('/tenants/{tenant}/exports', [PlatformExportController::class, 'index'])->middleware('permission:exports.manage');
        Route::post('/tenants/{tenant}/exports', [PlatformExportController::class, 'store'])->middleware('permission:exports.manage');
        Route::get('/tenants/{tenant}/exports/{exportJobId}/download', [PlatformExportController::class, 'download'])->middleware('permission:exports.manage');
        Route::post('/tenants/import', [PlatformExportController::class, 'import'])->middleware('permission:exports.manage');

        Route::get('/plans', [PlatformPlanController::class, 'index'])->middleware('permission:plans.manage');
        Route::post('/plans', [PlatformPlanController::class, 'store'])->middleware('permission:plans.manage');
        Route::put('/plans/{plan}', [PlatformPlanController::class, 'update'])->middleware('permission:plans.manage');
        Route::put('/plans/{plan}/features', [PlatformPlanController::class, 'setFeature'])->middleware('permission:plans.manage');
        Route::delete('/plans/{plan}', [PlatformPlanController::class, 'destroy'])->middleware('permission:plans.manage');

        Route::get('/tenants/{tenant}/subscription', [PlatformSubscriptionController::class, 'show'])->middleware('permission:subscriptions.manage');
        Route::put('/tenants/{tenant}/subscription', [PlatformSubscriptionController::class, 'update'])->middleware('permission:subscriptions.manage');
        Route::post('/tenants/{tenant}/subscription/complimentary', [PlatformSubscriptionController::class, 'grantComplimentary'])->middleware('permission:complimentary.grant');

        Route::get('/domains', [PlatformDomainController::class, 'index'])->middleware('permission:domains.oversight');
        Route::get('/managed-payments', [PlatformManagedPaymentsController::class, 'index'])->middleware('permission:payments.manage');
    });
});
