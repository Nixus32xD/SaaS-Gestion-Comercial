<?php

namespace App\Contracts\Payments;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;

interface PaymentProviderInterface
{
    public function createOrder(Business $business, Sale $sale, User $user, string $method): Payment;

    public function syncPayment(Payment $payment, ?array $providerPayload = null): Payment;
}
