<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Learning\Models\Assignment;
use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseCategory;
use App\Domain\Learning\Models\CourseModule;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Lesson;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Quiz;
use App\Domain\Learning\Models\QuizOption;
use App\Domain\Learning\Models\QuizQuestion;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantBranding;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Restores an export produced by TenantExportService into a brand-new
 * tenant — see ARCHITECTURE.md §12 for why this never merges into an
 * existing tenant, and why it restores identity + branding + the full
 * learning domain (content, enrolments, progress, certificates) but not
 * commerce/coaching/community, whose polymorphic foreign keys make a
 * correct first-pass restore a meaningfully larger undertaking.
 *
 * Runs inside one DB transaction — a mid-import failure leaves nothing
 * behind. Every *_id foreign key in the export refers to the *old* tenant's
 * auto-increment ids, so each import step keeps an old-id => new-id map and
 * rewrites references as it goes, in dependency order (categories before
 * courses before modules before lessons before quizzes... before
 * enrolments before progress).
 */
class TenantImportService
{
    public function __construct(private TenantProvisioningService $provisioning) {}

    public function importIntoNewTenant(array $payload, string $newTenantName): Tenant
    {
        return DB::transaction(function () use ($payload, $newTenantName) {
            $tenant = $this->provisioning->provision(name: $newTenantName);
            app(TenantContext::class)->set($tenant);

            $userIdMap = $this->importUsers($payload['users'] ?? []);
            $roleIdMap = $this->mapRoles($payload['roles'] ?? []);
            $this->importTenantUsers($tenant, $payload['tenant_users'] ?? [], $userIdMap, $roleIdMap);
            $this->importBranding($payload['branding'] ?? null);

            $categoryIdMap = $this->importCategories($payload['course_categories'] ?? []);
            $courseIdMap = $this->importCourses($payload['courses'] ?? [], $categoryIdMap);
            $moduleIdMap = $this->importModules($payload['course_modules'] ?? [], $courseIdMap);
            $lessonIdMap = $this->importLessons($payload['lessons'] ?? [], $moduleIdMap);
            $quizIdMap = $this->importQuizzes($payload['quizzes'] ?? [], $lessonIdMap);
            $questionIdMap = $this->importQuizQuestions($payload['quiz_questions'] ?? [], $quizIdMap);
            $this->importQuizOptions($payload['quiz_options'] ?? [], $questionIdMap);
            $this->importAssignments($payload['assignments'] ?? [], $lessonIdMap);

            $enrolmentIdMap = $this->importEnrolments($payload['enrolments'] ?? [], $userIdMap, $courseIdMap);
            $this->importLessonProgress($payload['lesson_progress'] ?? [], $enrolmentIdMap, $lessonIdMap);
            $this->importCertificates($payload['certificates'] ?? [], $userIdMap, $courseIdMap, $enrolmentIdMap);

            app(TenantContext::class)->clear();

            return $tenant->fresh();
        });
    }

    /** @return array<int,int> old user id => new (possibly reused) user id */
    private function importUsers(array $users): array
    {
        $map = [];

        foreach ($users as $row) {
            // The users table is global (one physical person can belong to
            // several tenants) — an email match means this is genuinely the
            // same person and must be reused, not duplicated.
            $user = User::firstOrCreate(
                ['email' => $row['email']],
                ['name' => $row['name'], 'password' => Str::password(24), 'public_id' => (string) Str::uuid()]
            );

            $map[$row['id']] = $user->id;
        }

        return $map;
    }

    /** @return array<int,int> old role id => new role id, matched by slug
     * against the 8 standard roles TenantProvisioningService already
     * created for the new tenant — never re-created from the export. */
    private function mapRoles(array $roles): array
    {
        $newRolesBySlug = Role::pluck('id', 'slug');
        $map = [];

        foreach ($roles as $row) {
            if (isset($newRolesBySlug[$row['slug']])) {
                $map[$row['id']] = $newRolesBySlug[$row['slug']];
            }
        }

        return $map;
    }

    private function importTenantUsers(Tenant $tenant, array $tenantUsers, array $userIdMap, array $roleIdMap): void
    {
        foreach ($tenantUsers as $row) {
            if (! isset($userIdMap[$row['user_id']], $roleIdMap[$row['role_id']])) {
                continue;
            }

            $created = TenantUser::create([
                'user_id' => $userIdMap[$row['user_id']],
                'role_id' => $roleIdMap[$row['role_id']],
                'status' => $row['status'],
                'invited_by' => isset($row['invited_by']) ? ($userIdMap[$row['invited_by']] ?? null) : null,
                'joined_at' => $row['joined_at'],
            ]);

            $newRole = Role::find($roleIdMap[$row['role_id']]);
            if ($newRole?->slug === 'tenant-owner' && ! $tenant->owner_user_id) {
                $tenant->update(['owner_user_id' => $created->user_id]);
            }
        }
    }

