<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerOpenItem;
use App\Models\CustomerOpenItemAllocation;
use App\Models\CustomerRefund;
use App\Models\CustomerRefundAllocation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerRefundAccountsReceivableService
{
    private const MONEY_SCALE = 6;
    private const RATE_SCALE = 8;
    public function __construct(private readonly CustomerOpenItemAllocationService $allocationService,)
    {
    }

    public function post(CustomerRefund $refund, AccountingPeriod $period, string $journalReference, User $actor): CustomerLedgerEntry
    {
        $this->requireTransaction();
        $refund = CustomerRefund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
        $existing = CustomerLedgerEntry::query()->where('posting_key', $this->postingKey($refund))->lockForUpdate()->first();
        if ($existing instanceof CustomerLedgerEntry) {
            return $existing;
        }
        $amount = BigDecimal::of((string) $refund->total_amount)->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
        $baseCredit = BigDecimal::of((string) $refund->base_credit_amount)->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
        $effectiveRate = $baseCredit->dividedBy($amount, self::RATE_SCALE, RoundingMode::HalfUp);
        $ledger = CustomerLedgerEntry::query()->create(['branch_id' => $refund->branch_id, 'customer_id' => $refund->customer_id, 'accounting_period_id' => $period->getKey(), 'reference' => sprintf('customer-refund:%d:ledger:post', (int) $refund->getKey()), 'posting_key' => $this->postingKey($refund), 'journal_reference' => $journalReference, 'entry_type' => 'refund', 'source_type' => $refund->getMorphClass(), 'source_id' => $refund->getKey(), 'source_document_number' => $refund->refund_number, 'document_date' => $refund->refund_date, 'posting_date' => $refund->posting_date, 'due_date' => null, 'currency_code' => $refund->currency_code, 'exchange_rate' => $refund->exchange_rate, 'debit_amount' => $amount->__toString(), 'credit_amount' => '0.000000', 'base_debit_amount' => $baseCredit->__toString(), 'base_credit_amount' => '0.000000', 'description' => mb_substr(sprintf('Customer Refund %s — %s', (string) $refund->refund_number, (string) $refund->customer_name), 0, 500), 'created_by_user_id' => $actor->getKey(), 'reversal_of_id' => null,]);
        $refundOpenItem = CustomerOpenItem::query()->create(['branch_id' => $refund->branch_id, 'customer_id' => $refund->customer_id, 'accounting_period_id' => $period->getKey(), 'customer_ledger_entry_id' => $ledger->getKey(), 'item_type' => 'refund', 'source_type' => $refund->getMorphClass(), 'source_id' => $refund->getKey(), 'document_number' => $refund->refund_number, 'document_date' => $refund->refund_date, 'posting_date' => $refund->posting_date, 'due_date' => null, 'currency_code' => $refund->currency_code, 'exchange_rate' => $effectiveRate->__toString(), 'original_amount' => $amount->__toString(), 'allocated_amount' => '0.000000', 'outstanding_amount' => $amount->__toString(), 'base_original_amount' => $baseCredit->__toString(), 'base_allocated_amount' => '0.000000', 'base_outstanding_amount' => $baseCredit->__toString(), 'status' => 'open', 'created_by_user_id' => $actor->getKey(), 'closed_at' => null,]);
        /** @var \Illuminate\Database\Eloquent\Collection<int, CustomerRefundAllocation> $allocations */
        $allocations = CustomerRefundAllocation::query()->where('customer_refund_id', $refund->getKey())->orderBy('line_number')->lockForUpdate()->get();
        foreach ($allocations as $line) {
            $credit = CustomerOpenItem::query()->whereKey($line->credit_open_item_id)->lockForUpdate()->first();
            if (!$credit instanceof CustomerOpenItem) {
                throw new LogicException('A Customer Refund credit open item is unavailable.');
            }
            $allocation = $this->allocationService->apply(receivableOpenItem: $refundOpenItem, creditOpenItem: $credit, accountingPeriod: $period, allocationType: 'refund', postingKey: sprintf('customer_refund:%d:line:%d', (int) $refund->getKey(), (int) $line->line_number), allocationDate: $refund->refund_date, postingDate: $refund->posting_date, amount: (string) $line->amount, source: $refund, actor: $actor,);
            $line->customer_open_item_allocation_id = $allocation->getKey();
            $line->credit_base_amount = $allocation->credit_base_amount;
            $line->status = 'applied';
            $line->applied_at = now();
            $line->save();
        }
        $refundOpenItem->refresh();
        if (!$refundOpenItem->isSettled()) {
            throw new LogicException('The Customer Refund clearing open item was not fully allocated.');
        }
        $refund->customer_ledger_entry_id = $ledger->getKey();
        $refund->customer_open_item_id = $refundOpenItem->getKey();
        $refund->save();
        return $ledger;
    }

    public function reverse(CustomerRefund $refund, AccountingPeriod $period, DateTimeInterface $reversalPostingDate, string $journalReference, string $reason, User $actor): CustomerLedgerEntry
    {
        $this->requireTransaction();
        $refund = CustomerRefund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
        $original = CustomerLedgerEntry::query()->where('posting_key', $this->postingKey($refund))->lockForUpdate()->first();
        if (!$original instanceof CustomerLedgerEntry) {
            throw new LogicException('The original Customer Refund ledger entry is unavailable.');
        }
        $existing = CustomerLedgerEntry::query()->where('posting_key', $this->reversalPostingKey($refund))->lockForUpdate()->first();
        if ($existing instanceof CustomerLedgerEntry) {
            return $existing;
        }
        $refundOpenItem = CustomerOpenItem::query()->whereKey($refund->customer_open_item_id)->lockForUpdate()->first();
        if (!$refundOpenItem instanceof CustomerOpenItem) {
            throw new LogicException('The Customer Refund clearing open item is unavailable.');
        }
        $lines = CustomerRefundAllocation::query()->where('customer_refund_id', $refund->getKey())->orderByDesc('line_number')->lockForUpdate()->get();
        foreach ($lines as $line) {
            if ($line->customer_open_item_allocation_id === null) {
                throw new LogicException('A posted Customer Refund allocation link is missing.');
            }
            $allocation = CustomerOpenItemAllocation::query()->whereKey($line->customer_open_item_allocation_id)->lockForUpdate()->first();
            if (!$allocation instanceof CustomerOpenItemAllocation) {
                throw new LogicException('A Customer Refund open-item allocation is unavailable.');
            }
            if ($allocation->isApplied()) {
                $this->allocationService->reverse($allocation, $period, $reversalPostingDate, $reason, $actor);
            }
            $line->status = 'reversed';
            $line->reversed_at = now();
            $line->save();
        }
        $refundOpenItem->refresh();
        if ((string) $refundOpenItem->outstanding_amount !== (string) $refundOpenItem->original_amount) {
            throw ValidationException::withMessages(['customer_refund' => ['The Customer Refund cannot be reversed because its clearing item does not retain the original amount.'],]);
        }
        $refundOpenItem->outstanding_amount = '0.000000';
        $refundOpenItem->base_outstanding_amount = '0.000000';
        $refundOpenItem->status = 'reversed';
        $refundOpenItem->closed_at = now();
        $refundOpenItem->save();
        return CustomerLedgerEntry::query()->create(['branch_id' => $refund->branch_id, 'customer_id' => $refund->customer_id, 'accounting_period_id' => $period->getKey(), 'reference' => sprintf('customer-refund:%d:ledger:reverse', (int) $refund->getKey()), 'posting_key' => $this->reversalPostingKey($refund), 'journal_reference' => $journalReference, 'entry_type' => 'refund_reversal', 'source_type' => $refund->getMorphClass(), 'source_id' => $refund->getKey(), 'source_document_number' => $refund->refund_number, 'document_date' => $refund->refund_date, 'posting_date' => $reversalPostingDate, 'due_date' => null, 'currency_code' => $refund->currency_code, 'exchange_rate' => $refund->exchange_rate, 'debit_amount' => '0.000000', 'credit_amount' => (string) $original->debit_amount, 'base_debit_amount' => '0.000000', 'base_credit_amount' => (string) $original->base_debit_amount, 'description' => mb_substr(sprintf('Reverse Customer Refund %s — %s', (string) $refund->refund_number, trim($reason)), 0, 500), 'created_by_user_id' => $actor->getKey(), 'reversal_of_id' => $original->getKey(),]);
    }

    public function postingKey(CustomerRefund $refund): string
    {
        return sprintf('customer_refund:%d:customer_ledger:post', (int) $refund->getKey());
    }

    public function reversalPostingKey(CustomerRefund $refund): string
    {
        return sprintf('customer_refund:%d:customer_ledger:reverse', (int) $refund->getKey());
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Customer Refund AR posting must run inside a transaction.');
        }
    }
}
