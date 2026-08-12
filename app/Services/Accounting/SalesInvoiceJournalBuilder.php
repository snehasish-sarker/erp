<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SalesInvoiceJournalBuilder
{
    private const SCALE = 6;

    public function __construct(
        private readonly SalesAccountingAccountService $accountService,
    ) {
    }

    /**
     * @return array{
     *     posting_key: string,
     *     description: string,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function buildPosting(
        SalesInvoice $salesInvoice,
    ): array {
        $this->ensureInsideTransaction();

        if (!$salesInvoice->hasInvoiceNumber()) {
            throw new LogicException(
                'The Sales Invoice does not have an invoice number.',
            );
        }

        $salesInvoice->loadMissing('lines');

        if ($salesInvoice->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The Sales Invoice does not contain any posting lines.',
                ],
            ]);
        }

        $accounts = $this->accountService
            ->salesInvoiceAccounts();

        $goodsRevenue = BigDecimal::zero();
        $serviceRevenue = BigDecimal::zero();

        foreach ($salesInvoice->lines as $line) {
            $netRevenue = BigDecimal::of(
                (string) $line->gross_amount,
            )->minus(
                BigDecimal::of(
                    (string) $line->discount_amount,
                ),
            );

            if ($line->isService()) {
                $serviceRevenue = $serviceRevenue
                    ->plus($netRevenue);
            } else {
                $goodsRevenue = $goodsRevenue
                    ->plus($netRevenue);
            }
        }

        $goodsRevenue = $goodsRevenue
            ->plus(
                BigDecimal::of(
                    (string) $salesInvoice->shipping_amount,
                ),
            )
            ->plus(
                BigDecimal::of(
                    (string) $salesInvoice->other_charges,
                ),
            );

        $tax = BigDecimal::of(
            (string) $salesInvoice->tax_amount,
        );

        $total = BigDecimal::of(
            (string) $salesInvoice->total_amount,
        );

        $reference = (string) $salesInvoice
            ->invoice_number;

        $lines = [[
            'account_id' => $accounts[
                'accounts_receivable_control'
            ]->getKey(),
            'branch_id' => $salesInvoice->branch_id,
            'supplier_id' => null,
            'customer_id' => $salesInvoice->customer_id,
            'reference' => $reference,
            'description' => $this->description(
                "Accounts Receivable for Sales Invoice {$reference}",
            ),
            'due_date' => $salesInvoice->due_date?->toDateString(),
            'debit_amount' => $this->decimal($total),
            'credit_amount' => '0.000000',
        ]];

        $this->appendCreditLine(
            lines: $lines,
            account: $accounts['sales_revenue'],
            branchId: (int) $salesInvoice->branch_id,
            amount: $goodsRevenue,
            reference: $reference,
            description: "Goods revenue for Sales Invoice {$reference}",
        );

        $this->appendCreditLine(
            lines: $lines,
            account: $accounts['service_revenue'],
            branchId: (int) $salesInvoice->branch_id,
            amount: $serviceRevenue,
            reference: $reference,
            description: "Service revenue for Sales Invoice {$reference}",
        );

        $this->appendCreditLine(
            lines: $lines,
            account: $accounts['output_tax_payable'],
            branchId: (int) $salesInvoice->branch_id,
            amount: $tax,
            reference: $reference,
            description: "Output tax for Sales Invoice {$reference}",
        );

        if (count($lines) < 2) {
            throw new LogicException(
                'The Sales Invoice journal does not contain enough lines.',
            );
        }

        return [
            'posting_key' => $this->postingKey(
                $salesInvoice,
            ),
            'description' => $this->description(
                sprintf(
                    'Sales Invoice %s — %s',
                    $reference,
                    (string) $salesInvoice->customer_name,
                ),
            ),
            'lines' => $lines,
        ];
    }

    public function postingKey(
        SalesInvoice $salesInvoice,
    ): string {
        return sprintf(
            'sales_invoice:%d:journal:post',
            (int) $salesInvoice->getKey(),
        );
    }

    public function reversalPostingKey(
        SalesInvoice $salesInvoice,
    ): string {
        return sprintf(
            'sales_invoice:%d:journal:reverse',
            (int) $salesInvoice->getKey(),
        );
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function appendCreditLine(
        array &$lines,
        Account $account,
        int $branchId,
        BigDecimal $amount,
        string $reference,
        string $description,
    ): void {
        if ($amount->isZero()) {
            return;
        }

        if ($amount->isLessThan(BigDecimal::zero())) {
            throw new LogicException(
                'A Sales Invoice revenue or tax amount cannot be negative.',
            );
        }

        $lines[] = [
            'account_id' => $account->getKey(),
            'branch_id' => $branchId,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => $this->description($description),
            'due_date' => null,
            'debit_amount' => '0.000000',
            'credit_amount' => $this->decimal($amount),
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return $value
            ->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            )
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
                'Sales Invoice journal building must run inside a database transaction.',
            );
        }
    }
}