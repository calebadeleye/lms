<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Tenancy\Models\TenantDomain;

/**
 * DNS TXT-record verification for custom subdomains/domains. Tenants add a
 * TXT record `_naitalk-verify.{hostname}` with the value returned by
 * `store()` (verification_token). Real SSL provisioning (Cloudflare for
 * SaaS, ACM, etc.) is infra-specific and documented as a deployment step,
 * not application logic — this service only owns the verification_status
 * transition; ssl_status is updated by that external hook calling back in.
 */
class DomainVerificationService
{
    public function attemptVerify(TenantDomain $domain): bool
    {
        $recordName = '_naitalk-verify.'.$domain->hostname;

        try {
            $records = dns_get_record($recordName, DNS_TXT);
        } catch (\Throwable $e) {
            $domain->update([
                'verification_status' => 'failed',
                'last_verification_error' => $e->getMessage(),
            ]);

            return false;
        }

        $found = collect($records)->contains(
            fn ($record) => trim($record['txt'] ?? '') === $domain->verification_token
        );

        if (! $found) {
            $domain->update([
                'verification_status' => 'failed',
                'last_verification_error' => "TXT record {$recordName} not found or does not match.",
            ]);

            return false;
        }

        $domain->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'last_verification_error' => null,
        ]);

        return true;
    }
}
