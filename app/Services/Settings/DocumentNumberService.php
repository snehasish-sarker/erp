<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Branch;
use App\Models\DocumentNumberAllocation;
use App\Models\DocumentSequence;
use App\Support\DocumentNumbers\DocumentNumberFormatter;
use App\Support\DocumentNumbers\DocumentTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class DocumentNumberService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentNumberFormatter $formatter,
        private readonly DocumentTypeRegistry $documentTypeRegistry,
    ) {
    }

    public function allocate(
        string $documentType,
        ?int $branchId,
        string $idempotencyKey,
        ?string $allocatableType = null,
        ?int $allocatableId = null,
        ?DateTimeInterface $allocatedAt = null,
    ): DocumentNumberAllocation {
        if (!$this->documentTypeRegistry->exists($documentType)) {
            throw new LogicException(
                "Unsupported document type [{$documentType}].",
            );
        }

        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '') {
            throw new LogicException(
                'An idempotency key is required to allocate a document number.',
            );
        }

        if (mb_strlen($idempotencyKey) > 100) {
            throw new LogicException(
                'The document-number idempotency key cannot exceed 100 characters.',
            );
        }

        try {
            return DB::transaction(
                function () use (
                    $documentType,
                    $branchId,
                    $idempotencyKey,
                    $allocatableType,
                    $allocatableId,
                    $allocatedAt,
                ): DocumentNumberAllocation {
                    $existingAllocation =
                        DocumentNumberAllocation::query()
                            ->where(
                                'idempotency_key',
                                $idempotencyKey,
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        $existingAllocation
                        instanceof DocumentNumberAllocation
                    ) {
                        $this->ensureIdempotentRequestMatches(
                            allocation: $existingAllocation,
                            documentType: $documentType,
                            branchId: $branchId,
                            allocatableType: $allocatableType,
                            allocatableId: $allocatableId,
                        );

                        return $existingAllocation;
                    }

                    $sequence = $this->lockSequence(
                        documentType: $documentType,
                        branchId: $branchId,
                    );

                    $tenant = $this->tenantContext->tenant();

                    $date = $allocatedAt === null
                        ? CarbonImmutable::now(
                            $tenant->timezone,
                        )
                        : CarbonImmutable::instance(
                            $allocatedAt,
                        )->setTimezone(
                            $tenant->timezone,
                        );

                    $fiscalYearStartMonth = (int) (
                        $sequence->fiscal_year_start_month ?? 1
                    );

                    $resetKey = $this->formatter->resetKey(
                        resetPolicy: $sequence->reset_policy,
                        fiscalYearStartMonth:
                            $fiscalYearStartMonth,
                        date: $date,
                    );

                    if (
                        $sequence->reset_policy !== 'never'
                        && $sequence->last_reset_key !== null
                        && $sequence->last_reset_key !== $resetKey
                    ) {
                        $sequence->current_number = 0;
                    }

                    $nextNumber =
                        (int) $sequence->current_number + 1;

                    $sequence->loadMissing(
                        'branch:id,code',
                    );

                    $documentBranch = $branchId === null
                        ? null
                        : Branch::query()
                            ->whereKey($branchId)
                            ->firstOrFail([
                                'id',
                                'code',
                            ]);

                    $formattedNumber =
                        $this->formatter->format(
                            documentType:
                                $sequence->document_type,
                            prefix: $sequence->prefix,
                            suffix: $sequence->suffix,
                            sequenceNumber: $nextNumber,
                            numberPadding: (int) (
                                $sequence->number_padding
                            ),
                            companyCode: $tenant->code,
                            branchCode:
                                $documentBranch?->code
                                ?? $sequence->branch?->code,
                            fiscalYearStartMonth:
                                $fiscalYearStartMonth,
                            date: $date,
                        );

                    $sequence->current_number = $nextNumber;
                    $sequence->last_reset_key = $resetKey;

                    /*
                     * The immutable allocation ledger is audited.
                     * Avoid producing an additional generic sequence
                     * update audit record for every allocated number.
                     */
                    $sequence->saveQuietly();

                    return DocumentNumberAllocation::query()
                        ->create([
                            'document_sequence_id' =>
                                $sequence->getKey(),

                            'branch_id' => $branchId,

                            'document_type' =>
                                $sequence->document_type,

                            'reset_key' => $resetKey,

                            'sequence_number' =>
                                $nextNumber,

                            'number' => $formattedNumber,

                            'idempotency_key' =>
                                $idempotencyKey,

                            'allocatable_type' =>
                                $allocatableType,

                            'allocatable_id' =>
                                $allocatableId,

                            'allocated_at' => $date,
                        ]);
                },
                attempts: 5,
            );
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw ValidationException::withMessages([
                'document_number' => [
                    'The configured sequence would create a duplicate document number. Use a distinct prefix, suffix, or branch token.',
                ],
            ]);
        }
    }

    public function preview(
        DocumentSequence $documentSequence,
        ?DateTimeInterface $date = null,
    ): string {
        $tenant = $this->tenantContext->tenant();

        $previewDate = $date === null
            ? CarbonImmutable::now($tenant->timezone)
            : CarbonImmutable::instance($date)
                ->setTimezone($tenant->timezone);

        $documentSequence->loadMissing(
            'branch:id,code',
        );

        $fiscalYearStartMonth = (int) (
            $documentSequence->fiscal_year_start_month ?? 1
        );

        $resetKey = $this->formatter->resetKey(
            resetPolicy: $documentSequence->reset_policy,
            fiscalYearStartMonth: $fiscalYearStartMonth,
            date: $previewDate,
        );

        $nextNumber =
            (int) $documentSequence->current_number + 1;

        if (
            $documentSequence->reset_policy !== 'never'
            && $documentSequence->last_reset_key !== null
            && $documentSequence->last_reset_key !== $resetKey
        ) {
            $nextNumber = 1;
        }

        return $this->formatter->format(
            documentType: $documentSequence->document_type,
            prefix: $documentSequence->prefix,
            suffix: $documentSequence->suffix,
            sequenceNumber: $nextNumber,
            numberPadding: (int) (
                $documentSequence->number_padding
            ),
            companyCode: $tenant->code,
            branchCode: $documentSequence->branch?->code,
            fiscalYearStartMonth: $fiscalYearStartMonth,
            date: $previewDate,
        );
    }

    /**
     * @return array{
     *     timezone: string,
     *     company_code: string,
     *     current_year: int,
     *     current_month: int
     * }
     */
    public function previewContext(): array
    {
        $tenant = $this->tenantContext->tenant();

        $date = CarbonImmutable::now(
            $tenant->timezone,
        );

        return [
            'timezone' => $tenant->timezone,
            'company_code' => $tenant->code,
            'current_year' => $date->year,
            'current_month' => $date->month,
        ];
    }

    private function lockSequence(
        string $documentType,
        ?int $branchId,
    ): DocumentSequence {
        if ($branchId !== null) {
            $branchSequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->where('branch_id', $branchId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (
                $branchSequence
                instanceof DocumentSequence
            ) {
                return $branchSequence;
            }
        }

        $companySequence = DocumentSequence::query()
            ->where('document_type', $documentType)
            ->whereNull('branch_id')
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (
            $companySequence
            instanceof DocumentSequence
        ) {
            return $companySequence;
        }

        throw ValidationException::withMessages([
            'document_number' => [
                'No active document-number sequence is configured for this document type and branch.',
            ],
        ]);
    }

    private function ensureIdempotentRequestMatches(
        DocumentNumberAllocation $allocation,
        string $documentType,
        ?int $branchId,
        ?string $allocatableType,
        ?int $allocatableId,
    ): void {
        if (
            $allocation->document_type !== $documentType
            || $allocation->branch_id !== $branchId
            || $allocation->allocatable_type
                !== $allocatableType
            || $allocation->allocatable_id
                !== $allocatableId
        ) {
            throw new LogicException(
                'The idempotency key is already associated with a different document-number allocation.',
            );
        }
    }
}