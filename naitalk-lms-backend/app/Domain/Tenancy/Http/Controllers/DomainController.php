<?php

namespace App\Domain\Tenancy\Http\Controllers;

use App\Domain\Tenancy\Models\TenantDomain;
use App\Domain\Tenancy\Services\DomainVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DomainController extends Controller
{
    public function __construct(private DomainVerificationService $verifier) {}

    public function index()
    {
        return response()->json(['data' => TenantDomain::orderByDesc('is_primary')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255'],
        ]);

        $hostname = strtolower(trim($data['hostname']));

        // Never allow a tenant to claim the neutral platform domain itself
        // or another tenant's already-verified hostname.
        if (TenantDomain::withoutTenancy(fn () => TenantDomain::where('hostname', $hostname)->exists())) {
            throw ValidationException::withMessages(['hostname' => ['This domain is already in use.']]);
        }

        $domain = TenantDomain::create([
            'hostname' => $hostname,
            'domain_type' => str_ends_with($hostname, config('services.frontend.neutral_platform_domain'))
                ? 'custom_subdomain'
                : 'custom_domain',
            'verification_token' => 'naitalk-verify='.Str::random(32),
            'verification_status' => 'pending',
            'ssl_status' => 'pending',
            'is_primary' => false,
        ]);

        return response()->json(['data' => $domain], 201);
    }

    /**
     * These three actions deliberately take a raw id and call
     * TenantDomain::findOrFail() rather than using implicit route-model
     * binding. Binding resolution can run before the `tenant` middleware in
     * the pipeline depending on framework middleware-priority ordering
     * (SubstituteBindings isn't guaranteed to run after custom-aliased
     * middleware), which would silently look up the model without the
     * tenant global scope applied. An explicit lookup here always runs
     * inside the controller, strictly after every route middleware
     * (including `tenant`) has executed — correctness can't depend on
     * pipeline ordering.
     */
    public function verify(string $domainId)
    {
        $domain = TenantDomain::findOrFail($domainId);
        $verified = $this->verifier->attemptVerify($domain);

        return response()->json(['data' => $domain->fresh()], $verified ? 200 : 422);
    }

    public function makePrimary(string $domainId)
    {
        $domain = TenantDomain::findOrFail($domainId);

        if (! $domain->isVerified()) {
            throw ValidationException::withMessages(['hostname' => ['Only a verified domain can be made primary.']]);
        }

        TenantDomain::where('id', '!=', $domain->id)->update(['is_primary' => false]);
        $domain->update(['is_primary' => true]);

        return response()->json(['data' => $domain->fresh()]);
    }

    public function destroy(string $domainId)
    {
        $domain = TenantDomain::findOrFail($domainId);

        if ($domain->is_primary) {
            throw ValidationException::withMessages(['hostname' => ['Cannot remove the primary domain. Assign another domain as primary first.']]);
        }

        $domain->delete();

        return response()->json(['data' => ['success' => true]]);
    }
}
