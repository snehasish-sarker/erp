<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DocumentSequenceService
{
    /**
     * @param array{
     *     branch_id: int|null,
     *     name: string,
     *     document_type: string,
     *     prefix: string|null,
     *     suffix: string|null,
     *     current_number: int,
     *     number_padding: int,
     *     reset_policy: string,
     *     fiscal_year_start_month: int|null,
     *     status: string
     * } $attributes
     */
    public function create(array $attributes): DocumentSequence
    {
        return DB::transaction(
            function () use ($attributes): DocumentSequence {
                return DocumentSequence::query()->create([
                    ...$attributes,

                    'scope_key' => $this->scopeKey(
                        $attributes['branch_id'],
                    ),

                    'last_reset_key' => null,
                ]);
            },
            attempts: 5,
        );
    }

    /**
     * @param array{
     *     branch_id: int|null,
     *     name: string,
     *     document_type: string,
     *     prefix: string|null,
     *     suffix: string|null,
     *     current_number: int,
     *     number_padding: int,
     *     reset_policy: string,
     *     fiscal_year_start_month: int|null,
     *     status: string
     * } $attributes
     */
    public function update(
        DocumentSequence $documentSequence,
        array $attributes,
    ): DocumentSequence {
        return DB::transaction(
            function () use (
                $documentSequence,
                $attributes,
            ): DocumentSequence {
                $lockedSequence = DocumentSequence::query()
                    ->whereKey($documentSequence->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $hasAllocations = $lockedSequence
                    ->allocations()
                    ->exists();

                if ($hasAllocations) {
                    $this->ensureAllocatedIdentityIsUnchanged(
                        documentSequence: $lockedSequence,
                        attributes: $attributes,
                    );
                }

                $resetConfigurationChanged =
                    $lockedSequence->reset_policy
                        !== $attributes['reset_policy']
                    || (int) (
                        $lockedSequence
                            ->fiscal_year_start_month
                        ?? 1
                    ) !== (int) (
                        $attributes[
                            'fiscal_year_start_month'
                        ] ?? 1
                    );

                $lockedSequence->fill([
                    ...$attributes,

                    'scope_key' => $this->scopeKey(
                        $attributes['branch_id'],
                    ),
                ]);

                if ($resetConfigurationChanged) {
                    $lockedSequence->last_reset_key = null;
                }

                $lockedSequence->save();

                return $lockedSequence->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(
        DocumentSequence $documentSequence,
    ): void {
        DB::transaction(
            function () use ($documentSequence): void {
                $lockedSequence = DocumentSequence::query()
                    ->whereKey($documentSequence->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedSequence->allocations()->exists()) {
                    throw ValidationException::withMessages([
                        'document_sequence' => [
                            'A sequence cannot be deleted after it has allocated a document number. Set it to inactive instead.',
                        ],
                    ]);
                }

                $lockedSequence->delete();
            },
            attempts: 5,
        );
    }

    private function scopeKey(?int $branchId): string
    {
        return $branchId === null
            ? 'company'
            : "branch:{$branchId}";
    }

    /**
     * @param array{
     *     branch_id: int|null,
     *     name: string,
     *     document_type: string,
     *     prefix: string|null,
     *     suffix: string|null,
     *     current_number: int,
     *     number_padding: int,
     *     reset_policy: string,
     *     fiscal_year_start_month: int|null,
     *     status: string
     * } $attributes
     */
    private function ensureAllocatedIdentityIsUnchanged(
        DocumentSequence $documentSequence,
        array $attributes,
    ): void {
        $errors = [];

        if (
            $documentSequence->branch_id
            !== $attributes['branch_id']
        ) {
            $errors['branch_id'] = [
                'The sequence scope cannot change after a number has been allocated.',
            ];
        }

        if (
            $documentSequence->document_type
            !== $attributes['document_type']
        ) {
            $errors['document_type'] = [
                'The document type cannot change after a number has been allocated.',
            ];
        }

        if (
            (int) $documentSequence->current_number
            !== $attributes['current_number']
        ) {
            $errors['current_number'] = [
                'The current number is managed automatically after the first allocation.',
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}