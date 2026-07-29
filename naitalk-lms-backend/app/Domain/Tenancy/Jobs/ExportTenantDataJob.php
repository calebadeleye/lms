<?php

namespace App\Domain\Tenancy\Jobs;

use App\Domain\Tenancy\Concerns\TenantAwareJob;
use App\Domain\Tenancy\Models\ExportJob;
use App\Domain\Tenancy\Services\TenantExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportTenantDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAwareJob;

    public function __construct(
        public string $tenantId,
        public int $exportJobId,
    ) {}

    public function handle(TenantExportService $exporter): void
    {
        $tenant = $this->establishTenantContext();
        $exportJob = ExportJob::findOrFail($this->exportJobId);

        $exportJob->update(['status' => 'processing']);

        try {
            $payload = $exporter->export($tenant);
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

            $path = "{$tenant->id}/exports/".now()->format('Y-m-d_His').'.json';
            Storage::disk('tenants')->put($path, $json);

            $exportJob->update([
                'status' => 'completed',
                'file_path' => $path,
                'file_size_bytes' => strlen($json),
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $exportJob->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            throw $e;
        }
    }
}
