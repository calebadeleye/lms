<?php

use App\Domain\Commerce\Models\TenantPaymentConfig;
use App\Domain\Tenancy\Models\TenantDomain;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenantA = app(TenantProvisioningService::class)->provision(name: 'Oversight Co A');
    $this->tenantB = app(TenantProvisioningService::class)->provision(name: 'Oversight Co B');
});

it('lists domains across every tenant for platform staff with domains.oversight', function () {
    app(TenantContext::class)->set($this->tenantA);
    TenantDomain::create([
        'hostname' => 'custom-a.example.com', 'domain_type' => 'custom_domain',
        'verification_status' => 'verified', 'ssl_status' => 'active', 'is_primary' => false,
    ]);
    app(TenantContext::class)->clear();

    // provision() already created each tenant's default subdomain, so both
    // tenants have at least one domain even without this extra one.
    $token = makePlatformStaffToken('platform-operations');

    $response = $this->getJson('/api/v1/platform/domains', ['Authorization' => "Bearer {$token}"])->assertOk();

    $hostnames = collect($response->json('data'))->pluck('hostname');
    expect($hostnames)->toContain('custom-a.example.com');

    // Cross-tenant visibility is the entire point of "oversight" — unlike
    // every tenant-scoped endpoint elsewhere, this one deliberately spans
    // both tenants in a single response.
    $tenantNames = collect($response->json('data'))->pluck('tenant.name')->unique();
    expect($tenantNames)->toContain('Oversight Co A', 'Oversight Co B');
});

it('refuses domain oversight without the domains.oversight permission', function () {
    $token = makePlatformStaffToken('platform-finance');

    $this->getJson('/api/v1/platform/domains', ['Authorization' => "Bearer {$token}"])->assertStatus(403);
});

it('lists only managed-gateway tenants for platform staff with payments.manage', function () {
    app(TenantContext::class)->set($this->tenantA);
    $managed = TenantPaymentConfig::create([
        'provider' => 'paystack', 'mode' => 'managed', 'fee_bearer' => 'tenant', 'subaccount_code' => 'ACCT_managed',
    ]);
    app(TenantContext::class)->clear();

    app(TenantContext::class)->set($this->tenantB);
    TenantPaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'fee_bearer' => 'tenant']);
    app(TenantContext::class)->clear();

    $token = makePlatformStaffToken('platform-finance');

    $response = $this->getJson('/api/v1/platform/managed-payments', ['Authorization' => "Bearer {$token}"])->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.subaccount_code'))->toBe('ACCT_managed');
    expect($response->json('data.0.tenant.name'))->toBe('Oversight Co A');
    expect($response->json('data.0.effective_commission_percent'))->toEqual(1.0);
    // The masking policy from the tenant-facing endpoint still applies —
    // this oversight view must never leak raw secrets either.
    expect($response->getContent())->not->toContain('secret_key_encrypted');
});

it('refuses managed-payments oversight without payments.manage', function () {
    $token = makePlatformStaffToken('platform-operations');

    $this->getJson('/api/v1/platform/managed-payments', ['Authorization' => "Bearer {$token}"])->assertStatus(403);
});
