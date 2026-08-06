<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\CustomerReceiptAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptAllocation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Posts Customer Receipts to the General Ledger and Accounts Receivable
 * subledger atomically inside the source document transaction.
 */
final class GeneralLedgerCustomerReceiptAccountingGateway implements CustomerReceiptAccountingGateway
{
    public function __construct(
        private readonly CustomerReceiptJournalBuilder $journalBuilder,
        private readonly JournalEntryService $journalEntryService,
        private readonly CustomerReceiptAccountsReceivableService $accountsReceivableService,
    ) {
    }

    public function post(
        CustomerReceipt $customerReceipt,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $journalPayload =
            $this->journalBuilder
                ->buildPosting(
                    $customerReceipt,
                );

        $journalEntry =
            $this->journalEntryService
                ->postSystemJournal(
                    journalType:
                        'customer_receipt',

                    source:
                        $customerReceipt,

                    branchId:
                        (int) $customerReceipt
                            ->branch_id,

                    accountingPeriod:
                        $accountingPeriod,

                    documentDate:
                        $customerReceipt
                            ->receipt_date,

                    postingDate:
                        $customerReceipt
                            ->posting_date,

                    currencyCode:
                        (string) $customerReceipt
                            ->currency_code,

                    exchangeRate:
                        (string) $customerReceipt
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
                        $customerReceipt
                            ->receipt_number,
                );

        $journalNumber =
            $this->journalNumber(
                $journalEntry,
            );

        $customerLedgerEntry =
            $this->accountsReceivableService
                ->post(
                    customerReceipt:
                        $customerReceipt,

                    accountingPeriod:
                        $accountingPeriod,

                    journalReference:
                        $journalNumber,

                    actor:
                        $actor,
                );

        $this->ensureCustomerLedgerMatchesJournal(
            customerLedgerEntry:
                $customerLedgerEntry,

            journalNumber:
                $journalNumber,
        );

        $this->ensureJournalMatchesPayload(
            journalEntry:
                $journalEntry,

            journalPayload:
                $journalPayload,
        );

        $this->ensureAccountsReceivableMatchesPayload(
            customerReceipt:
                $customerReceipt,

            journalPayload:
                $journalPayload,
        );

        return $journalNumber;
    }

