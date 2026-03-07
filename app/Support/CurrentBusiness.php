<?php

namespace App\Support;

use App\Models\Business;

class CurrentBusiness
{
    public function __construct(private ?Business $business = null)
    {
    }

    public function set(?Business $business): void
    {
        $this->business = $business;
    }

    public function get(): ?Business
    {
        return $this->business;
    }
}

