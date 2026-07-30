<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Contracts\PaymentProviderInterface;
use App\Domain\Commerce\Models\PaymentConfig;
use App\Domain\Commerce\Providers\FlutterwavePaymentProvider;
use App\Domain\Commerce\Providers\PaystackPaymentProvider;

/**
 * The single place that turns "which mode" into a concrete,
 * correctly-credentialed PaymentProviderInterface. No other class
 * instantiates PaystackPaymentProvider/FlutterwavePaymentProvider directly.
 */
class PaymentProviderFactory
{
    public function forConfig(PaymentConfig $config): PaymentProviderInterface
    {
        $secretKey = $config->isManaged()
            ? $this->managedSecretKey($config->provider)
            : $config->getSecretKey();

        if (! $secretKey) {
            throw new \RuntimeException("No secret key configured for payment config #{$config->id}.");
        }

        return $this->make($config->provider, $secretKey);
    }

    /**
     * For platform-level charges that aren't the organization's own
     * commerce (e.g. the registration fee) — always the platform's own
     * managed Paystack account, regardless of whether this org has since
     * configured (or switched to) a client-owned gateway of its own.
     */
    public function forManagedPaystack(): PaymentProviderInterface
    {
        return $this->make('paystack', $this->managedSecretKey('paystack'));
    }

    private function make(string $provider, string $secretKey): PaymentProviderInterface
    {
        return match ($provider) {
            'paystack' => new PaystackPaymentProvider($secretKey),
            'flutterwave' => new FlutterwavePaymentProvider($secretKey),
            default => throw new \InvalidArgumentException("Unknown payment provider: {$provider}"),
        };
    }

    private function managedSecretKey(string $provider): string
    {
        return match ($provider) {
            'paystack' => (string) config('services.paystack.secret_key'),
            'flutterwave' => (string) config('services.flutterwave.secret_key'),
            default => throw new \InvalidArgumentException("Unknown payment provider: {$provider}"),
        };
    }
}
