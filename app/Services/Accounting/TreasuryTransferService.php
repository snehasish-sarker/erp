<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\TreasuryTransferAccountingGateway;
use App\Models\BankReconciliationMatch;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Accounting\TreasuryTransferTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class TreasuryTransferService
{
    private const MONEY_SCALE = 6;
    private const RATE_SCALE = 8;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly TreasuryAccountService $accountService,
        private readonly TreasuryTransferTypeRegistry $typeRegistry,
        private readonly DocumentNumberService $documentNumberService,
        private readonly AccountingPeriodService $accountingPeriodService,
        private readonly TreasuryTransferAccountingGateway $accountingGateway,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): TreasuryTransfer
    {
        $normalized = $this->normalize($data, $actor);

        return DB::transaction(function () use ($normalized, $actor): TreasuryTransfer {
            $accounts = $this->lockedAccounts($normalized);
            $transfer = TreasuryTransfer::query()->create([
                ...$normalized,
                'transfer_type' => $this->typeRegistry->expectedType(
                    (string) $accounts['source']->control_type,
                    (string) $accounts['destination']->control_type,
                ),
                'source_account_code' => $accounts['source']->code,
                'source_account_name' => $accounts['source']->name,
                'source_control_type' => $accounts['source']->control_type,
                'destination_account_code' => $accounts['destination']->code,
                'destination_account_name' => $accounts['destination']->name,
                'destination_control_type' => $accounts['destination']->control_type,
                'base_amount' => $this->baseAmount($normalized['amount'], $normalized['exchange_rate']),
                'status' => 'draft',
                'revision' => 1,
                'created_by_user_id' => $actor->getKey(),
            ]);

            return $this->load($transfer);
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(TreasuryTransfer $transfer, array $data, User $actor): TreasuryTransfer
    {
        $normalized = $this->normalize($data, $actor);

        return DB::transaction(function () use ($transfer, $normalized, $actor): TreasuryTransfer {
            $locked = $this->lock($transfer);
            $this->ensureEditable($locked);
            $this->authorizeBranches($actor, (int) $locked->source_branch_id, (int) $locked->destination_branch_id, false);
            $accounts = $this->lockedAccounts($normalized);

            $locked->fill([
                ...$normalized,
                'transfer_type' => $this->typeRegistry->expectedType(
                    (string) $accounts['source']->control_type,
                    (string) $accounts['destination']->control_type,
                ),
                'source_account_code' => $accounts['source']->code,
                'source_account_name' => $accounts['source']->name,
                'source_control_type' => $accounts['source']->control_type,
                'destination_account_code' => $accounts['destination']->code,
                'destination_account_name' => $accounts['destination']->name,
                'destination_control_type' => $accounts['destination']->control_type,
                'base_amount' => $this->baseAmount($normalized['amount'], $normalized['exchange_rate']),
                'revision' => (int) $locked->revision + 1,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function delete(TreasuryTransfer $transfer, User $actor): void
    {
        DB::transaction(function () use ($transfer, $actor): void {
            $locked = $this->lock($transfer);
            $this->authorizeBranches($actor, (int) $locked->source_branch_id, (int) $locked->destination_branch_id, false);

            if (!$locked->canBeDeleted()) {
                throw ValidationException::withMessages([
                    'transfer' => ['Only an unnumbered draft Treasury Transfer can be deleted.'],
                ]);
            }

            $locked->delete();
        }, attempts: 5);
    }

    public function submit(TreasuryTransfer $transfer, User $actor): TreasuryTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): TreasuryTransfer {
            $locked = $this->lock($transfer);
            $this->authorizeBranches($actor, (int) $locked->source_branch_id, (int) $locked->destination_branch_id, true);

            if (!$locked->isDraft()) {
                throw ValidationException::withMessages(['status' => ['Only a draft transfer can be submitted.']]);
            }

            $this->revalidate($locked);
            $this->allocateNumber($locked);
            $locked->status = 'submitted';
            $locked->submitted_by_user_id = $actor->getKey();
            $locked->submitted_at = now();
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function returnToDraft(TreasuryTransfer $transfer, User $actor): TreasuryTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): TreasuryTransfer {
            $locked = $this->lock($transfer);
            $this->authorizeBranches($actor, (int) $locked->source_branch_id, (int) $locked->destination_branch_id, false);

            if (!$locked->isSubmitted()) {
                throw ValidationException::withMessages(['status' => ['Only a submitted transfer can return to draft.']]);
            }

            $locked->status = 'draft';
            $locked->submitted_by_user_id = null;
            $locked->submitted_at = null;
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function approve(TreasuryTransfer $transfer, User $actor): TreasuryTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): TreasuryTransfer {
            $locked = $this->lock($transfer);
            $this->authorizeBranches($actor, (int) $locked->source_branch_id, (int) $locked->destination_branch_id, true);

            if (!$locked->isSubmitted()) {
                throw ValidationException::withMessages(['status' => ['Only a submitted transfer can be approved.']]);
            }

            $this->revalidate($locked);
            $this->accountingPeriodService->lockOpenPeriod($locked->posting_date);
            $locked->status = 'approved';
            $locked->approved_by_user_id = $actor->getKey();
            $locked->approved_at = now();
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function post(TreasuryTransfer $transfer, User $actor): TreasuryTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): TreasuryTransfer {
            $locked = $this->lock($transfer);
            $this->authorizeBranches($actor, (int) $locked->source_branch_id, (int) $locked->destination_branch_id, true);

            if (!$locked->isApproved()) {
                throw ValidationException::withMessages(['status' => ['Only an approved transfer can be posted.']]);
            }

            $this->revalidate($locked);
            $period = $this->accountingPeriodService->lockOpenPeriod($locked->posting_date);
            $reference = $this->accountingGateway->post($locked, $period, $actor);
            $locked->status = 'posted';
            $locked->posted_by_user_id = $actor->getKey();
            $locked->posted_at = now();
            $locked->accounting_posting_reference = $reference;
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function cancel(TreasuryTransfer $transfer, string $reason, User $actor): TreasuryTransfer
    {
        $reason = $this->reason($reason, 'cancellation_reason');

        return DB::transaction(function () use ($transfer, $reason, $actor): TreasuryTransfer {
            $locked = $this->lock($transfer);
            $this->authorizeBranches($actor, (int) $locked->source_branch_id, (int) $locked->destination_branch_id, false);

            if (!in_array($locked->status, ['draft', 'submitted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => ['This transfer cannot be cancelled.']]);
            }

            $locked->status = 'cancelled';
            $locked->cancelled_by_user_id = $actor->getKey();
            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function reverse(
        TreasuryTransfer $transfer,
        string $postingDate,
        string $reason,
        User $actor,
    ): TreasuryTransfer {
        $reason = $this->reason($reason, 'reversal_reason');
        $date = $this->date($postingDate, 'reversal_posting_date');

        return DB::transaction(function () use ($transfer, $date, $reason, $actor): TreasuryTransfer {
            $locked = $this->lock($transfer);
            $this->authorizeBranches($actor, (int) $locked->source_branch_id, (int) $locked->destination_branch_id, false);

            if (!$locked->isPosted()) {
                throw ValidationException::withMessages(['status' => ['Only a posted transfer can be reversed.']]);
            }

            $this->ensureNotReconciled($locked);
            $period = $this->accountingPeriodService->lockOpenPeriod(CarbonImmutable::parse($date));
            $reference = $this->accountingGateway->reverse($locked, $period, CarbonImmutable::parse($date), $reason, $actor);
            $locked->status = 'reversed';
            $locked->reversal_posting_date = $date;
            $locked->reversed_by_user_id = $actor->getKey();
            $locked->reversed_at = now();
            $locked->reversal_reason = $reason;
            $locked->accounting_reversal_reference = $reference;
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, User $actor): array
    {
        $sourceBranchId = $this->positiveInteger($data['source_branch_id'] ?? null, 'source_branch_id');
        $destinationBranchId = $this->positiveInteger($data['destination_branch_id'] ?? null, 'destination_branch_id');
        $this->authorizeBranches($actor, $sourceBranchId, $destinationBranchId, true);
        $sourceAccountId = $this->positiveInteger($data['source_account_id'] ?? null, 'source_account_id');
        $destinationAccountId = $this->positiveInteger($data['destination_account_id'] ?? null, 'destination_account_id');

        if ($sourceAccountId === $destinationAccountId) {
            throw ValidationException::withMessages([
                'destination_account_id' => ['Source and destination accounts must be different.'],
            ]);
        }

        $currency = strtoupper(trim((string) ($data['currency_code'] ?? '')));
        $tenantCurrency = strtoupper((string) $this->tenantContext->tenant()->currency_code);

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages(['currency_code' => ['Enter a valid three-letter currency code.']]);
        }

        $rate = $this->positiveDecimal($data['exchange_rate'] ?? null, self::RATE_SCALE, 'exchange_rate');

        if ($currency === $tenantCurrency && !$rate->isEqualTo(BigDecimal::one()->toScale(self::RATE_SCALE))) {
            throw ValidationException::withMessages(['exchange_rate' => ['The exchange rate must be 1.00000000 for the base currency.']]);
        }

        return [
            'source_branch_id' => $sourceBranchId,
            'destination_branch_id' => $destinationBranchId,
            'source_account_id' => $sourceAccountId,
            'destination_account_id' => $destinationAccountId,
            'transfer_date' => $this->date($data['transfer_date'] ?? null, 'transfer_date'),
            'posting_date' => $this->date($data['posting_date'] ?? null, 'posting_date'),
            'currency_code' => $currency,
            'exchange_rate' => $rate->__toString(),
            'amount' => $this->positiveDecimal($data['amount'] ?? null, self::MONEY_SCALE, 'amount')->__toString(),
            'reference' => $this->nullableText($data['reference'] ?? null, 160),
            'notes' => $this->nullableText($data['notes'] ?? null, 4000),
        ];
    }

    /** @param array<string, mixed> $normalized
     * @return array{source: \App\Models\Account, destination: \App\Models\Account}
     */
    private function lockedAccounts(array $normalized): array
    {
        $ids = [(int) $normalized['source_account_id'], (int) $normalized['destination_account_id']];
        sort($ids);
        foreach ($ids as $id) {
            $this->accountService->lockCashOrBankAccount($id, $id === (int) $normalized['source_account_id'] ? 'source_account_id' : 'destination_account_id');
        }

        return [
            'source' => $this->accountService->lockCashOrBankAccount((int) $normalized['source_account_id'], 'source_account_id'),
            'destination' => $this->accountService->lockCashOrBankAccount((int) $normalized['destination_account_id'], 'destination_account_id'),
        ];
    }

    private function revalidate(TreasuryTransfer $transfer): void
    {
        $source = $this->accountService->lockCashOrBankAccount((int) $transfer->source_account_id, 'source_account_id');
        $destination = $this->accountService->lockCashOrBankAccount((int) $transfer->destination_account_id, 'destination_account_id');

        if ((int) $source->getKey() === (int) $destination->getKey()) {
            throw ValidationException::withMessages(['destination_account_id' => ['Source and destination accounts must be different.']]);
        }

        $expected = $this->typeRegistry->expectedType((string) $source->control_type, (string) $destination->control_type);

        if ($expected !== $transfer->transfer_type) {
            throw new LogicException('The persisted Treasury Transfer type no longer matches its accounts.');
        }
    }

    private function allocateNumber(TreasuryTransfer $transfer): void
    {
        if ($transfer->transfer_number !== null) {
            return;
        }

        $allocation = $this->documentNumberService->allocate(
            documentType: 'treasury_transfer',
            branchId: (int) $transfer->source_branch_id,
            idempotencyKey: sprintf('treasury-transfer:%d:%d', (int) $transfer->tenant_id, (int) $transfer->getKey()),
            allocatableType: TreasuryTransfer::class,
            allocatableId: (int) $transfer->getKey(),
            allocatedAt: $transfer->transfer_date,
        );

        $transfer->document_number_allocation_id = $allocation->getKey();
        $transfer->transfer_number = $allocation->number;
    }

    private function ensureNotReconciled(TreasuryTransfer $transfer): void
    {
        $journalIds = JournalEntry::query()
            ->where('source_type', $transfer->getMorphClass())
            ->where('source_id', $transfer->getKey())
            ->pluck('id');

        if ($journalIds->isEmpty()) {
            return;
        }

        $matched = BankReconciliationMatch::query()
            ->where('status', 'active')
            ->whereHas('journalEntryLine', static fn ($query) => $query->whereIn('journal_entry_id', $journalIds))
            ->exists();

        if ($matched) {
            throw ValidationException::withMessages([
                'transfer' => ['This transfer has already been matched in bank reconciliation and cannot be reversed.'],
            ]);
        }
    }

    private function lock(TreasuryTransfer $transfer): TreasuryTransfer
    {
        return TreasuryTransfer::query()->whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
    }

    private function load(TreasuryTransfer $transfer): TreasuryTransfer
    {
        return $transfer->load([
            'sourceBranch:id,name,code,status',
            'destinationBranch:id,name,code,status',
            'sourceAccount:id,code,name,control_type,status',
            'destinationAccount:id,code,name,control_type,status',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
        ]);
    }

    private function ensureEditable(TreasuryTransfer $transfer): void
    {
        if (!$transfer->canBeEdited()) {
            throw ValidationException::withMessages(['status' => ['Only a draft transfer can be edited.']]);
        }
    }

    private function authorizeBranches(User $actor, int $sourceBranchId, int $destinationBranchId, bool $active): void
    {
        foreach (array_values(array_unique([$sourceBranchId, $destinationBranchId])) as $branchId) {
            $branch = Branch::query()->whereKey($branchId)->first();

            if (!$branch instanceof Branch) {
                throw ValidationException::withMessages(['branch_id' => ['The selected branch is unavailable.']]);
            }

            $this->branchAccessService->authorizeBranch($actor, $branch, $active);
        }
    }

    private function baseAmount(string $amount, string $rate): string
    {
        return BigDecimal::of($amount)
            ->multipliedBy(BigDecimal::of($rate))
            ->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP)
            ->__toString();
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            throw ValidationException::withMessages([$field => ['Select a valid value.']]);
        }

        return $integer;
    }

    private function positiveDecimal(mixed $value, int $scale, string $field): BigDecimal
    {
        try {
            $decimal = BigDecimal::of(trim((string) $value))->toScale($scale, RoundingMode::UNNECESSARY);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => ["Enter a positive number with no more than {$scale} decimal places."]]);
        }

        if (!$decimal->isGreaterThan(BigDecimal::zero())) {
            throw ValidationException::withMessages([$field => ['The value must be greater than zero.']]);
        }

        return $decimal;
    }

    private function date(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw ValidationException::withMessages([$field => ['Use the YYYY-MM-DD date format.']]);
        }

        $value = trim($value);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $this->tenantContext->tenant()->timezone);

        if (!$date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => ['Use the YYYY-MM-DD date format.']]);
        }

        return $value;
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages(['transfer' => ["Text may not exceed {$max} characters."]]);
        }

        return $text;
    }

    private function reason(string $reason, string $field): string
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([$field => ['A reason is required and may not exceed 500 characters.']]);
        }

        return $reason;
    }
}
