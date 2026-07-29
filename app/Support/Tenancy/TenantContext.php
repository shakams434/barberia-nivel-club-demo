<?php

namespace App\Support\Tenancy;

class TenantContext
{
    private ?int $businessId = null;

    public function set(?int $businessId): void
    {
        $this->businessId = $businessId;
    }

    public function id(): ?int
    {
        return $this->businessId;
    }

    public function clear(): void
    {
        $this->businessId = null;
    }
}
