<?php

namespace App\Console\Commands;

use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Tenancy\Models\ExportJob;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * The consuming side of Phase 1's `deletion_scheduled_at` column — nothing
 * ever read it until now. Refuses to hard-delete a tenant with zero
 * completed export_jobs rather than silently skipping the safety net the
 * whole grace period exists for: if offboarding never ran an export, this
 * command logs an error and leaves the tenant alone rather than deleting
 * data nobody backed up. Once an export exists, every Phase 1-3 migration's
 * `tenant_id` foreign key already declares `->cascadeOnDelete()`, so a
 * single `forceDelete()` (bypassing the soft-delete) cascades through all
 * ~50 tenant-owned tables at the database level.
 */
class ProcessScheduledTenantDeletions extends Command
{
    protected $signature = 'tenants:process-scheduled-deletions';

    protected $description = 'Permanently delete tenants whose grace period has passed and which have a completed data export.';

    public function handle(AuditLogger $auditLogger): int
    {
        $due = Tenant::where('status', 'deletion_scheduled')
            ->where('deletion_scheduled_at', '<=', now())
            ->get();

        $deleted = 0;
        $skipped = 0;

        foreach ($due as $tenant) {
            $hasExport = ExportJob::withoutTenancy(fn () => ExportJob::where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->exists()
            );

            if (! $hasExport) {
                $this->error("Tenant {$tenant->id} ({$tenant->name}): deletion due but no completed export exists — skipping.");
                $skipped++;

                continue;
            }

            $tenantId = $tenant->id;
            $tenantName = $tenant->name;

            // Logged *before* deleting: audit_logs.tenant_id has an
            // ON DELETE SET NULL foreign key, which only applies to
            // existing rows — inserting a new row referencing a tenant_id
            // that's already gone would violate the constraint outright.
            // Logging first means the cascade nulls this row's tenant_id
            // out afterward, same as any other audit entry for a deleted
            // tenant, while the name survives in metadata either way.
            $auditLogger->log('tenant.deleted_permanently', tenantId: $tenantId, metadata: ['name' => $tenantName]);

            $tenant->forceDelete();

            $this->line("Tenant {$tenantId} ({$tenantName}): permanently deleted.");
            $deleted++;
        }

        $this->info("Deleted {$deleted} tenant(s), skipped {$skipped} (no completed export).");

        return self::SUCCESS;
    }
}