    private function importBranding(?array $branding): void
    {
        if (! $branding) {
            return;
        }

        TenantBranding::first()?->update(collect($branding)
            ->only([
                'logo_path', 'favicon_path', 'primary_color', 'secondary_color', 'accent_color',
                'font_family', 'homepage_json', 'contact_json', 'social_json', 'email_sender_name',
                'pwa_name', 'pwa_theme_color', 'pwa_icon_path',
            ])
            ->all());
    }

    /** @return array<int,int> */
    private function importCategories(array $categories): array
    {
        $map = [];

        foreach ($categories as $row) {
            $created = CourseCategory::create(['name' => $row['name'], 'slug' => $row['slug']]);
            $map[$row['id']] = $created->id;
        }

        return $map;
    }

    /** @return array<int,int> */
    private function importCourses(array $courses, array $categoryIdMap): array
    {
        $map = [];

        foreach ($courses as $row) {
            $created = Course::create([
                'category_id' => isset($row['category_id']) ? ($categoryIdMap[$row['category_id']] ?? null) : null,
                'title' => $row['title'],
                'slug' => $row['slug'],
                'excerpt' => $row['excerpt'],
                'description' => $row['description'],
                'thumbnail_path' => $row['thumbnail_path'],
                'promo_video_path' => $row['promo_video_path'],
                'status' => $row['status'],
                'pricing_type' => $row['pricing_type'],
                'price_cents' => $row['price_cents'],
                'currency' => $row['currency'],
                'difficulty_level' => $row['difficulty_level'],
                'tags' => $row['tags'],
                // Cross-course references use the *old* tenant's course ids
                // and would need a second remap pass once every course is
                // known — dropped rather than left silently wrong; the
                // course content itself is unaffected.
                'prerequisite_course_ids' => null,
                'drip_type' => $row['drip_type'],
                'certificate_enabled' => $row['certificate_enabled'],
                'published_at' => $row['published_at'],
            ]);

            if (! empty($row['deleted_at'])) {
                $created->forceFill(['deleted_at' => $row['deleted_at']])->save();
            }

            $map[$row['id']] = $created->id;
        }

        return $map;
    }

    /** @return array<int,int> */
    private function importModules(array $modules, array $courseIdMap): array
    {
        $map = [];

        foreach ($modules as $row) {
            if (! isset($courseIdMap[$row['course_id']])) {
                continue;
            }

            $created = CourseModule::create([
                'course_id' => $courseIdMap[$row['course_id']],
                'title' => $row['title'],
                'sort_order' => $row['sort_order'],
            ]);
            $map[$row['id']] = $created->id;
        }

        return $map;
    }

    /** @return array<int,int> */
    private function importLessons(array $lessons, array $moduleIdMap): array
    {
        $map = [];

        foreach ($lessons as $row) {
            if (! isset($moduleIdMap[$row['course_module_id']])) {
                continue;
            }

            $created = Lesson::create([
                'course_module_id' => $moduleIdMap[$row['course_module_id']],
                'title' => $row['title'],
                'type' => $row['type'],
                'content' => $row['content'],
                'video_path' => $row['video_path'],
                'duration_seconds' => $row['duration_seconds'],
                'is_preview' => $row['is_preview'],
                'is_mandatory' => $row['is_mandatory'],
                'available_after_days' => $row['available_after_days'],
                'sort_order' => $row['sort_order'],
            ]);

            if (! empty($row['deleted_at'])) {
                $created->forceFill(['deleted_at' => $row['deleted_at']])->save();
            }

            $map[$row['id']] = $created->id;
        }

        return $map;
    }

    /** @return array<int,int> */
    private function importQuizzes(array $quizzes, array $lessonIdMap): array
    {
        $map = [];

        foreach ($quizzes as $row) {
            if (! isset($lessonIdMap[$row['lesson_id']])) {
                continue;
            }

            $created = Quiz::create([
                'lesson_id' => $lessonIdMap[$row['lesson_id']],
                'passing_score_percent' => $row['passing_score_percent'],
                'max_attempts' => $row['max_attempts'],
                'time_limit_minutes' => $row['time_limit_minutes'],
                'randomize_questions' => $row['randomize_questions'],
            ]);
            $map[$row['id']] = $created->id;
        }

        return $map;
    }