    public function reverse(
        CustomerReceipt $customerReceipt,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        $this->ensureInsideTransaction();

        $originalJournal =
            $this->lockOriginalJournal(
                $customerReceipt,
            );

        $reversalJournal =
            $this->journalEntryService
                ->reverseSystemJournal(
                    journalEntry:
                        $originalJournal,

                    source:
                        $customerReceipt,

                    accountingPeriod:
                        $accountingPeriod,

                    reversalPostingDate:
                        $reversalPostingDate,

                    reason:
                        $reason,

                    postingKey:
                        $this->journalBuilder
                            ->reversalPostingKey(
                                $customerReceipt,
                            ),

                    actor:
                        $actor,
                );

        $reversalJournalNumber =
            $this->journalNumber(
                $reversalJournal,
            );

        $customerLedgerReversal =
            $this->accountsReceivableService
                ->reverse(
                    customerReceipt:
                        $customerReceipt,

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

        $this->ensureCustomerLedgerMatchesJournal(
            customerLedgerEntry:
                $customerLedgerReversal,

            journalNumber:
                $reversalJournalNumber,
        );

        return $reversalJournalNumber;
    }

    private function lockOriginalJournal(
        CustomerReceipt $customerReceipt,
    ): JournalEntry {
        $journalEntry =
            JournalEntry::query()
                ->where(
                    'posting_key',
                    $this->journalBuilder
                        ->postingKey(
                            $customerReceipt,
                        ),
                )
                ->lockForUpdate()
                ->first();

        if (
            !$journalEntry
                instanceof JournalEntry
        ) {
            throw new LogicException(
                'The original Customer Receipt General Ledger journal is unavailable.',
            );
        }

        if (
            $journalEntry->journal_type
                !== 'customer_receipt'
            || $journalEntry->source_type
                !== $customerReceipt
                    ->getMorphClass()
            || (int) $journalEntry->source_id
                !== (int) $customerReceipt
                    ->getKey()
            || !$journalEntry->isPosted()
        ) {
            throw new LogicException(
                'The original Customer Receipt journal does not match the posted source document.',
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
     *         customer_receipt_allocation_id: int,
     *         amount: string,
     *         receivable_base_amount: string,
     *         receipt_base_amount: string,
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
                'The posted Customer Receipt journal does not match its validated posting payload.',
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
                    'A Customer Receipt journal line is missing.',
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
                    $actualLine->customer_id
                        !== null
                            ? (int) $actualLine
                                ->customer_id
                            : null
                ) !== (
                    $expectedLine[
                        'customer_id'
                    ] !== null
                        ? (int) $expectedLine[
                            'customer_id'
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
                    'A posted Customer Receipt journal line differs from the validated posting payload.',
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
     *         customer_receipt_allocation_id: int,
     *         amount: string,
     *         receivable_base_amount: string,
     *         receipt_base_amount: string,
     *         exchange_difference_amount: string
     *     }>
     * } $journalPayload
     */
    private function ensureAccountsReceivableMatchesPayload(
        CustomerReceipt $customerReceipt,
        array $journalPayload,
    ): void {
        $receipt =
            CustomerReceipt::query()
                ->whereKey(
                    $customerReceipt->getKey(),
                )
                ->lockForUpdate()
                ->firstOrFail();

        if (
            (string) $receipt
                ->base_total_amount
                !== $journalPayload[
                    'base_total_amount'
                ]
            || (string) $receipt
                ->base_allocated_amount
                !== $journalPayload[
                    'base_allocated_amount'
                ]
            || (string) $receipt
                ->base_unallocated_amount
                !== $journalPayload[
                    'base_unallocated_amount'
                ]
        ) {
            throw new LogicException(
                'The Customer Receipt base-currency totals do not match the General Ledger preview.',
            );
        }

        /**
         * @var Collection<int, CustomerReceiptAllocation>
         *     $actualAllocations
         */
        $actualAllocations =
            CustomerReceiptAllocation::query()
                ->where(
                    'customer_receipt_id',
                    $receipt->getKey(),
                )
                ->orderBy('line_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (
                        CustomerReceiptAllocation $allocation,
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
                'The Customer Receipt allocation count changed during posting.',
            );
        }

        foreach (
            $journalPayload['allocations']
            as $expectedAllocation
        ) {
            $actualAllocation =
                $actualAllocations->get(
                    $expectedAllocation[
                        'customer_receipt_allocation_id'
                    ],
                );

            if (
                !$actualAllocation
                    instanceof CustomerReceiptAllocation
                || !$actualAllocation
                    ->isApplied()
                || $actualAllocation
                    ->customer_open_item_allocation_id
                    === null
                || (string) $actualAllocation
                    ->amount
                    !== $expectedAllocation[
                        'amount'
                    ]
                || (string) $actualAllocation
                    ->receivable_base_amount
                    !== $expectedAllocation[
                        'receivable_base_amount'
                    ]
                || (string) $actualAllocation
                    ->receipt_base_amount
                    !== $expectedAllocation[
                        'receipt_base_amount'
                    ]
                || (string) $actualAllocation
                    ->exchange_difference_amount
                    !== $expectedAllocation[
                        'exchange_difference_amount'
                    ]
            ) {
                throw new LogicException(
                    'A Customer Receipt Accounts Receivable allocation differs from the General Ledger preview.',
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

    private function ensureCustomerLedgerMatchesJournal(
        CustomerLedgerEntry $customerLedgerEntry,
        string $journalNumber,
    ): void {
        if (
            trim(
                (string) $customerLedgerEntry
                    ->journal_reference,
            ) !== $journalNumber
        ) {
            throw new LogicException(
                'The Accounts Receivable customer ledger reference does not match the General Ledger journal number.',
            );
        }
    }

    private function ensureInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'Customer Receipt accounting must run inside the source document transaction.',
            );
        }
    }
}