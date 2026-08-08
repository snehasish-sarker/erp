<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerArAdjustment;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerOpenItem;
use App\Models\CustomerOpenItemAllocation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerArAdjustmentAccountsReceivableService
{
    public function post(CustomerArAdjustment $adjustment, AccountingPeriod $period, string $journalReference, User $actor): CustomerLedgerEntry
    {
        $this->requireTransaction();
        $adjustment = CustomerArAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();
        $existing = CustomerLedgerEntry::query()->where('posting_key', $this->postingKey($adjustment))->lockForUpdate()->first();
        if ($existing instanceof CustomerLedgerEntry) {
            return $existing;
        }
        $isDebit = $adjustment->isDebit();
        $ledger = CustomerLedgerEntry::query()->create(['branch_id' => $adjustment->branch_id, 'customer_id' => $adjustment->customer_id, 'accounting_period_id' => $period->getKey(), 'reference' => sprintf('customer-ar-adjustment:%d:ledger:post', (int) $adjustment->getKey()), 'posting_key' => $this->postingKey($adjustment), 'journal_reference' => $journalReference, 'entry_type' => 'adjustment', 'source_type' => $adjustment->getMorphClass(), 'source_id' => $adjustment->getKey(), 'source_document_number' => $adjustment->adjustment_number, 'document_date' => $adjustment->adjustment_date, 'posting_date' => $adjustment->posting_date, 'due_date' => null, 'currency_code' => $adjustment->currency_code, 'exchange_rate' => $adjustment->exchange_rate, 'debit_amount' => $isDebit ? $adjustment->amount: '0.000000', 'credit_amount' => $isDebit ? '0.000000': $adjustment->amount, 'base_debit_amount' => $isDebit ? $adjustment->base_amount: '0.000000', 'base_credit_amount' => $isDebit ? '0.000000': $adjustment->base_amount, 'description' => mb_substr(sprintf('Customer AR Adjustment %s — %s', (string) $adjustment->adjustment_number, (string) $adjustment->reason), 0, 500), 'created_by_user_id' => $actor->getKey(), 'reversal_of_id' => null,]);
        $openItem = CustomerOpenItem::query()->create(['branch_id' => $adjustment->branch_id, 'customer_id' => $adjustment->customer_id, 'accounting_period_id' => $period->getKey(), 'customer_ledger_entry_id' => $ledger->getKey(), 'item_type' => $isDebit ? 'adjustment_debit': 'adjustment_credit', 'source_type' => $adjustment->getMorphClass(), 'source_id' => $adjustment->getKey(), 'document_number' => $adjustment->adjustment_number, 'document_date' => $adjustment->adjustment_date, 'posting_date' => $adjustment->posting_date, 'due_date' => null, 'currency_code' => $adjustment->currency_code, 'exchange_rate' => $adjustment->exchange_rate, 'original_amount' => $adjustment->amount, 'allocated_amount' => '0.000000', 'outstanding_amount' => $adjustment->amount, 'base_original_amount' => $adjustment->base_amount, 'base_allocated_amount' => '0.000000', 'base_outstanding_amount' => $adjustment->base_amount, 'status' => 'open', 'created_by_user_id' => $actor->getKey(), 'closed_at' => null,]);
        $adjustment->customer_ledger_entry_id = $ledger->getKey();
        $adjustment->customer_open_item_id = $openItem->getKey();
        $adjustment->save();
        return $ledger;
    }

    public function reverse(CustomerArAdjustment $adjustment, AccountingPeriod $period, DateTimeInterface $reversalPostingDate, string $journalReference, string $reason, User $actor): CustomerLedgerEntry
    {
        $this->requireTransaction();
        $adjustment = CustomerArAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();
        $original = CustomerLedgerEntry::query()->where('posting_key', $this->postingKey($adjustment))->lockForUpdate()->first();
        if (!$original instanceof CustomerLedgerEntry) {
            throw new LogicException('The original Customer AR Adjustment ledger entry is unavailable.');
        }
        $existing = CustomerLedgerEntry::query()->where('posting_key', $this->reversalPostingKey($adjustment))->lockForUpdate()->first();
        if ($existing instanceof CustomerLedgerEntry) {
            return $existing;
        }
        $openItem = CustomerOpenItem::query()->whereKey($adjustment->customer_open_item_id)->lockForUpdate()->first();
        if (!$openItem instanceof CustomerOpenItem) {
            throw new LogicException('The Customer AR Adjustment open item is unavailable.');
        }
        $hasAppliedAllocations = CustomerOpenItemAllocation::query()->where($adjustment->isDebit() ? 'receivable_open_item_id': 'credit_open_item_id', $openItem->getKey())->where('status', 'applied')->lockForUpdate()->exists();
        if ($hasAppliedAllocations || (string) $openItem->allocated_amount !== '0.000000') {
            throw ValidationException::withMessages(['customer_ar_adjustment' => ['The adjustment cannot be reversed after its open item has been allocated. Reverse those allocations first.'],]);
        }
        $openItem->outstanding_amount = '0.000000';
        $openItem->base_outstanding_amount = '0.000000';
        $openItem->status = 'reversed';
        $openItem->closed_at = now();
        $openItem->save();
        return CustomerLedgerEntry::query()->create(['branch_id' => $adjustment->branch_id, 'customer_id' => $adjustment->customer_id, 'accounting_period_id' => $period->getKey(), 'reference' => sprintf('customer-ar-adjustment:%d:ledger:reverse', (int) $adjustment->getKey()), 'posting_key' => $this->reversalPostingKey($adjustment), 'journal_reference' => $journalReference, 'entry_type' => 'adjustment_reversal', 'source_type' => $adjustment->getMorphClass(), 'source_id' => $adjustment->getKey(), 'source_document_number' => $adjustment->adjustment_number, 'document_date' => $adjustment->adjustment_date, 'posting_date' => $reversalPostingDate, 'due_date' => null, 'currency_code' => $adjustment->currency_code, 'exchange_rate' => $adjustment->exchange_rate, 'debit_amount' => (string) $original->credit_amount, 'credit_amount' => (string) $original->debit_amount, 'base_debit_amount' => (string) $original->base_credit_amount, 'base_credit_amount' => (string) $original->base_debit_amount, 'description' => mb_substr(sprintf('Reverse Customer AR Adjustment %s — %s', (string) $adjustment->adjustment_number, trim($reason)), 0, 500), 'created_by_user_id' => $actor->getKey(), 'reversal_of_id' => $original->getKey(),]);
    }

    public function postingKey(CustomerArAdjustment $adjustment): string
    {
        return sprintf('customer_ar_adjustment:%d:customer_ledger:post', (int) $adjustment->getKey());
    }

    public function reversalPostingKey(CustomerArAdjustment $adjustment): string
    {
        return sprintf('customer_ar_adjustment:%d:customer_ledger:reverse', (int) $adjustment->getKey());
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Customer AR Adjustment subledger posting must run inside a transaction.');
        }
    }
}
