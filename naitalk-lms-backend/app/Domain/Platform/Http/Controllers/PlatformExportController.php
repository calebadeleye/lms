<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Tenancy\Jobs\ExportTenantDataJob;
use App\Domain\Tenancy\Models\ExportJob;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantImportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Platform-staff equivalent of ExportController — can trigger/list/download
 * *any* tenant's exports (never resolves a TenantContext, so every query
 * here goes through `withoutTenancy()`), and owns the only route that can
 * import an export into a brand-new tenant. See ARCHITECTURE.md §12.
 */
class PlatformExportController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(Tenant $tenant)
    {
        $exports = ExportJob::withoutTenancy(fn () => ExportJob::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get()
        );

        return response()->json(['data' => $exports]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $exportJob = ExportJob::withoutTenancy(fn () => ExportJob::create([
            'tenant_id' => $tenant->id,
            'requested_by' => $request->user()->id,
            'status' => 'pending',
        ]));

        ExportTenantDataJob::dispatch($tenant->id, $exportJob->id);

        $this->auditLogger->log('tenant.export_requested', tenantId: $tenant->id, metadata: ['export_job_id' => $exportJob->id]);

        return response()->json(['data' => $exportJob], 201);
    }

    public function download(Tenant $tenant, string $exportJobId)
    {
        $exportJob = ExportJob::withoutTenancy(fn () => ExportJob::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->findOrFail($exportJobId)
        );

        return Storage::disk('tenants')->download($exportJob->file_path, "{$tenant->slug}-export-{$exportJob->id}.json");
    }

    /**
     * Restores an export into a brand-new tenant — never the tenant it was
     * exported from, never merged into an existing tenant. See
     * TenantImportService's docblock for the full reasoning and for exactly
     * which domains this actually restores.
     */
    public function import(Request $request, TenantImportService $importer)
    {
        $data = $request->validate([
            'new_tenant_name' => ['required', 'string', 'max:255'],
            'payload' => ['required', 'array'],
        ]);

        try {
            $tenant = $importer->importIntoNewTenant($data['payload'], $data['new_tenant_name']);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['payload' => ['Import failed: '.$e->getMessage()]]);
        }

        $this->auditLogger->log('tenant.imported', tenantId: $tenant->id, metadata: ['name' => $tenant->name]);

        return response()->json(['data' => $tenant->load('domains')], 201);
    }
}
