<?php

namespace App\Domain\Tenancy\Http\Controllers;

use App\Domain\Tenancy\Models\TenantBranding;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class BrandingController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function show()
    {
        $branding = TenantBranding::firstOrCreate([]);

        return response()->json(['data' => $branding]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'favicon_path' => ['nullable', 'string', 'max:2048'],
            'hero_image_path' => ['nullable', 'string', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'max:9'],
            'secondary_color' => ['nullable', 'string', 'max:9'],
            'accent_color' => ['nullable', 'string', 'max:9'],
            'font_family' => ['nullable', 'string', 'max:255'],
            'homepage_json' => ['nullable', 'array'],
            'contact_json' => ['nullable', 'array'],
            'social_json' => ['nullable', 'array'],
            'email_sender_name' => ['nullable', 'string', 'max:255'],
            'pwa_name' => ['nullable', 'string', 'max:255'],
            'pwa_theme_color' => ['nullable', 'string', 'max:9'],
            'pwa_icon_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $branding = TenantBranding::firstOrCreate([]);
        $branding->update($data);

        Cache::forget("tenant:{$this->tenantContext->id()}:public-config");

        return response()->json(['data' => $branding]);
    }

    /**
     * Logo/favicon/hero image are the only tenant-owned files that must be
     * reachable by a logged-out browser (a homepage visitor, a browser tab
     * icon) — everything else on the `tenants` disk (exports, certificates)
     * is deliberately private. Stored under a fixed `{tenant}/branding/`
     * filename (never the visitor's original filename) so there's exactly
     * one live logo/favicon/hero image per tenant and no path-traversal
     * surface from user input; TenantAssetController is the only thing that
     * ever reads from that specific subdirectory publicly.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate(['file' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048']]);

        return $this->storeAsset($request->file('file'), 'logo_path', 'logo');
    }

    public function uploadFavicon(Request $request)
    {
        $request->validate(['file' => ['required', 'mimes:png,ico,jpg,jpeg', 'max:512']]);

        return $this->storeAsset($request->file('file'), 'favicon_path', 'favicon');
    }

    public function uploadHeroImage(Request $request)
    {
        $request->validate(['file' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096']]);

        return $this->storeAsset($request->file('file'), 'hero_image_path', 'hero');
    }

    private function storeAsset(UploadedFile $file, string $column, string $baseName)
    {
        $tenant = $this->tenantContext->tenant();
        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $directory = "{$tenant->id}/branding";

        $file->storeAs($directory, "{$baseName}.{$extension}", ['disk' => 'tenants']);

        $branding = TenantBranding::firstOrCreate([]);
        $branding->update([$column => "{$directory}/{$baseName}.{$extension}"]);

        Cache::forget("tenant:{$tenant->id}:public-config");

        return response()->json(['data' => $branding->fresh()]);
    }
}
