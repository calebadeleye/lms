<?php

namespace App\Domain\Tenancy\Http\Controllers;

use App\Domain\Tenancy\Jobs\ExportTenantDataJob;
use App\Domain\Tenancy\Models\ExportJob;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Tenant-scoped data export — `exports.request` lets a tenant request and
 * download their own data anytime, independent of offboarding. See
 * PlatformExportController for the platform-staff equivalent that can act
 * on any tenant (what offboarding actually uses).
 */
class ExportController extends Controller
{
    public function index()
    {
        $exports = ExportJob::orderByDesc('created_at')->get();

        return response()->json(['data' => $exports]);
    }

    public function store(Request $request)
    {
        $tenant = app(TenantContext::class)->tenant();

        $exportJob = ExportJob::create(['requested_by' => $request->user()->id, 'status' => 'pending']);

        ExportTenantDataJob::dispatch($tenant->id, $exportJob->id);

        return response()->json(['data' => $exportJob], 201);
    }

    public function download(string $exportJobId)
    {
        $exportJob = ExportJob::where('status', 'completed')->findOrFail($exportJobId);

        return Storage::disk('tenants')->download($exportJob->file_path, "export-{$exportJob->id}.json");
    }
}
