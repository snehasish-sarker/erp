<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\CustomerArAdjustment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerArAdjustmentJournalBuilder
{
    private const SCALE = 6;
    public function __construct(private readonly CustomerCreditControlAccountService $accountService,)
    {
    }
    /** @return array{posting_key: string, description: string, lines: list<array<string, mixed>>} */
    public function buildPosting(CustomerArAdjustment $adjustment): array
    {
        $this->requireTransaction();
        $adjustment->loadMissing('offsetAccount');
        if (!$adjustment->isApproved() || !$adjustment->hasAdjustmentNumber()) {
            throw new LogicException('Only a numbered, approved AR Adjustment can be posted.');
        }
        $offset = $adjustment->offsetAccount;
        if (!$offset instanceof Account || !$offset->isActive() || !$offset->isPostingAccount() || !$offset->allowsManualPosting()) {
            throw ValidationException::withMessages(['offset_account_id' => ['The offset account must be an active manual-posting account.'],]);
        }
        $ar = $this->accountService->accountsReceivable();
        $amount = BigDecimal::of((string) $adjustment->amount);
        $base = BigDecimal::of((string) $adjustment->base_amount);
        $reference = (string) $adjustment->adjustment_number;
        if ($adjustment->isDebit()) {
            $lines = [$this->line($ar, (int) $adjustment->branch_id, (int) $adjustment->customer_id, $reference, 'Customer AR debit adjustment', $amount, BigDecimal::zero(), $base, BigDecimal::zero()), $this->line($offset, (int) $adjustment->branch_id, null, $reference, 'AR adjustment offset', BigDecimal::zero(), $amount, BigDecimal::zero(), $base),];
        } else {
            $lines = [$this->line($offset, (int) $adjustment->branch_id, null, $reference, 'AR adjustment offset', $amount, BigDecimal::zero(), $base, BigDecimal::zero()), $this->line($ar, (int) $adjustment->branch_id, (int) $adjustment->customer_id, $reference, 'Customer AR credit adjustment', BigDecimal::zero(), $amount, BigDecimal::zero(), $base),];
        }
        return['posting_key' => $this->postingKey($adjustment), 'description' => mb_substr(sprintf('Customer AR Adjustment %s — %s', $reference, (string) $adjustment->customer_name), 0, 500), 'lines' => $lines,];
    }

    public function postingKey(CustomerArAdjustment $adjustment): string
    {
        return sprintf('customer_ar_adjustment:%d:journal:post', (int) $adjustment->getKey());
    }

    public function reversalPostingKey(CustomerArAdjustment $adjustment): string
    {
        return sprintf('customer_ar_adjustment:%d:journal:reverse', (int) $adjustment->getKey());
    }

    private function line(Account $account, int $branchId, ? int $customerId, string $reference, string $description, BigDecimal $debit, BigDecimal $credit, BigDecimal $baseDebit, BigDecimal $baseCredit): array
    {
        return['account_id' => $account->getKey(), 'branch_id' => $branchId, 'supplier_id' => null, 'customer_id' => $customerId, 'reference' => $reference, 'description' => $description, 'due_date' => null, 'debit_amount' => $this->decimal($debit), 'credit_amount' => $this->decimal($credit), 'base_debit_amount' => $this->decimal($baseDebit), 'base_credit_amount' => $this->decimal($baseCredit),];
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Customer AR Adjustment journal building must run inside a transaction.');
        }
    }
}
