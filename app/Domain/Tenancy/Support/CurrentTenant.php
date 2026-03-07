<?php

namespace App\Domain\Tenancy\Support;

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;

class CurrentTenant
{
    public function __construct(
        private ?Tenant $tenant = null,
        private ?Branch $branch = null,
        private ?TenantMembership $membership = null,
    ) {
    }

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setBranch(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function setMembership(?TenantMembership $membership): void
    {
        $this->membership = $membership;
    }

    public function membership(): ?TenantMembership
    {
        return $this->membership;
    }
}
