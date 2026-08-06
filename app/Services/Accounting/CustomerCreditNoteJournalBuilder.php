<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\CustomerCreditNote;
use App\Models\CustomerCreditNoteLine;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerCreditNoteJournalBuilder
{
    private const SCALE = 6;

    public function __construct(
        private readonly SalesAccountingAccountService $accountService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @return array{
     *     posting_key: string,
     *     description: string,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function buildFinancialPosting(
        CustomerCreditNote $creditNote,
    ): array {
        $this->ensureInsideTransaction();
        $this->ensurePostableDocument($creditNote);
        $creditNote->loadMissing('lines');

        if ($creditNote->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Customer Credit Note does not contain any posting lines.',
                ],
            ]);
        }

        $accounts = $this->accountService
            ->salesInvoiceAccounts();

        $goodsRevenue = BigDecimal::zero();
        $serviceRevenue = BigDecimal::zero();
        $tax = BigDecimal::zero();
        $total = BigDecimal::zero();

        foreach ($creditNote->lines as $line) {
            if (!$line instanceof CustomerCreditNoteLine) {
                continue;
            }

            $subtotal = BigDecimal::of(
                (string) $line->subtotal,
            );

            if ($line->product_type === 'service') {
                $serviceRevenue = $serviceRevenue->plus($subtotal);
            } else {
                $goodsRevenue = $goodsRevenue->plus($subtotal);
            }

            $tax = $tax->plus(
                BigDecimal::of((string) $line->tax_amount),
            );

            $total = $total->plus(
                BigDecimal::of((string) $line->line_total),
            );
        }

        $storedTotal = BigDecimal::of(
            (string) $creditNote->total_amount,
        );

        if (!$total->isEqualTo($storedTotal)) {
            throw new LogicException(
                'The Customer Credit Note line total does not match its stored total amount.',
            );
        }

        $reference = (string) $creditNote->credit_note_number;
        $lines = [];

        $this->appendDebitLine(
            lines: $lines,
            account: $accounts['sales_revenue'],
            creditNote: $creditNote,
            amount: $goodsRevenue,
            reference: $reference,
            description: "Goods revenue reversal for Credit Note {$reference}",
        );

        $this->appendDebitLine(
            lines: $lines,
            account: $accounts['service_revenue'],
            creditNote: $creditNote,
            amount: $serviceRevenue,
            reference: $reference,
            description: "Service revenue reversal for Credit Note {$reference}",
        );

        $this->appendDebitLine(
            lines: $lines,
            account: $accounts['output_tax_payable'],
            creditNote: $creditNote,
            amount: $tax,
            reference: $reference,
            description: "Output tax reversal for Credit Note {$reference}",
        );

        $lines[] = [
            'account_id' => $accounts[
                'accounts_receivable_control'
            ]->getKey(),
            'branch_id' => $creditNote->branch_id,
            'supplier_id' => null,
            'customer_id' => $creditNote->customer_id,
            'reference' => $reference,
            'description' => $this->description(
                "Accounts Receivable credit for Credit Note {$reference}",
            ),
            'due_date' => null,
            'debit_amount' => '0.000000',
            'credit_amount' => $this->decimal($total),
        ];

        if (count($lines) < 2) {
            throw new LogicException(
                'The Customer Credit Note financial journal does not contain enough lines.',
            );
        }

        return [
            'posting_key' => $this->financialPostingKey($creditNote),
            'description' => $this->description(
                sprintf(
                    'Customer Credit Note %s — %s',
                    $reference,
                    (string) $creditNote->customer_name,
                ),
            ),
            'lines' => $lines,
        ];
    }

    /**
     * @return array{
     *     posting_key: string,
     *     description: string,
     *     currency_code: string,
     *     lines: list<array<string, mixed>>
     * }|null
     */
    public function buildInventoryPosting(
        CustomerCreditNote $creditNote,
    ): ?array {
        $this->ensureInsideTransaction();
        $this->ensurePostableDocument($creditNote);
        $creditNote->loadMissing('lines');

        $totalCost = BigDecimal::zero();

        foreach ($creditNote->lines as $line) {
            if (
                $line instanceof CustomerCreditNoteLine
                && $line->restoresInventory()
            ) {
                $totalCost = $totalCost->plus(
                    BigDecimal::of((string) $line->total_cost),
                );
            }
        }

        if (!$totalCost->isGreaterThan(BigDecimal::zero())) {
            return null;
        }

        $accounts = $this->accountService
            ->dispatchAccounts();

        $reference = (string) $creditNote->credit_note_number;
        $amount = $this->decimal($totalCost);
        $tenant = $this->tenantContext->tenant();

        return [
            'posting_key' => $this->inventoryPostingKey($creditNote),
            'description' => $this->description(
                "Inventory restored by Customer Credit Note {$reference}",
            ),
            'currency_code' => strtoupper((string) $tenant->currency_code),
            'lines' => [
                [
                    'account_id' => $accounts['inventory_asset']->getKey(),
                    'branch_id' => $creditNote->branch_id,
                    'supplier_id' => null,
                    'customer_id' => null,
                    'reference' => $reference,
                    'description' => $this->description(
                        "Inventory restored by Credit Note {$reference}",
                    ),
                    'due_date' => null,
                    'debit_amount' => $amount,
                    'credit_amount' => '0.000000',
                ],
                [
                    'account_id' => $accounts['cost_of_goods_sold']->getKey(),
                    'branch_id' => $creditNote->branch_id,
                    'supplier_id' => null,
                    'customer_id' => null,
                    'reference' => $reference,
                    'description' => $this->description(
                        "Cost of Goods Sold reversal for Credit Note {$reference}",
                    ),
                    'due_date' => null,
                    'debit_amount' => '0.000000',
                    'credit_amount' => $amount,
                ],
            ],
        ];
    }

    public function financialPostingKey(
        CustomerCreditNote $creditNote,
    ): string {
        return sprintf(
            'customer_credit_note:%d:journal:financial:post',
            (int) $creditNote->getKey(),
        );
    }

    public function financialReversalPostingKey(
        CustomerCreditNote $creditNote,
    ): string {
        return sprintf(
            'customer_credit_note:%d:journal:financial:reverse',
            (int) $creditNote->getKey(),
        );
    }

    public function inventoryPostingKey(
        CustomerCreditNote $creditNote,
    ): string {
        return sprintf(
            'customer_credit_note:%d:journal:inventory:post',
            (int) $creditNote->getKey(),
        );
    }

    public function inventoryReversalPostingKey(
        CustomerCreditNote $creditNote,
    ): string {
        return sprintf(
            'customer_credit_note:%d:journal:inventory:reverse',
            (int) $creditNote->getKey(),
        );
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function appendDebitLine(
        array &$lines,
        Account $account,
        CustomerCreditNote $creditNote,
        BigDecimal $amount,
        string $reference,
        string $description,
    ): void {
        if ($amount->isZero()) {
            return;
        }

        if ($amount->isLessThan(BigDecimal::zero())) {
            throw new LogicException(
                'Customer Credit Note revenue and tax amounts cannot be negative.',
            );
        }

        $lines[] = [
            'account_id' => $account->getKey(),
            'branch_id' => $creditNote->branch_id,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => $this->description($description),
            'due_date' => null,
            'debit_amount' => $this->decimal($amount),
            'credit_amount' => '0.000000',
        ];
    }

    private function ensurePostableDocument(
        CustomerCreditNote $creditNote,
    ): void {
        if (!$creditNote->hasCreditNoteNumber()) {
            throw new LogicException(
                'The Customer Credit Note does not have a document number.',
            );
        }

        if (!in_array($creditNote->status, ['approved', 'posted'], true)) {
            throw ValidationException::withMessages([
                'status' => [
                    'The Customer Credit Note is not in a postable accounting status.',
                ],
            ]);
        }
    }

    private function decimal(BigDecimal $value): string
    {
        return $value
            ->toScale(self::SCALE, RoundingMode::HALF_UP)
            ->__toString();
    }

    private function description(string $value): string
    {
        return mb_substr(trim($value), 0, 500);
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Customer Credit Note journal building must run inside a database transaction.',
            );
        }
    }
}