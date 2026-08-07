<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\CustomerOpenItem;
use Illuminate\Validation\ValidationException;

final class CustomerCreditControlAccountService
{
    public function __construct(private readonly AccountService $accountService,)
    {
    }

    public function accountsReceivable(): Account
    {
        return $this->accountService->findSystemAccount('accounts_receivable_control', true);
    }

    public function customerAdvances(): Account
    {
        return $this->accountService->findSystemAccount('customer_advances', true);
    }

    public function realizedExchangeGain(): Account
    {
        return $this->accountService->findSystemAccount('realized_exchange_gain', true);
    }

    public function realizedExchangeLoss(): Account
    {
        return $this->accountService->findSystemAccount('realized_exchange_loss', true);
    }

    public function forCreditOpenItem(CustomerOpenItem $openItem): Account
    {
        if (!$openItem->isCredit()) {
            throw ValidationException::withMessages(['credit_open_item_id' => ['The selected customer item is not a credit balance.'],]);
        }
        return $openItem->item_type === 'receipt' ? $this->customerAdvances(): $this->accountsReceivable();
    }
}