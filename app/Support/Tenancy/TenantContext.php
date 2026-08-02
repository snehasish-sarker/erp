<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;
use LogicException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function tenant(): Tenant
    {
        if ($this->tenant === null) {
            throw new LogicException(
                'Tenant context has not been initialized.',
            );
        }

        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}