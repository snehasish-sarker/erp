<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\TreasuryAdjustment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TreasuryAdjustmentJournalBuilder
{
    private const SCALE = 6;

    /** @return array{posting_key: string, description: string, lines: list<array<string, mixed>>} */
    public function build(TreasuryAdjustment $adjustment): array
    {
        $this->requireTransaction();
        $adjustment->loadMissing(['bankAccount', 'offsetAccount']);
        $bank = $adjustment->bankAccount;
        $offset = $adjustment->offsetAccount;

        if (!$bank instanceof Account || !$offset instanceof Account) {
            throw new LogicException('Treasury Adjustment accounts are unavailable.');
        }

        $amount = BigDecimal::of((string) $adjustment->amount);
        $base = BigDecimal::of((string) $adjustment->base_amount);
        $reference = (string) $adjustment->adjustment_number;
        $bankDebit = in_array($adjustment->adjustment_type, ['bank_interest', 'other_credit'], true);

        $lines = $bankDebit
            ? [
                $this->line($bank, $adjustment, $reference, $amount, BigDecimal::zero(), $base, BigDecimal::zero()),
                $this->line($offset, $adjustment, $reference, BigDecimal::zero(), $amount, BigDecimal::zero(), $base),
            ]
            : [
                $this->line($offset, $adjustment, $reference, $amount, BigDecimal::zero(), $base, BigDecimal::zero()),
                $this->line($bank, $adjustment, $reference, BigDecimal::zero(), $amount, BigDecimal::zero(), $base),
            ];

        return [
            'posting_key' => $this->postingKey($adjustment),
            'description' => mb_substr(
                sprintf('Treasury Adjustment %s — %s', $reference, $adjustment->description),
                0,
                500,
            ),
            'lines' => $lines,
        ];
    }

    public function postingKey(TreasuryAdjustment $adjustment): string
    {
        return sprintf('treasury_adjustment:%d:journal:post', (int) $adjustment->getKey());
    }

    public function reversalPostingKey(TreasuryAdjustment $adjustment): string
    {
        return sprintf('treasury_adjustment:%d:journal:reverse', (int) $adjustment->getKey());
    }

    /** @return array<string, mixed> */
    private function line(
        Account $account,
        TreasuryAdjustment $adjustment,
        string $reference,
        BigDecimal $debit,
        BigDecimal $credit,
        BigDecimal $baseDebit,
        BigDecimal $baseCredit,
    ): array {
        return [
            'account_id' => $account->getKey(),
            'branch_id' => $adjustment->branch_id,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => $adjustment->description,
            'due_date' => null,
            'debit_amount' => $this->decimal($debit),
            'credit_amount' => $this->decimal($credit),
            'base_debit_amount' => $this->decimal($baseDebit),
            'base_credit_amount' => $this->decimal($baseCredit),
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HALF_UP)->__toString();
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Treasury Adjustment journal building must run inside a transaction.');
        }
    }
}
