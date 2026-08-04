<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\SupplierPaymentAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Posts Supplier Payments to the General Ledger and Accounts Payable
 * subledger atomically inside the source document transaction.
 */
final class GeneralLedgerSupplierPaymentAccountingGateway implements SupplierPaymentAccountingGateway
{
    public function __construct(
        private readonly SupplierPaymentJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
        private readonly SupplierPaymentAccountsPayableService $accountsPayableService,
    ) {
    }

    public function post(
        SupplierPayment $supplierPayment,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $journalPayload =
            $this->journalBuilder
                ->buildPosting(
                    $supplierPayment,
                );

        $journalEntry =
            $this->journalEntryService
                ->postSystemJournal(
                    journalType:
                        'supplier_payment',

                    source:
                        $supplierPayment,

                    branchId:
                        (int) $supplierPayment
                            ->branch_id,

                    accountingPeriod:
                        $accountingPeriod,

                    documentDate:
                        $supplierPayment
                            ->payment_date,

                    postingDate:
                        $supplierPayment
                            ->posting_date,

                    currencyCode:
                        (string) $supplierPayment
                            ->currency_code,

                    exchangeRate:
                        (string) $supplierPayment
                            ->exchange_rate,

                    description:
                        $journalPayload[
                            'description'
                        ],

                    lines:
                        $journalPayload[
                            'lines'
                        ],

                    postingKey:
                        $journalPayload[
                            'posting_key'
                        ],

                    actor:
                        $actor,

                    sourceDocumentNumber:
                        $supplierPayment
                            ->payment_number,
                );

        $journalNumber =
            $this->journalNumber(
                $journalEntry,
            );

        $supplierLedgerEntry =
            $this->accountsPayableService
                ->post(
                    supplierPayment:
                        $supplierPayment,

                    accountingPeriod:
                        $accountingPeriod,

                    journalReference:
                        $journalNumber,

                    actor:
                        $actor,
                );

        $this->ensureSupplierLedgerMatchesJournal(
            supplierLedgerEntry:
                $supplierLedgerEntry,

            journalNumber:
                $journalNumber,
        );

        $this->ensureJournalMatchesPayload(
            journalEntry:
                $journalEntry,

            journalPayload:
                $journalPayload,
        );

        $this->ensureAccountsPayableMatchesPayload(
            supplierPayment:
                $supplierPayment,

            journalPayload:
                $journalPayload,
        );

        return $journalNumber;
    }

    public function reverse(
        SupplierPayment $supplierPayment,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $originalJournal =
            $this->lockOriginalJournal(
                $supplierPayment,
            );

        $reversalJournal =
            $this->journalEntryService
                ->reverseSystemJournal(
                    journalEntry:
                        $originalJournal,

                    source:
                        $supplierPayment,

                    accountingPeriod:
                        $accountingPeriod,

                    reversalPostingDate:
                        $reversalPostingDate,

                    reason:
                        $reason,

                    postingKey:
                        $this->journalBuilder
                            ->reversalPostingKey(
                                $supplierPayment,
                            ),

                    actor:
                        $actor,
                );

        $reversalJournalNumber =
            $this->journalNumber(
                $reversalJournal,
            );

        $supplierLedgerReversal =
            $this->accountsPayableService
                ->reverse(
                    supplierPayment:
                        $supplierPayment,

                    accountingPeriod:
                        $accountingPeriod,

                    reversalPostingDate:
                        $reversalPostingDate,

                    journalReference:
                        $reversalJournalNumber,

                    reason:
                        $reason,

                    actor:
                        $actor,
                );

        $this->ensureSupplierLedgerMatchesJournal(
            supplierLedgerEntry:
                $supplierLedgerReversal,

            journalNumber:
                $reversalJournalNumber,
        );

        return $reversalJournalNumber;
    }

    private function lockOriginalJournal(
        SupplierPayment $supplierPayment,
    ): JournalEntry {
        $journalEntry =
            JournalEntry::query()
                ->where(
                    'posting_key',
                    $this->journalBuilder
                        ->postingKey(
                            $supplierPayment,
                        ),
                )
                ->lockForUpdate()
                ->first();

        if (
            !$journalEntry
                instanceof JournalEntry
        ) {
            throw new LogicException(
                'The original Supplier Payment General Ledger journal is unavailable.',
            );
        }

        if (
            $journalEntry->journal_type
                !== 'supplier_payment'
            || $journalEntry->source_type
                !== $supplierPayment
                    ->getMorphClass()
            || (int) $journalEntry->source_id
                !== (int) $supplierPayment
                    ->getKey()
            || !$journalEntry->isPosted()
        ) {
            throw new LogicException(
                'The original Supplier Payment journal does not match the posted source document.',
            );
        }

        return $journalEntry;
    }

