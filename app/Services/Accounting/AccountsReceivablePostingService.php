<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerOpenItem;
use App\Models\CustomerOpenItemAllocation;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AccountsReceivablePostingService
{
    private const MONEY_SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function postSalesInvoice(
        SalesInvoice $salesInvoice,
        AccountingPeriod $accountingPeriod,
        string $journalReference,
        User $actor,
    ): CustomerLedgerEntry {
        $this->ensureInsideTransaction();
        $this->ensureContext(
            salesInvoice: $salesInvoice,
            accountingPeriod: $accountingPeriod,
            actor: $actor,
        );

        $postingKey = $this->postingKey(
            $salesInvoice,
        );

        $existing = CustomerLedgerEntry::query()
            ->where('posting_key', $postingKey)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof CustomerLedgerEntry) {
            return $existing;
        }

        $amount = BigDecimal::of(
            (string) $salesInvoice->total_amount,
        );

        $baseAmount = $amount
            ->multipliedBy(
                BigDecimal::of(
                    (string) $salesInvoice->exchange_rate,
                ),
            )
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );

        $reference = sprintf(
            'sales-invoice:%d:ledger:post',
            (int) $salesInvoice->getKey(),
        );

        $ledgerEntry = CustomerLedgerEntry::query()
            ->create([
                'branch_id' => $salesInvoice->branch_id,
                'customer_id' => $salesInvoice->customer_id,
                'accounting_period_id' => $accountingPeriod->getKey(),
                'reference' => $reference,
                'posting_key' => $postingKey,
                'journal_reference' => $journalReference,
                'entry_type' => 'invoice',
                'source_type' => $salesInvoice->getMorphClass(),
                'source_id' => $salesInvoice->getKey(),
                'source_document_number' => $salesInvoice->invoice_number,
                'document_date' => $salesInvoice->invoice_date,
                'posting_date' => $salesInvoice->posting_date,
                'due_date' => $salesInvoice->due_date,
                'currency_code' => $salesInvoice->currency_code,
                'exchange_rate' => $salesInvoice->exchange_rate,
                'debit_amount' => $amount->toScale(self::MONEY_SCALE)->__toString(),
                'credit_amount' => '0.000000',
                'base_debit_amount' => $baseAmount->__toString(),
                'base_credit_amount' => '0.000000',
                'description' => sprintf(
                    'Sales Invoice %s — %s',
                    (string) $salesInvoice->invoice_number,
                    (string) $salesInvoice->customer_name,
                ),
                'created_by_user_id' => $actor->getKey(),
                'reversal_of_id' => null,
            ]);

        CustomerOpenItem::query()->create([
            'branch_id' => $salesInvoice->branch_id,
            'customer_id' => $salesInvoice->customer_id,
            'accounting_period_id' => $accountingPeriod->getKey(),
            'customer_ledger_entry_id' => $ledgerEntry->getKey(),
            'item_type' => 'invoice',
            'source_type' => $salesInvoice->getMorphClass(),
            'source_id' => $salesInvoice->getKey(),
            'document_number' => $salesInvoice->invoice_number,
            'document_date' => $salesInvoice->invoice_date,
            'posting_date' => $salesInvoice->posting_date,
            'due_date' => $salesInvoice->due_date,
            'currency_code' => $salesInvoice->currency_code,
            'exchange_rate' => $salesInvoice->exchange_rate,
            'original_amount' => $amount->toScale(self::MONEY_SCALE)->__toString(),
            'allocated_amount' => '0.000000',
            'outstanding_amount' => $amount->toScale(self::MONEY_SCALE)->__toString(),
            'base_original_amount' => $baseAmount->__toString(),
            'base_allocated_amount' => '0.000000',
            'base_outstanding_amount' => $baseAmount->__toString(),
            'status' => 'open',
            'created_by_user_id' => $actor->getKey(),
            'closed_at' => null,
        ]);

        return $ledgerEntry;
    }

    public function reverseSalesInvoice(
        SalesInvoice $salesInvoice,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $journalReference,
        string $reason,
        User $actor,
    ): CustomerLedgerEntry {
        $this->ensureInsideTransaction();
        $this->ensureContext(
            salesInvoice: $salesInvoice,
            accountingPeriod: $accountingPeriod,
            actor: $actor,
        );

        $original = CustomerLedgerEntry::query()
            ->where(
                'posting_key',
                $this->postingKey($salesInvoice),
            )
            ->lockForUpdate()
            ->first();

        if (!$original instanceof CustomerLedgerEntry) {
            throw new LogicException(
                'The original customer-ledger invoice entry is unavailable.',
            );
        }

        $reversalKey = $this->reversalPostingKey(
            $salesInvoice,
        );

        $existing = CustomerLedgerEntry::query()
            ->where('posting_key', $reversalKey)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof CustomerLedgerEntry) {
            return $existing;
        }

        $openItem = CustomerOpenItem::query()
            ->where(
                'customer_ledger_entry_id',
                $original->getKey(),
            )
            ->lockForUpdate()
            ->first();

        if (!$openItem instanceof CustomerOpenItem) {
            throw new LogicException(
                'The Sales Invoice customer open item is unavailable.',
            );
        }

        $hasAllocations = CustomerOpenItemAllocation::query()
            ->where(
                'receivable_open_item_id',
                $openItem->getKey(),
            )
            ->where('status', 'applied')
            ->lockForUpdate()
            ->exists();

        if (
            $hasAllocations
            || (string) $openItem->allocated_amount !== '0.000000'
            || (string) $openItem->outstanding_amount
                !== (string) $openItem->original_amount
        ) {
            throw ValidationException::withMessages([
                'sales_invoice' => [
                    'The Sales Invoice cannot be reversed after a receipt, credit, or other allocation has been applied.',
                ],
            ]);
        }

        $reversal = CustomerLedgerEntry::query()
            ->create([
                'branch_id' => $salesInvoice->branch_id,
                'customer_id' => $salesInvoice->customer_id,
                'accounting_period_id' => $accountingPeriod->getKey(),
                'reference' => sprintf(
                    'sales-invoice:%d:ledger:reverse',
                    (int) $salesInvoice->getKey(),
                ),
                'posting_key' => $reversalKey,
                'journal_reference' => $journalReference,
                'entry_type' => 'invoice_reversal',
                'source_type' => $salesInvoice->getMorphClass(),
                'source_id' => $salesInvoice->getKey(),
                'source_document_number' => $salesInvoice->invoice_number,
                'document_date' => $salesInvoice->invoice_date,
                'posting_date' => $reversalPostingDate,
                'due_date' => null,
                'currency_code' => $salesInvoice->currency_code,
                'exchange_rate' => $salesInvoice->exchange_rate,
                'debit_amount' => '0.000000',
                'credit_amount' => (string) $original->debit_amount,
                'base_debit_amount' => '0.000000',
                'base_credit_amount' => (string) $original->base_debit_amount,
                'description' => sprintf(
                    'Reverse Sales Invoice %s — %s',
                    (string) $salesInvoice->invoice_number,
                    trim($reason),
                ),
                'created_by_user_id' => $actor->getKey(),
                'reversal_of_id' => $original->getKey(),
            ]);

        $openItem->outstanding_amount = '0.000000';
        $openItem->base_outstanding_amount = '0.000000';
        $openItem->status = 'reversed';
        $openItem->closed_at = now();
        $openItem->save();

        return $reversal;
    }

    public function postingKey(
        SalesInvoice $salesInvoice,
    ): string {
        return sprintf(
            'sales_invoice:%d:customer_ledger:post',
            (int) $salesInvoice->getKey(),
        );
    }

    public function reversalPostingKey(
        SalesInvoice $salesInvoice,
    ): string {
        return sprintf(
            'sales_invoice:%d:customer_ledger:reverse',
            (int) $salesInvoice->getKey(),
        );
    }

    private function ensureContext(
        SalesInvoice $salesInvoice,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): void {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        if (
            (int) $salesInvoice->tenant_id !== $tenantId
            || (int) $accountingPeriod->tenant_id !== $tenantId
            || (int) $actor->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'Accounts Receivable posting crossed an accounting or tenant boundary.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Accounts Receivable posting must run inside the source document transaction.',
            );
        }
    }
}