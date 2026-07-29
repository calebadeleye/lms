<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Contracts\PaymentProviderInterface;
use App\Domain\Commerce\Models\TenantPaymentConfig;
use App\Domain\Commerce\Providers\FlutterwavePaymentProvider;
use App\Domain\Commerce\Providers\PaystackPaymentProvider;

/**
 * The single place that turns "which tenant, which mode" into a concrete,
 * correctly-credentialed PaymentProviderInterface. No other class
 * instantiates PaystackPaymentProvider/FlutterwavePaymentProvider directly.
 */
class PaymentProviderFactory
{
    public function forTenantConfig(TenantPaymentConfig $config): PaymentProviderInterface
    {
        $secretKey = $config->isManaged()
            ? $this->platformSecretKey($config->provider)
            : $config->getSecretKey();

        if (! $secretKey) {
            throw new \RuntimeException("No secret key configured for tenant payment config #{$config->id}.");
        }

        return $this->make($config->provider, $secretKey);
    }

    /** Platform billing (NAI TALK's own SaaS revenue) — never a tenant's credentials. */
    public function forPlatform(): PaymentProviderInterface
    {
        $provider = config('services.platform_billing.provider');

        return $this->make($provider, $this->platformSecretKey($provider));
    }

    private function make(string $provider, string $secretKey): PaymentProviderInterface
    {
        return match ($provider) {
            'paystack' => new PaystackPaymentProvider($secretKey),
            'flutterwave' => new FlutterwavePaymentProvider($secretKey),
            default => throw new \InvalidArgumentException("Unknown payment provider: {$provider}"),
        };
    }

    private function platformSecretKey(string $provider): string
    {
        return match ($provider) {
            'paystack' => (string) config('services.paystack.secret_key'),
            'flutterwave' => (string) config('services.flutterwave.secret_key'),
            default => throw new \InvalidArgumentException("Unknown payment provider: {$provider}"),
        };
    }
}
