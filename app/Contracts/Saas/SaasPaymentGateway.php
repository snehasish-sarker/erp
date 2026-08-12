<?php

declare(strict_types=1);

namespace App\Contracts\Saas;

use App\Data\Saas\SaasPaymentSession;
use App\Models\SaasInvoice;

interface SaasPaymentGateway
{
    public function key(): string;

    public function createPaymentSession(
        SaasInvoice $invoice,
    ): SaasPaymentSession;
}