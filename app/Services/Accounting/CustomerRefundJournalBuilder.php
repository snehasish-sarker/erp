<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\CustomerOpenItem;
use App\Models\CustomerRefund;
use App\Models\CustomerRefundAllocation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerRefundJournalBuilder
{
    private const SCALE = 6;
    public function __construct(private readonly CustomerCreditControlAccountService $accountService,)
    {
    }
    /** @return array{posting_key: string, description: string, lines: list<array<string, mixed>>} */
    public function buildPosting(CustomerRefund $refund): array
    {
        $this->requireTransaction();
        $refund->loadMissing(['allocations.creditOpenItem', 'refundAccount']);
        if (!$refund->isApproved() || !$refund->hasRefundNumber()) {
            throw new LogicException('Only a numbered, approved Customer Refund can be posted.');
        }
        $refundAccount = $refund->refundAccount;
        if (!$refundAccount instanceof Account || !$refundAccount->isActive() || !$refundAccount->isPostingAccount() || !in_array($refundAccount->control_type, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(['refund_account_id' => ['The refund account must be an active cash or bank posting account.'],]);
        }
        $gain = $this->accountService->realizedExchangeGain();
        $loss = $this->accountService->realizedExchangeLoss();
        $lines = [];
        $total = BigDecimal::zero();
        $creditBaseTotal = BigDecimal::zero();
        foreach ($refund->allocations as $allocation) {
            if (!$allocation instanceof CustomerRefundAllocation) {
                continue;
            }
            $creditItem = $allocation->creditOpenItem;
            if (!$creditItem instanceof CustomerOpenItem) {
                throw new LogicException('A Customer Refund credit source is unavailable.');
            }
            $control = $this->accountService->forCreditOpenItem($creditItem);
            $amount = BigDecimal::of((string) $allocation->amount);
            $creditBase = BigDecimal::of((string) $allocation->credit_base_amount);
            $total = $total->plus($amount);
            $creditBaseTotal = $creditBaseTotal->plus($creditBase);
            $lines[] = $this->line(account: $control, branchId: (int) $refund->branch_id, customerId: (int) $refund->customer_id, reference: (string) $refund->refund_number, description: "Refund customer credit {$allocation->credit_document_number}", debit: $amount, credit: BigDecimal::zero(), baseDebit: $creditBase, baseCredit: BigDecimal::zero(),);
        }
        $cashBase = BigDecimal::of((string) $refund->base_cash_amount);
        $lines[] = $this->line(account: $refundAccount, branchId: (int) $refund->branch_id, customerId: null, reference: (string) $refund->refund_number, description: "Customer Refund {$refund->refund_number}", debit: BigDecimal::zero(), credit: $total, baseDebit: BigDecimal::zero(), baseCredit: $cashBase,);
        $exchange = $creditBaseTotal->minus($cashBase)->toScale(self::SCALE, RoundingMode::HalfUp);
        if ($exchange->isPositive()) {
            $lines[] = $this->line($gain, (int) $refund->branch_id, null, (string) $refund->refund_number, 'Realized exchange gain on Customer Refund', BigDecimal::zero(), BigDecimal::zero(), BigDecimal::zero(), $exchange);
        } elseif ($exchange->isNegative()) {
            $lines[] = $this->line($loss, (int) $refund->branch_id, null, (string) $refund->refund_number, 'Realized exchange loss on Customer Refund', BigDecimal::zero(), BigDecimal::zero(), $exchange->abs(), BigDecimal::zero());
        }
        return['posting_key' => $this->postingKey($refund), 'description' => mb_substr(sprintf('Customer Refund %s — %s', (string) $refund->refund_number, (string) $refund->customer_name), 0, 500), 'lines' => $lines,];
    }

    public function postingKey(CustomerRefund $refund): string
    {
        return sprintf('customer_refund:%d:journal:post', (int) $refund->getKey());
    }

    public function reversalPostingKey(CustomerRefund $refund): string
    {
        return sprintf('customer_refund:%d:journal:reverse', (int) $refund->getKey());
    }

    private function line(Account $account, int $branchId, ? int $customerId, string $reference, string $description, BigDecimal $debit, BigDecimal $credit, BigDecimal $baseDebit, BigDecimal $baseCredit): array
    {
        return['account_id' => $account->getKey(), 'branch_id' => $branchId, 'supplier_id' => null, 'customer_id' => $customerId, 'reference' => $reference, 'description' => mb_substr($description, 0, 500), 'due_date' => null, 'debit_amount' => $this->decimal($debit), 'credit_amount' => $this->decimal($credit), 'base_debit_amount' => $this->decimal($baseDebit), 'base_credit_amount' => $this->decimal($baseCredit),];
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Customer Refund journal building must run inside a transaction.');
        }
    }
}
