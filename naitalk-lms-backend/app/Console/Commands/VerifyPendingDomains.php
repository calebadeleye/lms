<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\Models\TenantDomain;
use App\Domain\Tenancy\Services\DomainVerificationService;
use Illuminate\Console\Command;

class VerifyPendingDomains extends Command
{
    protected $signature = 'domains:verify-pending';

    protected $description = 'Attempt DNS verification for all pending custom domains.';

    public function handle(DomainVerificationService $verifier): int
    {
        $pending = TenantDomain::withoutTenancy(fn () => TenantDomain::query()
            ->where('verification_status', 'pending')
            ->whereIn('domain_type', ['custom_subdomain', 'custom_domain'])
            ->get()
        );

        $verified = 0;

        foreach ($pending as $domain) {
            if ($verifier->attemptVerify($domain)) {
                $verified++;
                $this->line("Verified: {$domain->hostname}");
            }
        }

        $this->info("Checked {$pending->count()} pending domains, verified {$verified}.");

        return self::SUCCESS;
    }
}
