<?php

namespace App\Domain\Tenancy\Http\Controllers;

use App\Domain\Tenancy\Models\TenantTestimonial;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Public, cached-by-hostname endpoint the Next.js root layout calls on every
 * request to build CSS variables + PWA manifest. No auth required — this is
 * exactly the data a logged-out visitor to the tenant's homepage needs.
 */
class TenantConfigController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function show(Request $request)
    {
        $tenant = $this->tenantContext->tenant();

        $config = Cache::remember("tenant:{$tenant->id}:public-config", now()->addMinutes(10), function () use ($tenant) {
            $tenant->loadMissing('branding', 'domains');

            return [
                'tenant' => [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                ],
                'branding' => $tenant->branding ? [
                    'logo_url' => $this->assetUrl($tenant->id, $tenant->branding->logo_path),
                    'favicon_url' => $this->assetUrl($tenant->id, $tenant->branding->favicon_path),
                    'hero_image_url' => $this->assetUrl($tenant->id, $tenant->branding->hero_image_path),
                    'primary_color' => $tenant->branding->primary_color,
                    'secondary_color' => $tenant->branding->secondary_color,
                    'accent_color' => $tenant->branding->accent_color,
                    'font_family' => $tenant->branding->font_family,
                    'homepage' => $tenant->branding->homepage_json,
                    'contact' => $tenant->branding->contact_json,
                    'social' => $tenant->branding->social_json,
                    'email_sender_name' => $tenant->branding->email_sender_name,
                    'pwa' => [
                        'name' => $tenant->branding->pwa_name ?? $tenant->name,
                        'theme_color' => $tenant->branding->pwa_theme_color ?? $tenant->branding->primary_color,
                        'icon_url' => $tenant->branding->pwa_icon_path,
                    ],
                ] : null,
                'domain' => [
                    'primary_hostname' => $tenant->domains->firstWhere('is_primary', true)?->hostname,
                ],
                'testimonials' => TenantTestimonial::orderBy('created_at')
                    ->get(['id', 'quote', 'author']),
            ];
        });

        return response()->json(['data' => $config]);
    }

    /**
     * Stored paths are `{tenant}/branding/{logo|favicon}.{ext}` on the
     * private `tenants` disk — not browser-loadable directly. Build the
     * TenantAssetController path. Deliberately root-relative rather than
     * absolute: every other backend call the browser makes goes through the
     * Next.js app's own origin (either a Server Component fetching directly
     * via BACKEND_SERVER_URL, or the client-side `/api/v1/[...path]` proxy)
     * — the browser never talks to the backend's own host directly, so an
     * absolute URL built from `url()` would embed whatever host issued the
     * request that filled this (10-minute) cache, e.g. the BFF's internal
     * 127.0.0.1 address, and be unreachable from the visitor's browser.
     *
     * The `?v=` suffix cache-busts TenantAssetController's `max-age=3600`
     * response header: logo/favicon always live at the exact same filename,
     * so without a version marker a browser that already loaded the old
     * file keeps serving it from its own HTTP cache for up to an hour after
     * a re-upload.
     */
    private function assetUrl(string $tenantId, ?string $storedPath): ?string
    {
        if (! $storedPath) {
            return null;
        }

        $path = "/api/v1/tenant-assets/branding/{$tenantId}/".basename($storedPath);
        $version = Storage::disk('tenants')->lastModified($storedPath);

        return $version ? "{$path}?v={$version}" : $path;
    }
}
