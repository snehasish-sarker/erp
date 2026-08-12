<?php

declare(strict_types=1);

namespace App\Data\Saas;

final readonly class SaasPaymentSession
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $provider,
        public string $providerPaymentId,
        public ?string $checkoutUrl,
        public array $metadata = [],
    ) {
    }
}