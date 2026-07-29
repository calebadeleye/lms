<?php

use App\Domain\Tenancy\Models\ExportJob;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('lets platform staff schedule and then cancel a tenant deletion', function () {
    $tenant = app(TenantProvisioningService::class)->provision(name: 'Offboarding Co');
    $token = makePlatformStaffToken();
    $headers = ['Authorization' => "Bearer {$token}"];

    $schedule = $this->postJson("/api/v1/platform/tenants/{$tenant->id}/schedule-deletion", [
        'retention_days' => 14,
    ], $headers)->assertOk();

    expect($schedule->json('data.status'))->toBe('deletion_scheduled');
    expect($schedule->json('data.deletion_scheduled_at'))->not->toBeNull();

    $cancel = $this->postJson("/api/v1/platform/tenants/{$tenant->id}/cancel-deletion", [], $headers)->assertOk();

    expect($cancel->json('data.status'))->toBe('active');
    expect($cancel->json('data.deletion_scheduled_at'))->toBeNull();
});

it('refuses to permanently delete a due tenant that has no completed export', function () {
    $tenant = app(TenantProvisioningService::class)->provision(name: 'No Export Co');
    $tenant->update(['status' => 'deletion_scheduled', 'deletion_scheduled_at' => now()->subDay()]);

    $this->artisan('tenants:process-scheduled-deletions')
        ->expectsOutputToContain('no completed export exists — skipping')
        ->assertExitCode(0);

    expect(Tenant::find($tenant->id))->not->toBeNull();
    expect(Tenant::find($tenant->id)->status)->toBe('deletion_scheduled');
});

it('permanently deletes a due tenant with a completed export, cascading its data', function () {
    $tenant = app(TenantProvisioningService::class)->provision(name: 'Ready To Delete Co');

    app(TenantContext::class)->set($tenant);
    $course = \App\Domain\Learning\Models\Course::create([
        'title' => 'Doomed Course', 'slug' => 'doomed-course', 'status' => 'published',
        'pricing_type' => 'free', 'published_at' => now(),
    ]);
    ExportJob::create(['status' => 'completed', 'file_path' => 'whatever.json']);
    app(TenantContext::class)->clear();

    $tenant->update(['status' => 'deletion_scheduled', 'deletion_scheduled_at' => now()->subDay()]);

    $this->artisan('tenants:process-scheduled-deletions')
        ->expectsOutputToContain('permanently deleted')
        ->assertExitCode(0);

    expect(Tenant::withTrashed()->find($tenant->id))->toBeNull();

    // Cascades at the DB level — the course row is gone too, not just the
    // tenant row.
    $orphanedCourse = \App\Domain\Learning\Models\Course::withoutTenancy(
        fn () => \App\Domain\Learning\Models\Course::find($course->id)
    );
    expect($orphanedCourse)->toBeNull();
});

it('does not touch a tenant whose grace period has not passed yet', function () {
    $tenant = app(TenantProvisioningService::class)->provision(name: 'Not Due Yet Co');
    $tenant->update(['status' => 'deletion_scheduled', 'deletion_scheduled_at' => now()->addDays(10)]);

    $this->artisan('tenants:process-scheduled-deletions')->assertExitCode(0);

    expect(Tenant::find($tenant->id))->not->toBeNull();
});

it('leaves an active tenant alone even if it somehow has a deletion_scheduled_at in the past', function () {
    $tenant = app(TenantProvisioningService::class)->provision(name: 'Active Co');
    $tenant->update(['deletion_scheduled_at' => now()->subDay()]); // status stays 'active'

    $this->artisan('tenants:process-scheduled-deletions')->assertExitCode(0);

    expect(Tenant::find($tenant->id))->not->toBeNull();
});
