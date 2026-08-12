<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\AccountsPayableRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierStatementService
{
    private const MONEY_SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly AccountsPayableRegistry $accountsPayableRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     supplier_id: int,
     *     branch_id: int|null,
     *     currency_code: string|null,
     *     date_from: string,
     *     date_to: string,
     *     per_page: int
     * }
     */
    public function normalizeExportFilters(
        Supplier $supplier,
        User $actor,
        array $filters,
    ): array {
        $context = $this->statementContext(
            supplier: $supplier,
            actor: $actor,
            filters: $filters,
        );

        return [
            'supplier_id' =>
                (int) $supplier->getKey(),
            'branch_id' => $context['branch_id'],
            'currency_code' =>
                $context['currency_code'],
            'date_from' =>
                $context['date_from'],
            'date_to' =>
                $context['date_to'],
            'per_page' =>
                $this->perPage(
                    $filters['per_page'] ?? 25,
                ),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportTotalRows(
        Supplier $supplier,
        User $actor,
        array $filters,
    ): int {
        $context = $this->statementContext(
            supplier: $supplier,
            actor: $actor,
            filters: $filters,
        );

        $baseQuery = $this->statementBaseQuery(
            supplier: $supplier,
            actor: $actor,
            branchId: $context['branch_id'],
            currencyCode: $context['currency_code'],
        );

        $periodQuery = (clone $baseQuery)
            ->whereDate(
                'posting_date',
                '>=',
                $context['date_from'],
            )
            ->whereDate(
                'posting_date',
                '<=',
                $context['date_to'],
            );

        $openingQuery = (clone $baseQuery)
            ->whereDate(
                'posting_date',
                '<',
                $context['date_from'],
            );

        $currencySummaryCount = count(
            $this->closingCurrencySummary(
                opening: $this->currencyTotals(
                    clone $openingQuery,
                ),
                period: $this->currencyTotals(
                    clone $periodQuery,
                ),
            ),
        );

        return 1
            + $currencySummaryCount
            + (clone $periodQuery)->count();
    }

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportRows(
        Supplier $supplier,
        User $actor,
        array $filters,
    ): LazyCollection {
        $context = $this->statementContext(
            supplier: $supplier,
            actor: $actor,
            filters: $filters,
        );

        $baseQuery = $this->statementBaseQuery(
            supplier: $supplier,
            actor: $actor,
            branchId: $context['branch_id'],
            currencyCode: $context['currency_code'],
        );

        $openingQuery = (clone $baseQuery)
            ->whereDate(
                'posting_date',
                '<',
                $context['date_from'],
            );

        $periodQuery = (clone $baseQuery)
            ->whereDate(
                'posting_date',
                '>=',
                $context['date_from'],
            )
            ->whereDate(
                'posting_date',
                '<=',
                $context['date_to'],
            );

        $openingCurrencySummary =
            $this->currencyTotals(
                clone $openingQuery,
            );

        $periodCurrencySummary =
            $this->currencyTotals(
                clone $periodQuery,
            );

        $openingBaseSummary =
            $this->baseTotals(
                clone $openingQuery,
            );

        $periodBaseSummary =
            $this->baseTotals(
                clone $periodQuery,
            );

        $currencySummary =
            $this->closingCurrencySummary(
                opening:
                    $openingCurrencySummary,
                period:
                    $periodCurrencySummary,
            );

        $baseClosingBalance = $this->money(
            $openingBaseSummary['balance'],
        )
            ->plus(
                $this->money(
                    $periodBaseSummary['balance'],
                ),
            )
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );

        $runningCurrencyBalances =
            $this->currencyBalanceMap(
                $openingCurrencySummary,
            );

        $runningBaseBalance = $this->money(
            $openingBaseSummary['balance'],
        );

        $branchMap = Branch::withTrashed()
            ->whereIn(
                'id',
                (clone $periodQuery)
                    ->select('branch_id')
                    ->distinct()
                    ->pluck('branch_id'),
            )
            ->get([
                'id',
                'code',
                'name',
            ])
            ->keyBy('id');

        return LazyCollection::make(
            function () use (
                $supplier,
                $context,
                $periodQuery,
                $currencySummary,
                $openingBaseSummary,
                $periodBaseSummary,
                $baseClosingBalance,
                &$runningCurrencyBalances,
                &$runningBaseBalance,
                $branchMap,
            ): \Generator {
                yield [
                    'row_type' => 'base_summary',
                    'supplier_code' =>
                        $supplier->code,
                    'supplier_name' =>
                        $supplier->name,
                    'date_from' =>
                        $context['date_from'],
                    'date_to' =>
                        $context['date_to'],
                    'currency_code' =>
                        mb_strtoupper(
                            (string) $this
                                ->tenantContext
                                ->tenant()
                                ->currency_code,
                        ),
                    'opening_balance' =>
                        $openingBaseSummary['balance'],
                    'period_debit' =>
                        $periodBaseSummary['debit'],
                    'period_credit' =>
                        $periodBaseSummary['credit'],
                    'closing_balance' =>
                        $baseClosingBalance
                            ->__toString(),
                ];

                foreach (
                    $currencySummary
                    as $summary
                ) {
                    yield [
                        'row_type' =>
                            'currency_summary',
                        'supplier_code' =>
                            $supplier->code,
                        'supplier_name' =>
                            $supplier->name,
                        'date_from' =>
                            $context['date_from'],
                        'date_to' =>
                            $context['date_to'],
                        ...$summary,
                    ];
                }

                foreach (
                    (clone $periodQuery)
                        ->orderBy('posting_date')
                        ->orderBy('id')
                        ->cursor()
                    as $entry
                ) {
                    if (
                        !$entry
                            instanceof SupplierLedgerEntry
                    ) {
                        continue;
                    }

                    $currency = mb_strtoupper(
                        (string) $entry
                            ->currency_code,
                    );

                    $transactionChange =
                        $this->money(
                            $entry->credit_amount,
                        )
                            ->minus(
                                $this->money(
                                    $entry
                                        ->debit_amount,
                                ),
                            )
                            ->toScale(
                                self::MONEY_SCALE,
                                RoundingMode::HalfUp,
                            );

                    $baseChange = $this->money(
                        $entry->base_credit_amount,
                    )
                        ->minus(
                            $this->money(
                                $entry
                                    ->base_debit_amount,
                            ),
                        )
                        ->toScale(
                            self::MONEY_SCALE,
                            RoundingMode::HalfUp,
                        );

                    $runningCurrencyBalances[
                        $currency
                    ] = (
                        $runningCurrencyBalances[
                            $currency
                        ] ?? $this->zeroMoney()
                    )
                        ->plus(
                            $transactionChange,
                        )
                        ->toScale(
                            self::MONEY_SCALE,
                            RoundingMode::HalfUp,
                        );

                    $runningBaseBalance =
                        $runningBaseBalance
                            ->plus($baseChange)
                            ->toScale(
                                self::MONEY_SCALE,
                                RoundingMode::HalfUp,
                            );

                    yield [
                        'row_type' => 'entry',
                        'supplier_code' =>
                            $supplier->code,
                        'supplier_name' =>
                            $supplier->name,
                        'reference' =>
                            $entry->reference,
                        'journal_reference' =>
                            $entry
                                ->journal_reference,
                        'entry_type' =>
                            $entry->entry_type,
                        'entry_type_label' =>
                            $this
                                ->accountsPayableRegistry
                                ->ledgerEntryTypeLabel(
                                    $entry->entry_type,
                                ),
                        'source_document_number' =>
                            $entry
                                ->source_document_number,
                        'document_date' =>
                            $entry
                                ->document_date
                                ->toDateString(),
                        'posting_date' =>
                            $entry
                                ->posting_date
                                ->toDateString(),
                        'due_date' =>
                            $entry
                                ->due_date
                                ?->toDateString(),
                        'branch_id' =>
                            (int) $entry->branch_id,
                        'branch_code' =>
                            $branchMap->get(
                                (int) $entry->branch_id,
                            )?->code,
                        'branch_name' =>
                            $branchMap->get(
                                (int) $entry->branch_id,
                            )?->name,
                        'currency_code' =>
                            $currency,
                        'exchange_rate' =>
                            $entry->exchange_rate,
                        'debit_amount' =>
                            $entry->debit_amount,
                        'credit_amount' =>
                            $entry->credit_amount,
                        'transaction_change' =>
                            $transactionChange
                                ->__toString(),
                        'currency_running_balance' =>
                            $runningCurrencyBalances[
                                $currency
                            ]->__toString(),
                        'base_debit_amount' =>
                            $entry
                                ->base_debit_amount,
                        'base_credit_amount' =>
                            $entry
                                ->base_credit_amount,
                        'base_change' =>
                            $baseChange->__toString(),
                        'base_running_balance' =>
                            $runningBaseBalance
                                ->__toString(),
                        'description' =>
                            $entry->description,
                    ];
                }
            },
        );
    }

        /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildPrintable(
        Supplier $supplier,
        User $actor,
        array $filters,
    ): array {
        $tenant = $this->tenantContext->tenant();

        $context = $this->statementContext(
            supplier: $supplier,
            actor: $actor,
            filters: $filters,
        );

        $baseQuery = $this->statementBaseQuery(
            supplier: $supplier,
            actor: $actor,
            branchId: $context['branch_id'],
            currencyCode: $context['currency_code'],
        );

        $openingQuery = (clone $baseQuery)
            ->whereDate(
                'posting_date',
                '<',
                $context['date_from'],
            );

        $periodQuery = (clone $baseQuery)
            ->whereDate(
                'posting_date',
                '>=',
                $context['date_from'],
            )
            ->whereDate(
                'posting_date',
                '<=',
                $context['date_to'],
            );

        $openingCurrencySummary =
            $this->currencyTotals(
                clone $openingQuery,
            );

        $periodCurrencySummary =
            $this->currencyTotals(
                clone $periodQuery,
            );

        $openingBaseSummary =
            $this->baseTotals(
                clone $openingQuery,
            );

        $periodBaseSummary =
            $this->baseTotals(
                clone $periodQuery,
            );

        $runningCurrencyBalances =
            $this->currencyBalanceMap(
                $openingCurrencySummary,
            );

        $runningBaseBalance = $this->money(
            $openingBaseSummary['balance'],
        );

        $entries = (clone $periodQuery)
            ->with([
                'branch:id,name,code,status',
                'createdBy:id,name',
                'reversalOf:id,reference,entry_type,source_document_number',
            ])
            ->orderBy('posting_date')
            ->orderBy('id')
            ->get();

        $entryData = [];

        foreach ($entries as $entry) {
            $currency = mb_strtoupper(
                (string) $entry->currency_code,
            );

            $transactionChange = $this->money(
                $entry->credit_amount,
            )
                ->minus(
                    $this->money(
                        $entry->debit_amount,
                    ),
                )
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                );

            $baseChange = $this->money(
                $entry->base_credit_amount,
            )
                ->minus(
                    $this->money(
                        $entry->base_debit_amount,
                    ),
                )
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                );

            $runningCurrencyBalances[$currency] = (
                $runningCurrencyBalances[$currency]
                    ?? $this->zeroMoney()
            )
                ->plus($transactionChange)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                );

            $runningBaseBalance =
                $runningBaseBalance
                    ->plus($baseChange)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $entryData[] = [
                'id' => (int) $entry->getKey(),
                'reference' => $entry->reference,
                'journal_reference' =>
                    $entry->journal_reference,
                'entry_type' =>
                    $entry->entry_type,
                'entry_type_label' =>
                    $this
                        ->accountsPayableRegistry
                        ->ledgerEntryTypeLabel(
                            $entry->entry_type,
                        ),
                'source_document_number' =>
                    $entry
                        ->source_document_number,
                'document_date' =>
                    $entry
                        ->document_date
                        ->toDateString(),
                'posting_date' =>
                    $entry
                        ->posting_date
                        ->toDateString(),
                'due_date' =>
                    $entry
                        ->due_date
                        ?->toDateString(),
                'branch' => [
                    'id' => (int) $entry->branch_id,
                    'code' => $entry->branch?->code,
                    'name' => $entry->branch?->name,
                ],
                'currency_code' => $currency,
                'exchange_rate' =>
                    $entry->exchange_rate,
                'debit_amount' =>
                    $entry->debit_amount,
                'credit_amount' =>
                    $entry->credit_amount,
                'transaction_change' =>
                    $transactionChange
                        ->__toString(),
                'currency_running_balance' =>
                    $runningCurrencyBalances[
                        $currency
                    ]->__toString(),
                'base_debit_amount' =>
                    $entry->base_debit_amount,
                'base_credit_amount' =>
                    $entry->base_credit_amount,
                'base_change' =>
                    $baseChange->__toString(),
                'base_running_balance' =>
                    $runningBaseBalance
                        ->__toString(),
                'description' =>
                    $entry->description,
                'reversal_of' =>
                    $entry->reversalOf === null
                        ? null
                        : [
                            'reference' =>
                                $entry
                                    ->reversalOf
                                    ->reference,
                            'source_document_number' =>
                                $entry
                                    ->reversalOf
                                    ->source_document_number,
                        ],
            ];
        }

        return [
            'supplier' => [
                'id' => (int) $supplier->getKey(),
                'code' => $supplier->code,
                'name' => $supplier->name,
                'status' => $supplier->status,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'payment_terms_days' =>
                    (int) $supplier
                        ->payment_terms_days,
            ],
            'filters' => [
                'supplier_id' =>
                    (int) $supplier->getKey(),
                'branch_id' =>
                    $context['branch_id'],
                'currency_code' =>
                    $context['currency_code'],
                'date_from' =>
                    $context['date_from'],
                'date_to' =>
                    $context['date_to'],
            ],
            'base_currency_code' => mb_strtoupper(
                (string) $tenant->currency_code,
            ),
            'summary' => [
                'base' => [
                    'opening_balance' =>
                        $openingBaseSummary['balance'],
                    'period_debit' =>
                        $periodBaseSummary['debit'],
                    'period_credit' =>
                        $periodBaseSummary['credit'],
                    'closing_balance' =>
                        $this->money(
                            $openingBaseSummary[
                                'balance'
                            ],
                        )
                            ->plus(
                                $this->money(
                                    $periodBaseSummary[
                                        'balance'
                                    ],
                                ),
                            )
                            ->toScale(
                                self::MONEY_SCALE,
                                RoundingMode::HalfUp,
                            )
                            ->__toString(),
                ],
                'currencies' =>
                    $this->closingCurrencySummary(
                        opening:
                            $openingCurrencySummary,
                        period:
                            $periodCurrencySummary,
                    ),
            ],
            'entries' => $entryData,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function build(
        Supplier $supplier,
        User $actor,
        array $filters,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureContext(
            supplier: $supplier,
            actor: $actor,
            tenantId: $tenantId,
        );

        $dateRange = $this->dateRange(
            filters: $filters,
            timezone: $tenant->timezone,
        );

        $branchId = $this->optionalId(
            $filters['branch_id'] ?? null,
        );

        if ($branchId !== null) {
            $this->resolveBranch(
                branchId: $branchId,
                actor: $actor,
            );
        }

        $currencyCode = $this->optionalCurrencyCode(
            $filters['currency_code'] ?? null,
        );

        $perPage = $this->perPage(
            $filters['per_page'] ?? 25,
        );

        $baseQuery = SupplierLedgerEntry::query()
            ->where(
                'supplier_id',
                $supplier->getKey(),
            )
            ->when(
                $branchId !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'branch_id',
                    $branchId,
                ),
            )
            ->when(
                $currencyCode !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'currency_code',
                    $currencyCode,
                ),
            );

        $this->branchAccessService->scopeQuery(
            query: $baseQuery,
            user: $actor,
            branchColumn:
                'supplier_ledger_entries.branch_id',
        );

        $openingQuery = (clone $baseQuery)->whereDate(
            'posting_date',
            '<',
            $dateRange['date_from'],
        );

        $periodQuery = (clone $baseQuery)
            ->whereDate(
                'posting_date',
                '>=',
                $dateRange['date_from'],
            )
            ->whereDate(
                'posting_date',
                '<=',
                $dateRange['date_to'],
            );

        $openingCurrencySummary = $this->currencyTotals(
            clone $openingQuery,
        );

        $periodCurrencySummary = $this->currencyTotals(
            clone $periodQuery,
        );

        $openingBaseSummary = $this->baseTotals(
            clone $openingQuery,
        );

        $periodBaseSummary = $this->baseTotals(
            clone $periodQuery,
        );

        /** @var LengthAwarePaginator<int, SupplierLedgerEntry> $entries */
        $entries = (clone $periodQuery)
            ->with([
                'branch:id,name,code,status',
                'createdBy:id,name',
                'reversalOf:id,reference,entry_type,source_document_number',
            ])
            ->orderBy('posting_date')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $runningCurrencyBalances = $this->currencyBalanceMap(
            $openingCurrencySummary,
        );

        $runningBaseBalance = $this->money(
            $openingBaseSummary['balance'],
        );

        $firstEntry = $entries
            ->getCollection()
            ->first();

        if ($firstEntry instanceof SupplierLedgerEntry) {
            $priorPageQuery = (clone $periodQuery)->where(
                static function (
                    Builder $query,
                ) use ($firstEntry): void {
                    $query
                        ->whereDate(
                            'posting_date',
                            '<',
                            $firstEntry
                                ->posting_date
                                ->toDateString(),
                        )
                        ->orWhere(
                            static function (
                                Builder $sameDateQuery,
                            ) use ($firstEntry): void {
                                $sameDateQuery
                                    ->whereDate(
                                        'posting_date',
                                        $firstEntry
                                            ->posting_date
                                            ->toDateString(),
                                    )
                                    ->where(
                                        'id',
                                        '<',
                                        $firstEntry->getKey(),
                                    );
                            },
                        );
                },
            );

            $priorCurrencySummary = $this->currencyTotals(
                clone $priorPageQuery,
            );

            foreach ($priorCurrencySummary as $summary) {
                $currency = $summary['currency_code'];

                $runningCurrencyBalances[$currency] =
                    ($runningCurrencyBalances[$currency]
                        ?? $this->zeroMoney())
                        ->plus(
                            $this->money(
                                $summary['balance'],
                            ),
                        )
                        ->toScale(
                            self::MONEY_SCALE,
                            RoundingMode::HalfUp,
                        );
            }

            $priorBaseSummary = $this->baseTotals(
                clone $priorPageQuery,
            );

            $runningBaseBalance = $runningBaseBalance
                ->plus(
                    $this->money(
                        $priorBaseSummary['balance'],
                    ),
                )
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                );
        }

        $entryData = [];

        foreach ($entries->getCollection() as $entry) {
            $currency = mb_strtoupper(
                (string) $entry->currency_code,
            );

            $transactionChange = $this->money(
                $entry->credit_amount,
            )->minus(
                $this->money(
                    $entry->debit_amount,
                ),
            )->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );

            $baseChange = $this->money(
                $entry->base_credit_amount,
            )->minus(
                $this->money(
                    $entry->base_debit_amount,
                ),
            )->toScale(
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );

            $runningCurrencyBalances[$currency] =
                ($runningCurrencyBalances[$currency]
                    ?? $this->zeroMoney())
                    ->plus($transactionChange)
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    );

            $runningBaseBalance = $runningBaseBalance
                ->plus($baseChange)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                );

            $entryData[] = [
                'id' => (int) $entry->getKey(),
                'reference' => $entry->reference,
                'journal_reference' =>
                    $entry->journal_reference,
                'entry_type' => $entry->entry_type,
                'entry_type_label' =>
                    $this->accountsPayableRegistry
                        ->ledgerEntryTypeLabel(
                            $entry->entry_type,
                        ),
                'source_type' => $entry->source_type,
                'source_id' => (int) $entry->source_id,
                'source_document_number' =>
                    $entry->source_document_number,
                'document_date' =>
                    $entry->document_date->toDateString(),
                'posting_date' =>
                    $entry->posting_date->toDateString(),
                'due_date' =>
                    $entry->due_date?->toDateString(),
                'branch' => [
                    'id' => (int) $entry->branch_id,
                    'code' => $entry->branch?->code,
                    'name' => $entry->branch?->name,
                ],
                'currency_code' => $currency,
                'exchange_rate' => $entry->exchange_rate,
                'debit_amount' => $entry->debit_amount,
                'credit_amount' => $entry->credit_amount,
                'transaction_change' =>
                    $transactionChange->__toString(),
                'currency_running_balance' =>
                    $runningCurrencyBalances[$currency]
                        ->__toString(),
                'base_debit_amount' =>
                    $entry->base_debit_amount,
                'base_credit_amount' =>
                    $entry->base_credit_amount,
                'base_change' =>
                    $baseChange->__toString(),
                'base_running_balance' =>
                    $runningBaseBalance->__toString(),
                'description' => $entry->description,
                'created_by' => $entry->createdBy === null
                    ? null
                    : [
                        'id' => (int) $entry
                            ->createdBy
                            ->getKey(),
                        'name' => $entry
                            ->createdBy
                            ->name,
                    ],
                'reversal_of' => $entry->reversalOf === null
                    ? null
                    : [
                        'id' => (int) $entry
                            ->reversalOf
                            ->getKey(),
                        'reference' => $entry
                            ->reversalOf
                            ->reference,
                        'entry_type' => $entry
                            ->reversalOf
                            ->entry_type,
                        'source_document_number' => $entry
                            ->reversalOf
                            ->source_document_number,
                    ],
            ];
        }

        return [
            'supplier' => [
                'id' => (int) $supplier->getKey(),
                'code' => $supplier->code,
                'name' => $supplier->name,
                'status' => $supplier->status,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'payment_terms_days' =>
                    (int) $supplier->payment_terms_days,
            ],

            'filters' => [
                'supplier_id' =>
                    (int) $supplier->getKey(),
                'branch_id' => $branchId,
                'currency_code' => $currencyCode,
                'date_from' => $dateRange['date_from'],
                'date_to' => $dateRange['date_to'],
                'per_page' => $perPage,
            ],

            'base_currency_code' => mb_strtoupper(
                (string) $tenant->currency_code,
            ),

            'summary' => [
                'base' => [
                    'opening_balance' =>
                        $openingBaseSummary['balance'],
                    'period_debit' =>
                        $periodBaseSummary['debit'],
                    'period_credit' =>
                        $periodBaseSummary['credit'],
                    'closing_balance' => $this->money(
                        $openingBaseSummary['balance'],
                    )->plus(
                        $this->money(
                            $periodBaseSummary['balance'],
                        ),
                    )->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HalfUp,
                    )->__toString(),
                ],
                'currencies' => $this->closingCurrencySummary(
                    opening: $openingCurrencySummary,
                    period: $periodCurrencySummary,
                ),
            ],

            'entries' => [
                'data' => $entryData,
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'from' => $entries->firstItem(),
                    'to' => $entries->lastItem(),
                    'total' => $entries->total(),
                ],
            ],
        ];
    }

        /**
     * @param array<string, mixed> $filters
     * @return array{
     *     branch_id: int|null,
     *     currency_code: string|null,
     *     date_from: string,
     *     date_to: string
     * }
     */
    private function statementContext(
        Supplier $supplier,
        User $actor,
        array $filters,
    ): array {
        $tenant = $this->tenantContext->tenant();

        $this->ensureContext(
            supplier: $supplier,
            actor: $actor,
            tenantId: (int) $tenant->getKey(),
        );

        $dateRange = $this->dateRange(
            filters: $filters,
            timezone: $tenant->timezone,
        );

        $branchId = $this->optionalId(
            $filters['branch_id'] ?? null,
        );

        if ($branchId !== null) {
            $this->resolveBranch(
                branchId: $branchId,
                actor: $actor,
            );
        }

        return [
            'branch_id' => $branchId,
            'currency_code' =>
                $this->optionalCurrencyCode(
                    $filters['currency_code']
                        ?? null,
                ),
            'date_from' =>
                $dateRange['date_from'],
            'date_to' =>
                $dateRange['date_to'],
        ];
    }

    /**
     * @return Builder<SupplierLedgerEntry>
     */
    private function statementBaseQuery(
        Supplier $supplier,
        User $actor,
        ?int $branchId,
        ?string $currencyCode,
    ): Builder {
        $query = SupplierLedgerEntry::query()
            ->where(
                'supplier_id',
                $supplier->getKey(),
            )
            ->when(
                $branchId !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'branch_id',
                    $branchId,
                ),
            )
            ->when(
                $currencyCode !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'currency_code',
                    $currencyCode,
                ),
            );

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn:
                'supplier_ledger_entries.branch_id',
        );

        return $query;
    }

    /**
     * @param Builder<SupplierLedgerEntry> $query
     * @return list<array{
     *     currency_code: string,
     *     debit: string,
     *     credit: string,
     *     balance: string
     * }>
     */
    private function currencyTotals(Builder $query): array
    {
        return $query
            ->select('currency_code')
            ->selectRaw(
                'COALESCE(SUM(debit_amount), 0) AS debit_total',
            )
            ->selectRaw(
                'COALESCE(SUM(credit_amount), 0) AS credit_total',
            )
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get()
            ->map(
                function (
                    SupplierLedgerEntry $entry,
                ): array {
                    $debit = $this->money(
                        $entry->getAttribute(
                            'debit_total',
                        ),
                    );

                    $credit = $this->money(
                        $entry->getAttribute(
                            'credit_total',
                        ),
                    );

                    return [
                        'currency_code' => mb_strtoupper(
                            (string) $entry->currency_code,
                        ),
                        'debit' => $debit->__toString(),
                        'credit' => $credit->__toString(),
                        'balance' => $credit
                            ->minus($debit)
                            ->toScale(
                                self::MONEY_SCALE,
                                RoundingMode::HalfUp,
                            )
                            ->__toString(),
                    ];
                },
            )
            ->values()
            ->all();
    }

    /**
     * @param Builder<SupplierLedgerEntry> $query
     * @return array{debit: string, credit: string, balance: string}
     */
    private function baseTotals(Builder $query): array
    {
        $totals = $query
            ->selectRaw(
                'COALESCE(SUM(base_debit_amount), 0) AS debit_total',
            )
            ->selectRaw(
                'COALESCE(SUM(base_credit_amount), 0) AS credit_total',
            )
            ->first();

        $debit = $this->money(
            $totals?->getAttribute('debit_total') ?? '0',
        );

        $credit = $this->money(
            $totals?->getAttribute('credit_total') ?? '0',
        );

        return [
            'debit' => $debit->__toString(),
            'credit' => $credit->__toString(),
            'balance' => $credit
                ->minus($debit)
                ->toScale(
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                )
                ->__toString(),
        ];
    }

    /**
     * @param list<array{currency_code: string, balance: string}> $summary
     * @return array<string, BigDecimal>
     */
    private function currencyBalanceMap(array $summary): array
    {
        $balances = [];

        foreach ($summary as $row) {
            $balances[$row['currency_code']] = $this->money(
                $row['balance'],
            );
        }

        return $balances;
    }

    /**
     * @param list<array{currency_code: string, debit: string, credit: string, balance: string}> $opening
     * @param list<array{currency_code: string, debit: string, credit: string, balance: string}> $period
     * @return list<array{
     *     currency_code: string,
     *     opening_balance: string,
     *     period_debit: string,
     *     period_credit: string,
     *     closing_balance: string
     * }>
     */
    private function closingCurrencySummary(
        array $opening,
        array $period,
    ): array {
        $rows = [];

        foreach ($opening as $summary) {
            $rows[$summary['currency_code']] = [
                'currency_code' => $summary['currency_code'],
                'opening_balance' => $summary['balance'],
                'period_debit' => '0.000000',
                'period_credit' => '0.000000',
                'period_balance' => '0.000000',
            ];
        }

        foreach ($period as $summary) {
            $currency = $summary['currency_code'];

            $rows[$currency] ??= [
                'currency_code' => $currency,
                'opening_balance' => '0.000000',
                'period_debit' => '0.000000',
                'period_credit' => '0.000000',
                'period_balance' => '0.000000',
            ];

            $rows[$currency]['period_debit'] =
                $summary['debit'];
            $rows[$currency]['period_credit'] =
                $summary['credit'];
            $rows[$currency]['period_balance'] =
                $summary['balance'];
        }

        ksort($rows);

        return array_values(
            array_map(
                function (array $row): array {
                    return [
                        'currency_code' =>
                            $row['currency_code'],
                        'opening_balance' =>
                            $row['opening_balance'],
                        'period_debit' =>
                            $row['period_debit'],
                        'period_credit' =>
                            $row['period_credit'],
                        'closing_balance' => $this->money(
                            $row['opening_balance'],
                        )->plus(
                            $this->money(
                                $row['period_balance'],
                            ),
                        )->toScale(
                            self::MONEY_SCALE,
                            RoundingMode::HalfUp,
                        )->__toString(),
                    ];
                },
                $rows,
            ),
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{date_from: string, date_to: string}
     */
    private function dateRange(
        array $filters,
        string $timezone,
    ): array {
        $today = CarbonImmutable::now(
            $timezone,
        )->toDateString();

        $dateTo = $this->dateString(
            $filters['date_to'] ?? null,
            $today,
            $timezone,
            'date_to',
        );

        $defaultDateFrom = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $dateTo,
            $timezone,
        );

        if (!$defaultDateFrom instanceof CarbonImmutable) {
            throw new LogicException(
                'The Supplier Statement ending date is invalid.',
            );
        }

        $dateFrom = $this->dateString(
            $filters['date_from'] ?? null,
            $defaultDateFrom
                ->startOfMonth()
                ->toDateString(),
            $timezone,
            'date_from',
        );

        if ($dateFrom > $dateTo) {
            throw ValidationException::withMessages([
                'date_to' => [
                    'The statement ending date cannot be before the starting date.',
                ],
            ]);
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function dateString(
        mixed $value,
        string $default,
        string $timezone,
        string $field,
    ): string {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        try {
            return CarbonImmutable::parse(
                (string) $value,
                $timezone,
            )->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must be valid.',
                ],
            ]);
        }
    }

    private function resolveBranch(
        int $branchId,
        User $actor,
    ): Branch {
        $branch = $this->branchAccessService
            ->findAccessibleBranch(
                user: $actor,
                branchId: $branchId,
                requireActive: false,
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

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        if ($id === false) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'The selected identifier is invalid.',
                ],
            ]);
        }

        return (int) $id;
    }

    private function optionalCurrencyCode(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $currencyCode = mb_strtoupper(
            trim((string) $value),
        );

        if (
            preg_match(
                '/^[A-Z]{3}$/',
                $currencyCode,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'currency_code' => [
                    'The currency code must contain exactly three letters.',
                ],
            ]);
        }

        return $currencyCode;
    }

    private function perPage(mixed $value): int
    {
        $perPage = (int) $value;

        return in_array(
            $perPage,
            [
                10,
                15,
                25,
                50,
                100,
            ],
            true,
        )
            ? $perPage
            : 25;
    }

    private function money(mixed $value): BigDecimal
    {
        return BigDecimal::of(
            (string) ($value ?? '0'),
        )->toScale(
            self::MONEY_SCALE,
            RoundingMode::HalfUp,
        );
    }

    private function zeroMoney(): BigDecimal
    {
        return BigDecimal::zero()->toScale(
            self::MONEY_SCALE,
        );
    }

    private function ensureContext(
        Supplier $supplier,
        User $actor,
        int $tenantId,
    ): void {
        if (
            (int) $supplier->tenant_id !== $tenantId
            || (int) $actor->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The Supplier Statement context contains records from different tenants.',
            );
        }
    }
}