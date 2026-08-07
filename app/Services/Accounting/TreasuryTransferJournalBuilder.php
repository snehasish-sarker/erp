<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\TreasuryTransfer;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TreasuryTransferJournalBuilder
{
    private const SCALE = 6;

    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    /**
     * @return list<array{
     *     branch_id: int,
     *     posting_key: string,
     *     description: string,
     *     lines: list<array<string, mixed>>
     * }>
     */
    public function build(TreasuryTransfer $transfer): array
    {
        $this->requireTransaction();
        $transfer->loadMissing(['sourceAccount', 'destinationAccount']);

        $source = $transfer->sourceAccount;
        $destination = $transfer->destinationAccount;

        if (!$source instanceof Account || !$destination instanceof Account) {
            throw new LogicException('Treasury transfer accounts are unavailable.');
        }

        $amount = BigDecimal::of((string) $transfer->amount);
        $base = BigDecimal::of((string) $transfer->base_amount);
        $reference = (string) $transfer->transfer_number;

        if ((int) $transfer->source_branch_id === (int) $transfer->destination_branch_id) {
            return [[
                'branch_id' => (int) $transfer->source_branch_id,
                'posting_key' => $this->sameBranchPostingKey($transfer),
                'description' => "Treasury Transfer {$reference}",
                'lines' => [
                    $this->line(
                        account: $destination,
                        branchId: (int) $transfer->destination_branch_id,
                        reference: $reference,
                        description: "Treasury Transfer received from {$source->code}",
                        debit: $amount,
                        credit: BigDecimal::zero(),
                        baseDebit: $base,
                        baseCredit: BigDecimal::zero(),
                    ),
                    $this->line(
                        account: $source,
                        branchId: (int) $transfer->source_branch_id,
                        reference: $reference,
                        description: "Treasury Transfer sent to {$destination->code}",
                        debit: BigDecimal::zero(),
                        credit: $amount,
                        baseDebit: BigDecimal::zero(),
                        baseCredit: $base,
                    ),
                ],
            ]];
        }

        $clearing = $this->accountService->findSystemAccount('treasury_clearing', true);

        return [
            [
                'branch_id' => (int) $transfer->source_branch_id,
                'posting_key' => $this->sourcePostingKey($transfer),
                'description' => "Treasury Transfer {$reference} — source branch",
                'lines' => [
                    $this->line(
                        account: $clearing,
                        branchId: (int) $transfer->source_branch_id,
                        reference: $reference,
                        description: "Treasury clearing to destination branch",
                        debit: $amount,
                        credit: BigDecimal::zero(),
                        baseDebit: $base,
                        baseCredit: BigDecimal::zero(),
                    ),
                    $this->line(
                        account: $source,
                        branchId: (int) $transfer->source_branch_id,
                        reference: $reference,
                        description: "Treasury Transfer sent to destination branch",
                        debit: BigDecimal::zero(),
                        credit: $amount,
                        baseDebit: BigDecimal::zero(),
                        baseCredit: $base,
                    ),
                ],
            ],
            [
                'branch_id' => (int) $transfer->destination_branch_id,
                'posting_key' => $this->destinationPostingKey($transfer),
                'description' => "Treasury Transfer {$reference} — destination branch",
                'lines' => [
                    $this->line(
                        account: $destination,
                        branchId: (int) $transfer->destination_branch_id,
                        reference: $reference,
                        description: "Treasury Transfer received from source branch",
                        debit: $amount,
                        credit: BigDecimal::zero(),
                        baseDebit: $base,
                        baseCredit: BigDecimal::zero(),
                    ),
                    $this->line(
                        account: $clearing,
                        branchId: (int) $transfer->destination_branch_id,
                        reference: $reference,
                        description: "Treasury clearing from source branch",
                        debit: BigDecimal::zero(),
                        credit: $amount,
                        baseDebit: BigDecimal::zero(),
                        baseCredit: $base,
                    ),
                ],
            ],
        ];
    }

    public function sameBranchPostingKey(TreasuryTransfer $transfer): string
    {
        return sprintf('treasury_transfer:%d:journal:post', (int) $transfer->getKey());
    }

    public function sourcePostingKey(TreasuryTransfer $transfer): string
    {
        return sprintf('treasury_transfer:%d:journal:source:post', (int) $transfer->getKey());
    }

    public function destinationPostingKey(TreasuryTransfer $transfer): string
    {
        return sprintf('treasury_transfer:%d:journal:destination:post', (int) $transfer->getKey());
    }

    public function reversalKey(string $postingKey): string
    {
        return str_replace(':post', ':reverse', $postingKey);
    }

    /** @return array<string, mixed> */
    private function line(
        Account $account,
        int $branchId,
        string $reference,
        string $description,
        BigDecimal $debit,
        BigDecimal $credit,
        BigDecimal $baseDebit,
        BigDecimal $baseCredit,
    ): array {
        return [
            'account_id' => $account->getKey(),
            'branch_id' => $branchId,
            'supplier_id' => null,
            'customer_id' => null,
            'reference' => $reference,
            'description' => mb_substr($description, 0, 500),
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
            throw new LogicException('Treasury transfer journal building must run inside a transaction.');
        }
    }
}