    /** @return array<int,int> */
    private function importQuizQuestions(array $questions, array $quizIdMap): array
    {
        $map = [];

        foreach ($questions as $row) {
            if (! isset($quizIdMap[$row['quiz_id']])) {
                continue;
            }

            $created = QuizQuestion::create([
                'quiz_id' => $quizIdMap[$row['quiz_id']],
                'type' => $row['type'],
                'question_text' => $row['question_text'],
                'points' => $row['points'],
                'sort_order' => $row['sort_order'],
            ]);
            $map[$row['id']] = $created->id;
        }

        return $map;
    }

    private function importQuizOptions(array $options, array $questionIdMap): void
    {
        foreach ($options as $row) {
            if (! isset($questionIdMap[$row['quiz_question_id']])) {
                continue;
            }

            QuizOption::create([
                'quiz_question_id' => $questionIdMap[$row['quiz_question_id']],
                'option_text' => $row['option_text'],
                'is_correct' => $row['is_correct'],
                'sort_order' => $row['sort_order'],
            ]);
        }
    }

    private function importAssignments(array $assignments, array $lessonIdMap): void
    {
        foreach ($assignments as $row) {
            if (! isset($lessonIdMap[$row['lesson_id']])) {
                continue;
            }

            Assignment::create([
                'lesson_id' => $lessonIdMap[$row['lesson_id']],
                'title' => $row['title'],
                'instructions' => $row['instructions'],
                'max_points' => $row['max_points'],
                'due_date' => $row['due_date'],
            ]);
        }
    }

    /** @return array<int,int> */
    private function importEnrolments(array $enrolments, array $userIdMap, array $courseIdMap): array
    {
        $map = [];

        foreach ($enrolments as $row) {
            if (! isset($userIdMap[$row['user_id']], $courseIdMap[$row['course_id']])) {
                continue;
            }

            $created = Enrolment::create([
                'user_id' => $userIdMap[$row['user_id']],
                'course_id' => $courseIdMap[$row['course_id']],
                'status' => $row['status'],
                'source' => $row['source'],
                'enrolled_at' => $row['enrolled_at'],
                'completed_at' => $row['completed_at'],
            ]);
            $map[$row['id']] = $created->id;
        }

        return $map;
    }

    private function importLessonProgress(array $progress, array $enrolmentIdMap, array $lessonIdMap): void
    {
        foreach ($progress as $row) {
            if (! isset($enrolmentIdMap[$row['enrolment_id']], $lessonIdMap[$row['lesson_id']])) {
                continue;
            }

            LessonProgress::create([
                'enrolment_id' => $enrolmentIdMap[$row['enrolment_id']],
                'lesson_id' => $lessonIdMap[$row['lesson_id']],
                'status' => $row['status'],
                'video_position_seconds' => $row['video_position_seconds'],
                'completed_at' => $row['completed_at'],
            ]);
        }
    }

    private function importCertificates(array $certificates, array $userIdMap, array $courseIdMap, array $enrolmentIdMap): void
    {
        $tenant = app(TenantContext::class)->tenant();
        $issuedThisYear = [];

        foreach ($certificates as $row) {
            if (! isset($userIdMap[$row['user_id']])) {
                continue;
            }

            // Both regenerated rather than copied verbatim: certificate_number
            // is a per-tenant sequence prefixed with the tenant's own slug, and
            // verification_code must stay globally unique across every tenant
            // that has ever existed — the new tenant has a different slug and
            // needs its own sequence, not a copy of the old one's.
            $year = Carbon::parse($row['issued_at'])->year;
            $issuedThisYear[$year] = ($issuedThisYear[$year] ?? 0) + 1;

            Certificate::create([
                'user_id' => $userIdMap[$row['user_id']],
                'course_id' => isset($row['course_id']) ? ($courseIdMap[$row['course_id']] ?? null) : null,
                'enrolment_id' => isset($row['enrolment_id']) ? ($enrolmentIdMap[$row['enrolment_id']] ?? null) : null,
                'certificate_number' => sprintf('%s-%d-%04d', strtoupper($tenant->slug), $year, $issuedThisYear[$year]),
                'verification_code' => (string) Str::uuid(),
                'recipient_name' => $row['recipient_name'],
                'course_title' => $row['course_title'],
                'completed_at' => $row['completed_at'],
                'issued_at' => $row['issued_at'],
                'revoked_at' => $row['revoked_at'],
                'revoked_reason' => $row['revoked_reason'],
                'metadata' => $row['metadata'],
            ]);
        }
    }
}
