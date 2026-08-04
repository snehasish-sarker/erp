<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Events\Accounting\JournalEntryPosted;
use App\Events\Accounting\JournalEntryReversed;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Auditing\AuditLogService;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Accounting\GeneralLedgerRegistry;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class JournalEntryService
{
    /**
     * @var array<string, string>
     */
    private const REVERSAL_TYPES = [
        'manual' => 'adjustment_reversal',
        'adjustment' => 'adjustment_reversal',
        'supplier_invoice' => 'supplier_invoice_reversal',
        'supplier_debit_note' => 'supplier_debit_note_reversal',
        'supplier_payment' => 'supplier_payment_reversal',
        'inventory' => 'inventory_reversal',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly JournalEntryValidationService $validationService,
        private readonly GeneralLedgerRegistry $registry,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array{
     *     branch_id: int|string,
     *     document_date: DateTimeInterface|string,
     *     posting_date: DateTimeInterface|string,
     *     currency_code: string,
     *     exchange_rate: string|int|float,
     *     description: string,
     *     lines: list<array<string, mixed>>
     * } $data
     */
    public function createDraft(
        array $data,
        User $actor,
    ): JournalEntry {
        $this->ensureActorBelongsToTenant($actor);

        return DB::transaction(
            function () use ($data, $actor): JournalEntry {
                $normalized = $this->normalizeManualJournalData(
                    data: $data,
                    actor: $actor,
                    requireActiveBranch: true,
                );

                $journalEntry = JournalEntry::query()->create([
                    'branch_id' => $normalized['branch']->getKey(),
                    'accounting_period_id' =>
                        $normalized['accounting_period']->getKey(),
                    'document_number_allocation_id' => null,
                    'journal_number' => null,
                    'posting_key' => null,
                    'journal_type' => 'manual',
                    'status' => 'draft',
                    'source_type' => null,
                    'source_id' => null,
                    'source_document_number' => null,
                    'document_date' => $normalized['document_date'],
                    'posting_date' => $normalized['posting_date'],
                    'currency_code' =>
                        $normalized['validated']['currency_code'],
                    'exchange_rate' =>
                        $normalized['validated']['exchange_rate'],
                    'total_debit' =>
                        $normalized['validated']['total_debit'],
                    'total_credit' =>
                        $normalized['validated']['total_credit'],
                    'base_total_debit' =>
                        $normalized['validated']['base_total_debit'],
                    'base_total_credit' =>
                        $normalized['validated']['base_total_credit'],
                    'description' => $normalized['description'],
                    'prepared_by_user_id' => $actor->getKey(),
                    'approved_by_user_id' => null,
                    'approved_at' => null,
                    'posted_by_user_id' => null,
                    'posted_at' => null,
                    'reversal_of_id' => null,
                    'reversal_reason' => null,
                    'cancelled_by_user_id' => null,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                ]);

                $this->replaceLines(
                    journalEntry: $journalEntry,
                    lines: $normalized['validated']['lines'],
                );

                return $this->loadJournal(
                    $journalEntry->refresh(),
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array{
     *     branch_id: int|string,
     *     document_date: DateTimeInterface|string,
     *     posting_date: DateTimeInterface|string,
     *     currency_code: string,
     *     exchange_rate: string|int|float,
     *     description: string,
     *     lines: list<array<string, mixed>>
     * } $data
     */
    public function updateDraft(
        JournalEntry $journalEntry,
        array $data,
        User $actor,
    ): JournalEntry {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureJournalBelongsToTenant($journalEntry);

        return DB::transaction(
            function () use (
                $journalEntry,
                $data,
                $actor,
            ): JournalEntry {
                $lockedJournal = $this->lockJournal(
                    $journalEntry,
                );

                $this->authorizeJournalBranch(
                    actor: $actor,
                    journalEntry: $lockedJournal,
                    requireActive: false,
                );

                $this->ensureManualJournal($lockedJournal);

                if (!$lockedJournal->isDraft()) {
                    throw ValidationException::withMessages([
                        'journal_entry' => [
                            'Only a draft journal entry can be updated.',
                        ],
                    ]);
                }

                $normalized = $this->normalizeManualJournalData(
                    data: $data,
                    actor: $actor,
                    requireActiveBranch: true,
                );

                if (
                    $lockedJournal->hasJournalNumber()
                    && (
                        (int) $lockedJournal->branch_id
                            !== (int) $normalized['branch']->getKey()
                        || $lockedJournal->posting_date->toDateString()
                            !== $normalized['posting_date']
                    )
                ) {
                    throw ValidationException::withMessages([
                        'posting_date' => [
                            'The branch and posting date cannot be changed after a journal number has been allocated.',
                        ],
                    ]);
                }

                $lockedJournal->branch_id =
                    $normalized['branch']->getKey();

                $lockedJournal->accounting_period_id =
                    $normalized['accounting_period']->getKey();

                $lockedJournal->document_date =
                    $normalized['document_date'];

                $lockedJournal->posting_date =
                    $normalized['posting_date'];

                $lockedJournal->currency_code =
                    $normalized['validated']['currency_code'];

                $lockedJournal->exchange_rate =
                    $normalized['validated']['exchange_rate'];

                $lockedJournal->total_debit =
                    $normalized['validated']['total_debit'];

                $lockedJournal->total_credit =
                    $normalized['validated']['total_credit'];

                $lockedJournal->base_total_debit =
                    $normalized['validated']['base_total_debit'];

                $lockedJournal->base_total_credit =
                    $normalized['validated']['base_total_credit'];

                $lockedJournal->description =
                    $normalized['description'];

                $lockedJournal->prepared_by_user_id =
                    $actor->getKey();

                $lockedJournal->save();

                $this->replaceLines(
                    journalEntry: $lockedJournal,
                    lines: $normalized['validated']['lines'],
                );

                return $this->loadJournal(
                    $lockedJournal->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function deleteDraft(
        JournalEntry $journalEntry,
        User $actor,
    ): void {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureJournalBelongsToTenant($journalEntry);

        DB::transaction(
            function () use (
                $journalEntry,
                $actor,
            ): void {
                $lockedJournal = $this->lockJournal(
                    $journalEntry,
                );

                $this->authorizeJournalBranch(
                    actor: $actor,
                    journalEntry: $lockedJournal,
                    requireActive: false,
                );

                $this->ensureManualJournal($lockedJournal);

                if (!$lockedJournal->canBeDeleted()) {
                    throw ValidationException::withMessages([
                        'journal_entry' => [
                            'Only an unnumbered draft journal entry can be deleted.',
                        ],
                    ]);
                }

                JournalEntryLine::query()
                    ->where(
                        'journal_entry_id',
                        $lockedJournal->getKey(),
                    )
                    ->delete();

                $lockedJournal->delete();
            },
            attempts: 5,
        );
    }

    public function approve(
        JournalEntry $journalEntry,
        User $actor,
    ): JournalEntry {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureJournalBelongsToTenant($journalEntry);

        return DB::transaction(
            function () use (
                $journalEntry,
                $actor,
            ): JournalEntry {
                $lockedJournal = $this->lockJournal(
                    journalEntry: $journalEntry,
                    withLines: true,
                );

                $this->authorizeJournalBranch(
                    actor: $actor,
                    journalEntry: $lockedJournal,
                    requireActive: true,
                );

                $this->ensureManualJournal($lockedJournal);

                if (
                    !$this->registry->canApprove(
                        $lockedJournal->status,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'journal_entry' => [
                            'Only a draft journal entry can be approved.',
                        ],
                    ]);
                }

                $period = $this->accountingPeriodService
                    ->lockOpenPeriod(
                        $lockedJournal->posting_date,
                    );

                $validated = $this->validatePersistedJournal(
                    journalEntry: $lockedJournal,
                    manualPosting: true,
                    requireActiveAccounts: true,
                );

                $oldValues = $lockedJournal->only([
                    'status',
                    'journal_number',
                    'approved_by_user_id',
                    'approved_at',
                ]);

                $this->allocateJournalNumber(
                    journalEntry: $lockedJournal,
                    postingDate: $lockedJournal->posting_date,
                );

                $lockedJournal->accounting_period_id =
                    $period->getKey();

                $this->applyTotals(
                    journalEntry: $lockedJournal,
                    validated: $validated,
                );

                $lockedJournal->status = 'approved';

                $lockedJournal->approved_by_user_id =
                    $actor->getKey();

                $lockedJournal->approved_at = now();

                $lockedJournal->saveQuietly();

                $this->auditLogService->recordCustomEvent(
                    subject: $lockedJournal,
                    event: 'approved',
                    oldValues: $oldValues,
                    newValues: $lockedJournal->only([
                        'status',
                        'journal_number',
                        'approved_by_user_id',
                        'approved_at',
                    ]),
                    metadata: [
                        'actor_id' => $actor->getKey(),
                    ],
                );

                return $this->loadJournal(
                    $lockedJournal->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function returnToDraft(
        JournalEntry $journalEntry,
        User $actor,
    ): JournalEntry {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureJournalBelongsToTenant($journalEntry);

        return DB::transaction(
            function () use (
                $journalEntry,
                $actor,
            ): JournalEntry {
                $lockedJournal = $this->lockJournal(
                    $journalEntry,
                );

                $this->authorizeJournalBranch(
                    actor: $actor,
                    journalEntry: $lockedJournal,
                    requireActive: false,
                );

                $this->ensureManualJournal($lockedJournal);

                if (
                    !$this->registry->canReturnToDraft(
                        $lockedJournal->status,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'journal_entry' => [
                            'Only an approved journal entry can be returned to draft.',
                        ],
                    ]);
                }

                $oldValues = $lockedJournal->only([
                    'status',
                    'approved_by_user_id',
                    'approved_at',
                ]);

                $lockedJournal->status = 'draft';
                $lockedJournal->approved_by_user_id = null;
                $lockedJournal->approved_at = null;
                $lockedJournal->saveQuietly();

                $this->auditLogService->recordCustomEvent(
                    subject: $lockedJournal,
                    event: 'returned_to_draft',
                    oldValues: $oldValues,
                    newValues: $lockedJournal->only([
                        'status',
                        'approved_by_user_id',
                        'approved_at',
                    ]),
                    metadata: [
                        'actor_id' => $actor->getKey(),
                    ],
                );

                return $this->loadJournal(
                    $lockedJournal->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function cancel(
        JournalEntry $journalEntry,
        string $reason,
        User $actor,
    ): JournalEntry {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureJournalBelongsToTenant($journalEntry);

        $reason = $this->normalizeReason(
            reason: $reason,
            field: 'cancellation_reason',
        );

        return DB::transaction(
            function () use (
                $journalEntry,
                $reason,
                $actor,
            ): JournalEntry {
                $lockedJournal = $this->lockJournal(
                    $journalEntry,
                );

                $this->authorizeJournalBranch(
                    actor: $actor,
                    journalEntry: $lockedJournal,
                    requireActive: false,
                );

                $this->ensureManualJournal($lockedJournal);

                if (
                    !$this->registry->canCancel(
                        $lockedJournal->status,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'journal_entry' => [
                            'Only a draft or approved journal entry can be cancelled.',
                        ],
                    ]);
                }

                $oldValues = $lockedJournal->only([
                    'status',
                    'cancelled_by_user_id',
                    'cancelled_at',
                    'cancellation_reason',
                ]);

                $lockedJournal->status = 'cancelled';

                $lockedJournal->cancelled_by_user_id =
                    $actor->getKey();

                $lockedJournal->cancelled_at = now();

                $lockedJournal->cancellation_reason = $reason;

                $lockedJournal->saveQuietly();

                $this->auditLogService->recordCustomEvent(
                    subject: $lockedJournal,
                    event: 'cancelled',
                    oldValues: $oldValues,
                    newValues: $lockedJournal->only([
                        'status',
                        'cancelled_by_user_id',
                        'cancelled_at',
                        'cancellation_reason',
                    ]),
                    metadata: [
                        'actor_id' => $actor->getKey(),
                    ],
                );

                return $this->loadJournal(
                    $lockedJournal->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function post(
        JournalEntry $journalEntry,
        User $actor,
    ): JournalEntry {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureJournalBelongsToTenant($journalEntry);

        return DB::transaction(
            function () use (
                $journalEntry,
                $actor,
            ): JournalEntry {
                $lockedJournal = $this->lockJournal(
                    journalEntry: $journalEntry,
                    withLines: true,
                );

                $this->authorizeJournalBranch(
                    actor: $actor,
                    journalEntry: $lockedJournal,
                    requireActive: true,
                );

                $this->ensureManualJournal($lockedJournal);

                if (
                    !$this->registry->canPost(
                        $lockedJournal->status,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'journal_entry' => [
                            'Only an approved journal entry can be posted.',
                        ],
                    ]);
                }

                if (!$lockedJournal->hasJournalNumber()) {
                    throw new LogicException(
                        'An approved journal entry must have a journal number before posting.',
                    );
                }

                $period = $this->accountingPeriodService
                    ->lockOpenPeriod(
                        $lockedJournal->posting_date,
                    );

                $validated = $this->validatePersistedJournal(
                    journalEntry: $lockedJournal,
                    manualPosting: true,
                    requireActiveAccounts: true,
                );

                $oldValues = $lockedJournal->only([
                    'status',
                    'posted_by_user_id',
                    'posted_at',
                ]);

                $lockedJournal->accounting_period_id =
                    $period->getKey();

                $this->applyTotals(
                    journalEntry: $lockedJournal,
                    validated: $validated,
                );

                $lockedJournal->status = 'posted';

                $lockedJournal->posted_by_user_id =
                    $actor->getKey();

                $lockedJournal->posted_at = now();

                $lockedJournal->saveQuietly();

                $this->auditLogService->recordCustomEvent(
                    subject: $lockedJournal,
                    event: 'posted',
                    oldValues: $oldValues,
                    newValues: $lockedJournal->only([
                        'status',
                        'posted_by_user_id',
                        'posted_at',
                    ]),
                    metadata: [
                        'actor_id' => $actor->getKey(),
                        'journal_number' =>
                            $lockedJournal->journal_number,
                    ],
                );

                JournalEntryPosted::dispatch(
                    tenantId:
                        (int) $lockedJournal->tenant_id,

                    journalEntryId:
                        (int) $lockedJournal->getKey(),

                    branchId:
                        (int) $lockedJournal->branch_id,

                    journalType:
                        $lockedJournal->journal_type,

                    actorId:
                        (int) $actor->getKey(),
                );

                return $this->loadJournal(
                    $lockedJournal->refresh(),
                );
            },
            attempts: 5,
        );
    }

    /**
     * Create and post a non-manual journal inside an existing transaction.
     *
     * The caller must create the related operational subledger record in the
     * same transaction. This is the API used by accounting gateways.
     *
     * @param list<array<string, mixed>> $lines
     */
    public function postSystemJournal(
        string $journalType,
        Model $source,
        int $branchId,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $documentDate,
        DateTimeInterface $postingDate,
        string $currencyCode,
        string $exchangeRate,
        string $description,
        array $lines,
        string $postingKey,
        User $actor,
        ?string $sourceDocumentNumber = null,
    ): JournalEntry {
        $this->requireTransaction();

        $this->ensureActorBelongsToTenant($actor);

        $this->ensureSourceBelongsToTenant($source);

        $journalType = trim($journalType);

        if (
            !$this->registry->isJournalType($journalType)
            || $journalType === 'manual'
            || str_ends_with(
                $journalType,
                '_reversal',
            )
        ) {
            throw new LogicException(
                "Unsupported system journal type [{$journalType}].",
            );
        }

        $postingKey = $this->normalizePostingKey(
            $postingKey,
        );

        $description = $this->normalizeDescription(
            $description,
        );

        $sourceDocumentNumber = $this->nullableText(
            value: $sourceDocumentNumber,
            maxLength: 160,
            field: 'source_document_number',
        );

        $branch = $this->accessibleBranch(
            actor: $actor,
            branchId: $branchId,
            requireActive: true,
        );

        $sourceBranchId = $source->getAttribute(
            'branch_id',
        );

        if (
            $sourceBranchId !== null
            && (int) $sourceBranchId
                !== (int) $branch->getKey()
        ) {
            throw new LogicException(
                'The accounting source branch does not match the journal branch.',
            );
        }

        $normalizedPostingDate = $this->normalizeDate(
            value: $postingDate,
            field: 'posting_date',
        );

        $normalizedDocumentDate = $this->normalizeDate(
            value: $documentDate,
            field: 'document_date',
        );

        $lockedPeriod = $this->lockSuppliedOpenPeriod(
            accountingPeriod: $accountingPeriod,
            postingDate: $normalizedPostingDate,
        );

        $existing = JournalEntry::query()
            ->where(
                'posting_key',
                $postingKey,
            )
            ->lockForUpdate()
            ->first();

        if ($existing instanceof JournalEntry) {
            $this->ensureIdempotentSystemJournalMatches(
                journalEntry: $existing,
                journalType: $journalType,
                source: $source,
                branch: $branch,
                postingDate: $normalizedPostingDate,
                postingKey: $postingKey,
            );

            return $this->loadJournal(
                $existing,
            );
        }

        $validated = $this->validationService
            ->validateAndNormalize(
                lines: $lines,
                branch: $branch,
                currencyCode: $currencyCode,
                exchangeRate: $exchangeRate,
                manualPosting: false,
                requireActiveAccounts: true,
            );

        $journalEntry = JournalEntry::query()->create([
            'branch_id' => $branch->getKey(),

            'accounting_period_id' =>
                $lockedPeriod->getKey(),

            'document_number_allocation_id' => null,
            'journal_number' => null,
            'posting_key' => $postingKey,
            'journal_type' => $journalType,
            'status' => 'draft',

            'source_type' =>
                $source->getMorphClass(),

            'source_id' =>
                $source->getKey(),

            'source_document_number' =>
                $sourceDocumentNumber,

            'document_date' =>
                $normalizedDocumentDate,

            'posting_date' =>
                $normalizedPostingDate,

            'currency_code' =>
                $validated['currency_code'],

            'exchange_rate' =>
                $validated['exchange_rate'],

            'total_debit' =>
                $validated['total_debit'],

            'total_credit' =>
                $validated['total_credit'],

            'base_total_debit' =>
                $validated['base_total_debit'],

            'base_total_credit' =>
                $validated['base_total_credit'],

            'description' => $description,

            'prepared_by_user_id' =>
                $actor->getKey(),

            'approved_by_user_id' =>
                $actor->getKey(),

            'approved_at' => now(),

            'posted_by_user_id' => null,
            'posted_at' => null,
            'reversal_of_id' => null,
            'reversal_reason' => null,
            'cancelled_by_user_id' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        $this->replaceLines(
            journalEntry: $journalEntry,
            lines: $validated['lines'],
        );

        $this->allocateJournalNumber(
            journalEntry: $journalEntry,
            postingDate: $normalizedPostingDate,
            allocationKey:
                $this->allocationKey(
                    $postingKey,
                ),
        );

        $journalEntry->status = 'posted';

        $journalEntry->posted_by_user_id =
            $actor->getKey();

        $journalEntry->posted_at = now();

        $journalEntry->saveQuietly();

        $this->auditLogService->recordCustomEvent(
            subject: $journalEntry,
            event: 'system_posted',
            newValues: $journalEntry->only([
                'journal_number',
                'posting_key',
                'journal_type',
                'status',
                'source_type',
                'source_id',
                'total_debit',
                'total_credit',
                'base_total_debit',
                'base_total_credit',
                'posted_by_user_id',
                'posted_at',
            ]),
            metadata: [
                'actor_id' => $actor->getKey(),
            ],
        );

        JournalEntryPosted::dispatch(
            tenantId:
                (int) $journalEntry->tenant_id,

            journalEntryId:
                (int) $journalEntry->getKey(),

            branchId:
                (int) $journalEntry->branch_id,

            journalType:
                $journalEntry->journal_type,

            actorId:
                (int) $actor->getKey(),
        );

        return $this->loadJournal(
            $journalEntry->refresh(),
        );
    }

    public function reverseManualJournal(
        JournalEntry $journalEntry,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): JournalEntry {
        $this->ensureActorBelongsToTenant($actor);

        $this->ensureJournalBelongsToTenant(
            $journalEntry,
        );

        $reason = $this->normalizeReason(
            reason: $reason,
            field: 'reversal_reason',
        );

        return DB::transaction(
            function () use (
                $journalEntry,
                $reversalPostingDate,
                $reason,
                $actor,
            ): JournalEntry {
                $lockedJournal = $this->lockJournal(
                    journalEntry: $journalEntry,
                    withLines: true,
                );

                $this->authorizeJournalBranch(
                    actor: $actor,
                    journalEntry: $lockedJournal,
                    requireActive: false,
                );

                $this->ensureManualJournal(
                    $lockedJournal,
                );

                $normalizedDate = $this->normalizeDate(
                    value: $reversalPostingDate,
                    field: 'reversal_posting_date',
                );

                $period = $this->accountingPeriodService
                    ->lockOpenPeriod(
                        CarbonImmutable::parse(
                            $normalizedDate,
                            $this->tenantContext
                                ->tenant()
                                ->timezone,
                        ),
                    );

                return $this->reverseLockedJournal(
                    original: $lockedJournal,
                    accountingPeriod: $period,
                    reversalPostingDate:
                        $normalizedDate,
                    reason: $reason,
                    postingKey: sprintf(
                        'journal-reversal:%d',
                        $lockedJournal->getKey(),
                    ),
                    actor: $actor,
                );
            },
            attempts: 5,
        );
    }

    /**
     * Reverse a system journal inside the source workflow transaction.
     */
    public function reverseSystemJournal(
        JournalEntry $journalEntry,
        Model $source,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        string $postingKey,
        User $actor,
    ): JournalEntry {
        $this->requireTransaction();

        $this->ensureActorBelongsToTenant($actor);

        $this->ensureJournalBelongsToTenant(
            $journalEntry,
        );

        $this->ensureSourceBelongsToTenant(
            $source,
        );

        $reason = $this->normalizeReason(
            reason: $reason,
            field: 'reversal_reason',
        );

        $postingKey = $this->normalizePostingKey(
            $postingKey,
        );

        $lockedJournal = $this->lockJournal(
            journalEntry: $journalEntry,
            withLines: true,
        );

        if (
            $lockedJournal->source_type
                !== $source->getMorphClass()
            || (int) $lockedJournal->source_id
                !== (int) $source->getKey()
        ) {
            throw new LogicException(
                'The journal entry does not belong to the supplied source document.',
            );
        }

        if (
            $lockedJournal->journal_type === 'manual'
            || str_ends_with(
                $lockedJournal->journal_type,
                '_reversal',
            )
        ) {
            throw new LogicException(
                'The selected journal entry is not a reversible system source journal.',
            );
        }

        $normalizedDate = $this->normalizeDate(
            value: $reversalPostingDate,
            field: 'reversal_posting_date',
        );

        $lockedPeriod = $this->lockSuppliedOpenPeriod(
            accountingPeriod: $accountingPeriod,
            postingDate: $normalizedDate,
        );

        return $this->reverseLockedJournal(
            original: $lockedJournal,
            accountingPeriod: $lockedPeriod,
            reversalPostingDate: $normalizedDate,
            reason: $reason,
            postingKey: $postingKey,
            actor: $actor,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     branch: Branch,
     *     accounting_period: AccountingPeriod,
     *     document_date: string,
     *     posting_date: string,
     *     description: string,
     *     validated: array{
     *         currency_code: string,
     *         exchange_rate: string,
     *         lines: list<array<string, mixed>>,
     *         total_debit: string,
     *         total_credit: string,
     *         base_total_debit: string,
     *         base_total_credit: string
     *     }
     * }
     */
    private function normalizeManualJournalData(
        array $data,
        User $actor,
        bool $requireActiveBranch,
    ): array {
        $branchId = filter_var(
            $data['branch_id'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        if ($branchId === false) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'The selected branch is invalid.',
                ],
            ]);
        }

        $branch = $this->accessibleBranch(
            actor: $actor,
            branchId: (int) $branchId,
            requireActive: $requireActiveBranch,
        );

        $documentDate = $this->normalizeDate(
            value: $data['document_date'] ?? null,
            field: 'document_date',
        );

        $postingDate = $this->normalizeDate(
            value: $data['posting_date'] ?? null,
            field: 'posting_date',
        );

        $accountingPeriod =
            $this->accountingPeriodService
                ->lockOpenPeriod(
                    CarbonImmutable::parse(
                        $postingDate,
                        $this->tenantContext
                            ->tenant()
                            ->timezone,
                    ),
                );

        $description = $this->normalizeDescription(
            (string) (
                $data['description'] ?? ''
            ),
        );

        $lines = $data['lines'] ?? null;

        if (!is_array($lines)) {
            throw ValidationException::withMessages([
                'lines' => [
                    'Journal lines are required.',
                ],
            ]);
        }

        /** @var list<array<string, mixed>> $lines */
        $validated = $this->validationService
            ->validateAndNormalize(
                lines: $lines,
                branch: $branch,
                currencyCode: (string) (
                    $data['currency_code'] ?? ''
                ),
                exchangeRate: (string) (
                    $data['exchange_rate'] ?? ''
                ),
                manualPosting: true,
                requireActiveAccounts: true,
            );

        return [
            'branch' => $branch,

            'accounting_period' =>
                $accountingPeriod,

            'document_date' =>
                $documentDate,

            'posting_date' =>
                $postingDate,

            'description' =>
                $description,

            'validated' =>
                $validated,
        ];
    }

    /**
     * @return array{
     *     currency_code: string,
     *     exchange_rate: string,
     *     lines: list<array<string, mixed>>,
     *     total_debit: string,
     *     total_credit: string,
     *     base_total_debit: string,
     *     base_total_credit: string
     * }
     */
    private function validatePersistedJournal(
        JournalEntry $journalEntry,
        bool $manualPosting,
        bool $requireActiveAccounts,
    ): array {
        $journalEntry->loadMissing([
            'branch',
            'lines',
        ]);

        if (
            !$journalEntry->branch
                instanceof Branch
        ) {
            throw new LogicException(
                'The journal branch is unavailable.',
            );
        }

        $lines = [];

        foreach (
            $journalEntry->lines
            as $line
        ) {
            $lines[] = [
                'account_id' =>
                    $line->account_id,

                'branch_id' =>
                    $line->branch_id,

                'supplier_id' =>
                    $line->supplier_id,

                'customer_id' =>
                    $line->customer_id,

                'reference' =>
                    $line->reference,

                'description' =>
                    $line->description,

                'due_date' =>
                    $line->due_date,

                'currency_code' =>
                    $line->currency_code,

                'exchange_rate' =>
                    $line->exchange_rate,

                'debit_amount' =>
                    $line->debit_amount,

                'credit_amount' =>
                    $line->credit_amount,
            ];
        }

        return $this->validationService
            ->validateAndNormalize(
                lines: $lines,
                branch: $journalEntry->branch,
                currencyCode:
                    $journalEntry->currency_code,
                exchangeRate:
                    $journalEntry->exchange_rate,
                manualPosting: $manualPosting,
                requireActiveAccounts:
                    $requireActiveAccounts,
            );
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function replaceLines(
        JournalEntry $journalEntry,
        array $lines,
    ): void {
        JournalEntryLine::query()
            ->where(
                'journal_entry_id',
                $journalEntry->getKey(),
            )
            ->delete();

        foreach ($lines as $line) {
            JournalEntryLine::query()->create([
                'journal_entry_id' =>
                    $journalEntry->getKey(),

                'line_number' =>
                    $line['line_number'],

                'account_id' =>
                    $line['account_id'],

                'branch_id' =>
                    $line['branch_id'],

                'supplier_id' =>
                    $line['supplier_id'],

                'customer_id' =>
                    $line['customer_id'],

                'reference' =>
                    $line['reference'],

                'description' =>
                    $line['description'],

                'due_date' =>
                    $line['due_date'],

                'currency_code' =>
                    $line['currency_code'],

                'exchange_rate' =>
                    $line['exchange_rate'],

                'debit_amount' =>
                    $line['debit_amount'],

                'credit_amount' =>
                    $line['credit_amount'],

                'base_debit_amount' =>
                    $line['base_debit_amount'],

                'base_credit_amount' =>
                    $line['base_credit_amount'],
            ]);
        }
    }

    private function reverseLockedJournal(
        JournalEntry $original,
        AccountingPeriod $accountingPeriod,
        string $reversalPostingDate,
        string $reason,
        string $postingKey,
        User $actor,
    ): JournalEntry {
        if ($original->isReversed()) {
            $existingReversal =
                JournalEntry::query()
                    ->where(
                        'reversal_of_id',
                        $original->getKey(),
                    )
                    ->first();

            if (
                $existingReversal
                instanceof JournalEntry
            ) {
                return $this->loadJournal(
                    $existingReversal,
                );
            }

            throw new LogicException(
                'The journal is marked as reversed but its reversal entry is missing.',
            );
        }

        if (
            !$this->registry->canReverse(
                $original->status,
            )
        ) {
            throw ValidationException::withMessages([
                'journal_entry' => [
                    'Only a posted journal entry can be reversed.',
                ],
            ]);
        }

        $reversalType = self::REVERSAL_TYPES[
            $original->journal_type
        ] ?? null;

        if ($reversalType === null) {
            throw ValidationException::withMessages([
                'journal_entry' => [
                    "Journal type [{$original->journal_type}] does not support automatic reversal.",
                ],
            ]);
        }

        $existingReversal =
            JournalEntry::query()
                ->where(
                    'reversal_of_id',
                    $original->getKey(),
                )
                ->lockForUpdate()
                ->first();

        if (
            $existingReversal
            instanceof JournalEntry
        ) {
            return $this->loadJournal(
                $existingReversal,
            );
        }

        $existingPostingKey =
            JournalEntry::query()
                ->where(
                    'posting_key',
                    $postingKey,
                )
                ->lockForUpdate()
                ->first();

        if (
            $existingPostingKey
            instanceof JournalEntry
        ) {
            if (
                (int) $existingPostingKey
                    ->reversal_of_id
                !== (int) $original->getKey()
            ) {
                throw new LogicException(
                    'The reversal posting key is already used by another journal entry.',
                );
            }

            return $this->loadJournal(
                $existingPostingKey,
            );
        }

        $original->loadMissing('lines');

        if ($original->lines->count() < 2) {
            throw new LogicException(
                'A posted journal cannot be reversed because its lines are missing.',
            );
        }

        $reversal = JournalEntry::query()->create([
            'branch_id' =>
                $original->branch_id,

            'accounting_period_id' =>
                $accountingPeriod->getKey(),

            'document_number_allocation_id' =>
                null,

            'journal_number' =>
                null,

            'posting_key' =>
                $postingKey,

            'journal_type' =>
                $reversalType,

            'status' =>
                'draft',

            'source_type' =>
                $original->source_type,

            'source_id' =>
                $original->source_id,

            'source_document_number' =>
                $original->source_document_number,

            'document_date' =>
                $reversalPostingDate,

            'posting_date' =>
                $reversalPostingDate,

            'currency_code' =>
                $original->currency_code,

            'exchange_rate' =>
                $original->exchange_rate,

            'total_debit' =>
                $original->total_credit,

            'total_credit' =>
                $original->total_debit,

            'base_total_debit' =>
                $original->base_total_credit,

            'base_total_credit' =>
                $original->base_total_debit,

            'description' => mb_substr(
                sprintf(
                    'Reversal of %s: %s',
                    $original->journal_number
                        ?? 'journal '
                            .$original->getKey(),
                    $reason,
                ),
                0,
                500,
            ),

            'prepared_by_user_id' =>
                $actor->getKey(),

            'approved_by_user_id' =>
                $actor->getKey(),

            'approved_at' =>
                now(),

            'posted_by_user_id' =>
                null,

            'posted_at' =>
                null,

            'reversal_of_id' =>
                $original->getKey(),

            'reversal_reason' =>
                $reason,

            'cancelled_by_user_id' =>
                null,

            'cancelled_at' =>
                null,

            'cancellation_reason' =>
                null,
        ]);

        foreach ($original->lines as $line) {
            JournalEntryLine::query()->create([
                'journal_entry_id' =>
                    $reversal->getKey(),

                'line_number' =>
                    $line->line_number,

                'account_id' =>
                    $line->account_id,

                'branch_id' =>
                    $line->branch_id,

                'supplier_id' =>
                    $line->supplier_id,

                'customer_id' =>
                    $line->customer_id,

                'reference' =>
                    $line->reference,

                'description' => mb_substr(
                    'Reversal: '
                        .$line->description,
                    0,
                    500,
                ),

                'due_date' =>
                    $line->due_date,

                'currency_code' =>
                    $line->currency_code,

                'exchange_rate' =>
                    $line->exchange_rate,

                'debit_amount' =>
                    $line->credit_amount,

                'credit_amount' =>
                    $line->debit_amount,

                'base_debit_amount' =>
                    $line->base_credit_amount,

                'base_credit_amount' =>
                    $line->base_debit_amount,
            ]);
        }

        $this->allocateJournalNumber(
            journalEntry: $reversal,
            postingDate: $reversalPostingDate,
            allocationKey:
                $this->allocationKey(
                    $postingKey,
                ),
        );

        $reversal->status = 'posted';

        $reversal->posted_by_user_id =
            $actor->getKey();

        $reversal->posted_at = now();

        $reversal->saveQuietly();

        $oldOriginalValues = $original->only([
            'status',
            'reversal_reason',
        ]);

        $original->status = 'reversed';

        $original->reversal_reason = $reason;

        $original->saveQuietly();

        $this->auditLogService->recordCustomEvent(
            subject: $original,
            event: 'reversed',
            oldValues: $oldOriginalValues,
            newValues: $original->only([
                'status',
                'reversal_reason',
            ]),
            metadata: [
                'actor_id' =>
                    $actor->getKey(),

                'reversal_journal_entry_id' =>
                    $reversal->getKey(),

                'reversal_journal_number' =>
                    $reversal->journal_number,
            ],
        );

        $this->auditLogService->recordCustomEvent(
            subject: $reversal,
            event: 'reversal_posted',
            newValues: $reversal->only([
                'journal_number',
                'posting_key',
                'journal_type',
                'status',
                'reversal_of_id',
                'reversal_reason',
                'posted_by_user_id',
                'posted_at',
            ]),
            metadata: [
                'actor_id' =>
                    $actor->getKey(),

                'original_journal_entry_id' =>
                    $original->getKey(),
            ],
        );

        JournalEntryReversed::dispatch(
            tenantId:
                (int) $original->tenant_id,

            originalJournalEntryId:
                (int) $original->getKey(),

            reversalJournalEntryId:
                (int) $reversal->getKey(),

            branchId:
                (int) $original->branch_id,

            actorId:
                (int) $actor->getKey(),
        );

        return $this->loadJournal(
            $reversal->refresh(),
        );
    }

    private function allocateJournalNumber(
        JournalEntry $journalEntry,
        DateTimeInterface|string $postingDate,
        ?string $allocationKey = null,
    ): void {
        if ($journalEntry->hasJournalNumber()) {
            return;
        }

        $date = $postingDate
            instanceof DateTimeInterface
                ? $postingDate
                : CarbonImmutable::parse(
                    $postingDate,
                );

        $allocation =
            $this->documentNumberService->allocate(
                documentType: 'journal_entry',

                branchId:
                    (int) $journalEntry->branch_id,

                idempotencyKey:
                    $allocationKey
                        ?? sprintf(
                            'journal-entry:%d',
                            $journalEntry->getKey(),
                        ),

                allocatableType:
                    JournalEntry::class,

                allocatableId:
                    (int) $journalEntry->getKey(),

                allocatedAt:
                    $date,
            );

        $journalEntry
            ->document_number_allocation_id =
                $allocation->getKey();

        $journalEntry->journal_number =
            $allocation->number;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function applyTotals(
        JournalEntry $journalEntry,
        array $validated,
    ): void {
        $journalEntry->currency_code =
            $validated['currency_code'];

        $journalEntry->exchange_rate =
            $validated['exchange_rate'];

        $journalEntry->total_debit =
            $validated['total_debit'];

        $journalEntry->total_credit =
            $validated['total_credit'];

        $journalEntry->base_total_debit =
            $validated['base_total_debit'];

        $journalEntry->base_total_credit =
            $validated['base_total_credit'];
    }

    private function lockSuppliedOpenPeriod(
        AccountingPeriod $accountingPeriod,
        string $postingDate,
    ): AccountingPeriod {
        $period = AccountingPeriod::query()
            ->whereKey(
                $accountingPeriod->getKey(),
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->ensureModelBelongsToTenant(
            $period,
        );

        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    "The accounting period {$period->code} is closed.",
                ],
            ]);
        }

        if (
            $postingDate
                < $period->start_date->toDateString()
            || $postingDate
                > $period->end_date->toDateString()
        ) {
            throw ValidationException::withMessages([
                'posting_date' => [
                    'The posting date does not belong to the supplied accounting period.',
                ],
            ]);
        }

        return $period;
    }

    private function accessibleBranch(
        User $actor,
        int $branchId,
        bool $requireActive,
    ): Branch {
        $branch = $this->branchAccessService
            ->findAccessibleBranch(
                user: $actor,
                branchId: $branchId,
                requireActive: $requireActive,
            );

        if (!$branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'The selected branch is unavailable or outside your access scope.',
                ],
            ]);
        }

        return $branch;
    }

    private function authorizeJournalBranch(
        User $actor,
        JournalEntry $journalEntry,
        bool $requireActive,
    ): void {
        $this->accessibleBranch(
            actor: $actor,
            branchId:
                (int) $journalEntry->branch_id,
            requireActive: $requireActive,
        );
    }

    private function lockJournal(
        JournalEntry $journalEntry,
        bool $withLines = false,
    ): JournalEntry {
        $query = JournalEntry::query()
            ->whereKey(
                $journalEntry->getKey(),
            );

        if ($withLines) {
            $query->with([
                'branch',
                'lines',
            ]);
        }

        return $query
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureIdempotentSystemJournalMatches(
        JournalEntry $journalEntry,
        string $journalType,
        Model $source,
        Branch $branch,
        string $postingDate,
        string $postingKey,
    ): void {
        $matches =
            $journalEntry->journal_type
                === $journalType

            && $journalEntry->source_type
                === $source->getMorphClass()

            && (int) $journalEntry->source_id
                === (int) $source->getKey()

            && (int) $journalEntry->branch_id
                === (int) $branch->getKey()

            && $journalEntry
                ->posting_date
                ->toDateString()
                === $postingDate

            && $journalEntry->posting_key
                === $postingKey;

        if (!$matches) {
            throw new LogicException(
                'The system journal posting key is already used by a different posting request.',
            );
        }

        if (
            !in_array(
                $journalEntry->status,
                [
                    'posted',
                    'reversed',
                ],
                true,
            )
        ) {
            throw new LogicException(
                'An incomplete journal exists for the system posting key.',
            );
        }

        if ($journalEntry->isReversed()) {
            throw ValidationException::withMessages([
                'accounting' => [
                    'The source journal was already posted and later reversed. It cannot be posted again with the same key.',
                ],
            ]);
        }
    }

    private function ensureManualJournal(
        JournalEntry $journalEntry,
    ): void {
        if (
            $journalEntry->journal_type === 'manual'
            && $journalEntry->source_type === null
            && $journalEntry->source_id === null
            && $journalEntry->posting_key === null
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'journal_entry' => [
                'System-generated journals can only be changed from their source document workflow.',
            ],
        ]);
    }

    private function ensureActorBelongsToTenant(
        User $actor,
    ): void {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw new LogicException(
                'Tenant context has not been initialized.',
            );
        }

        if (
            (int) $actor->tenant_id
            !== $tenantId
        ) {
            throw new LogicException(
                'The actor does not belong to the active tenant.',
            );
        }
    }

    private function ensureJournalBelongsToTenant(
        JournalEntry $journalEntry,
    ): void {
        $this->ensureModelBelongsToTenant(
            $journalEntry,
        );
    }

    private function ensureSourceBelongsToTenant(
        Model $source,
    ): void {
        if ($source->getKey() === null) {
            throw new LogicException(
                'The accounting source must be persisted before posting.',
            );
        }

        $this->ensureModelBelongsToTenant(
            $source,
        );
    }

    private function ensureModelBelongsToTenant(
        Model $model,
    ): void {
        $tenantId = $this->tenantContext->id();

        $modelTenantId = $model->getAttribute(
            'tenant_id',
        );

        if (
            $tenantId === null
            || $modelTenantId === null
            || (int) $modelTenantId
                !== $tenantId
        ) {
            throw new LogicException(
                'The accounting record does not belong to the active tenant.',
            );
        }
    }

    private function normalizeDate(
        mixed $value,
        string $field,
    ): string {
        if (
            $value === null
            || (
                !$value instanceof DateTimeInterface
                && trim((string) $value) === ''
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The date is required.',
                ],
            ]);
        }

        $tenant = $this->tenantContext->tenant();

        try {
            return $value instanceof DateTimeInterface
                ? CarbonImmutable::instance(
                    $value,
                )
                    ->setTimezone(
                        $tenant->timezone,
                    )
                    ->toDateString()

                : CarbonImmutable::parse(
                    (string) $value,
                    $tenant->timezone,
                )->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must be valid.',
                ],
            ]);
        }
    }

    private function normalizeDescription(
        string $description,
    ): string {
        $description = trim($description);

        if (
            $description === ''
            || mb_strlen($description) > 500
        ) {
            throw ValidationException::withMessages([
                'description' => [
                    'A description is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return $description;
    }

    private function normalizeReason(
        string $reason,
        string $field,
    ): string {
        $reason = trim($reason);

        if (
            $reason === ''
            || mb_strlen($reason) > 500
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'A reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return $reason;
    }

    private function normalizePostingKey(
        string $postingKey,
    ): string {
        $postingKey = trim($postingKey);

        if (
            $postingKey === ''
            || mb_strlen($postingKey) > 190
        ) {
            throw new LogicException(
                'A posting key is required and may not exceed 190 characters.',
            );
        }

        return $postingKey;
    }

    private function nullableText(
        ?string $value,
        int $maxLength,
        string $field,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw ValidationException::withMessages([
                $field => [
                    "The value may not exceed {$maxLength} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function allocationKey(
        string $postingKey,
    ): string {
        return 'journal:'.hash(
            'sha256',
            $postingKey,
        );
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'System journal posting must run inside the source accounting transaction.',
            );
        }
    }

    private function loadJournal(
        JournalEntry $journalEntry,
    ): JournalEntry {
        return $journalEntry->load([
            'branch',
            'accountingPeriod.fiscalYear',
            'documentNumberAllocation',
            'source',
            'lines.account',
            'lines.supplier',
            'lines.customer',
            'preparedBy',
            'approvedBy',
            'postedBy',
            'cancelledBy',
            'reversalOf',
            'reversal',
        ]);
    }
}