    /**
     * @param array{
     *     total_amount: string,
     *     base_total_amount: string,
     *     base_allocated_amount: string,
     *     base_unallocated_amount: string,
     *     lines: list<array<string, mixed>>,
     *     allocations: list<array{
     *         supplier_payment_allocation_id: int,
     *         amount: string,
     *         payable_base_amount: string,
     *         credit_base_amount: string,
     *         exchange_difference_amount: string
     *     }>
     * } $journalPayload
     */
    private function ensureJournalMatchesPayload(
        JournalEntry $journalEntry,
        array $journalPayload,
    ): void {
        $journalEntry->loadMissing(
            'lines',
        );

        if (
            !$journalEntry->isPosted()
            || (string) $journalEntry
                ->total_debit
                !== $journalPayload[
                    'total_amount'
                ]
            || (string) $journalEntry
                ->total_credit
                !== $journalPayload[
                    'total_amount'
                ]
            || (string) $journalEntry
                ->base_total_debit
                !== (string) $journalEntry
                    ->base_total_credit
            || $journalEntry->lines->count()
                !== count(
                    $journalPayload['lines'],
                )
        ) {
            throw new LogicException(
                'The posted Supplier Payment journal does not match its validated posting payload.',
            );
        }

        foreach (
            $journalPayload['lines']
            as $index => $expectedLine
        ) {
            $actualLine =
                $journalEntry->lines->get(
                    $index,
                );

            if (
                !$actualLine
                    instanceof JournalEntryLine
            ) {
                throw new LogicException(
                    'A Supplier Payment journal line is missing.',
                );
            }

            if (
                (int) $actualLine->account_id
                    !== (int) $expectedLine[
                        'account_id'
                    ]
                || (int) $actualLine
                    ->branch_id
                    !== (int) $expectedLine[
                        'branch_id'
                    ]
                || (
                    $actualLine->supplier_id
                        !== null
                            ? (int) $actualLine
                                ->supplier_id
                            : null
                ) !== (
                    $expectedLine[
                        'supplier_id'
                    ] !== null
                        ? (int) $expectedLine[
                            'supplier_id'
                        ]
                        : null
                )
                || (string) $actualLine
                    ->debit_amount
                    !== (string) $expectedLine[
                        'debit_amount'
                    ]
                || (string) $actualLine
                    ->credit_amount
                    !== (string) $expectedLine[
                        'credit_amount'
                    ]
                || (string) $actualLine
                    ->base_debit_amount
                    !== (string) $expectedLine[
                        'base_debit_amount'
                    ]
                || (string) $actualLine
                    ->base_credit_amount
                    !== (string) $expectedLine[
                        'base_credit_amount'
                    ]
            ) {
                throw new LogicException(
                    'A posted Supplier Payment journal line differs from the validated posting payload.',
                );
            }
        }
    }

    /**
     * @param array{
     *     base_total_amount: string,
     *     base_allocated_amount: string,
     *     base_unallocated_amount: string,
     *     allocations: list<array{
     *         supplier_payment_allocation_id: int,
     *         amount: string,
     *         payable_base_amount: string,
     *         credit_base_amount: string,
     *         exchange_difference_amount: string
     *     }>
     * } $journalPayload
     */
    private function ensureAccountsPayableMatchesPayload(
        SupplierPayment $supplierPayment,
        array $journalPayload,
    ): void {
        $payment =
            SupplierPayment::query()
                ->whereKey(
                    $supplierPayment->getKey(),
                )
                ->lockForUpdate()
                ->firstOrFail();

        if (
            (string) $payment
                ->base_total_amount
                !== $journalPayload[
                    'base_total_amount'
                ]
            || (string) $payment
                ->base_allocated_amount
                !== $journalPayload[
                    'base_allocated_amount'
                ]
            || (string) $payment
                ->base_unallocated_amount
                !== $journalPayload[
                    'base_unallocated_amount'
                ]
        ) {
            throw new LogicException(
                'The Supplier Payment base-currency totals do not match the General Ledger preview.',
            );
        }

        /**
         * @var Collection<int, SupplierPaymentAllocation>
         *     $actualAllocations
         */
        $actualAllocations =
            SupplierPaymentAllocation::query()
                ->where(
                    'supplier_payment_id',
                    $payment->getKey(),
                )
                ->orderBy('line_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        SupplierPaymentAllocation $allocation,
                    ): int => (int) $allocation
                        ->getKey(),
                );

        if (
            $actualAllocations->count()
            !== count(
                $journalPayload[
                    'allocations'
                ],
            )
        ) {
            throw new LogicException(
                'The Supplier Payment allocation count changed during posting.',
            );
        }

        foreach (
            $journalPayload['allocations']
            as $expectedAllocation
        ) {
            $actualAllocation =
                $actualAllocations->get(
                    $expectedAllocation[
                        'supplier_payment_allocation_id'
                    ],
                );

            if (
                !$actualAllocation
                    instanceof SupplierPaymentAllocation
                || !$actualAllocation
                    ->isApplied()
                || $actualAllocation
                    ->supplier_open_item_allocation_id
                    === null
                || (string) $actualAllocation
                    ->amount
                    !== $expectedAllocation[
                        'amount'
                    ]
                || (string) $actualAllocation
                    ->payable_base_amount
                    !== $expectedAllocation[
                        'payable_base_amount'
                    ]
                || (string) $actualAllocation
                    ->credit_base_amount
                    !== $expectedAllocation[
                        'credit_base_amount'
                    ]
                || (string) $actualAllocation
                    ->exchange_difference_amount
                    !== $expectedAllocation[
                        'exchange_difference_amount'
                    ]
            ) {
                throw new LogicException(
                    'A Supplier Payment Accounts Payable allocation differs from the General Ledger preview.',
                );
            }
        }
    }

    private function journalNumber(
        JournalEntry $journalEntry,
    ): string {
        $journalNumber = trim(
            (string) $journalEntry
                ->journal_number,
        );

        if ($journalNumber === '') {
            throw new LogicException(
                'The posted General Ledger journal does not have a journal number.',
            );
        }

        return $journalNumber;
    }

    private function ensureSupplierLedgerMatchesJournal(
        SupplierLedgerEntry $supplierLedgerEntry,
        string $journalNumber,
    ): void {
        if (
            trim(
                (string) $supplierLedgerEntry
                    ->journal_reference,
            ) !== $journalNumber
        ) {
            throw new LogicException(
                'The Accounts Payable supplier ledger reference does not match the General Ledger journal number.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Supplier Payment accounting must run inside the source document transaction.',
            );
        }
    }
}