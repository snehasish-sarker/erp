<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\AccountsReceivableRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerStatementService
{
    private const MONEY_SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly AccountsReceivableRegistry $accountsReceivableRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function normalizeExportFilters(
        Customer $customer,
        User $actor,
        array $filters,
    ): array {
        return $this->context(
            customer: $customer,
            actor: $actor,
            filters: $filters,
        );
    }

    /** @param array<string, mixed> $filters */
    public function exportTotalRows(
        Customer $customer,
        User $actor,
        array $filters,
    ): int {
        $context = $this->context($customer, $actor, $filters);
        $base = $this->baseQuery($customer, $actor, $context);
        $period = (clone $base)
            ->whereDate('posting_date', '>=', $context['date_from'])
            ->whereDate('posting_date', '<=', $context['date_to']);

        $currencyCount = count(
            $this->currencySummary(
                baseQuery: $base,
                dateFrom: (string) $context['date_from'],
                dateTo: (string) $context['date_to'],
            ),
        );

        return 1 + $currencyCount + $period->count();
    }

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportRows(
        Customer $customer,
        User $actor,
        array $filters,
    ): LazyCollection {
        $context = $this->context($customer, $actor, $filters);
        $base = $this->baseQuery($customer, $actor, $context);
        $opening = $this->baseTotals(
            (clone $base)->whereDate(
                'posting_date',
                '<',
                $context['date_from'],
            ),
        );
        $periodTotals = $this->baseTotals(
            (clone $base)
                ->whereDate('posting_date', '>=', $context['date_from'])
                ->whereDate('posting_date', '<=', $context['date_to']),
        );
        $currencySummary = $this->currencySummary(
            baseQuery: $base,
            dateFrom: (string) $context['date_from'],
            dateTo: (string) $context['date_to'],
        );
        $runningBase = $this->money($opening['balance']);
        $runningCurrencies = $this->currencyOpeningMap($currencySummary);

        return LazyCollection::make(
            function () use (
                $customer,
                $context,
                $base,
                $opening,
                $periodTotals,
                $currencySummary,
                &$runningBase,
                &$runningCurrencies,
            ): \Generator {
                yield [
                    'row_type' => 'base_summary',
                    'customer_code' => $customer->code,
                    'customer_name' => $customer->name,
                    'date_from' => $context['date_from'],
                    'date_to' => $context['date_to'],
                    'currency_code' => $this->baseCurrencyCode(),
                    'opening_balance' => $opening['balance'],
                    'period_debit' => $periodTotals['debit'],
                    'period_credit' => $periodTotals['credit'],
                    'closing_balance' => $this->decimal(
                        $this->money($opening['balance'])
                            ->plus($this->money($periodTotals['balance'])),
                    ),
                ];

                foreach ($currencySummary as $summary) {
                    yield [
                        'row_type' => 'currency_summary',
                        'customer_code' => $customer->code,
                        'customer_name' => $customer->name,
                        'date_from' => $context['date_from'],
                        'date_to' => $context['date_to'],
                        ...$summary,
                    ];
                }

                foreach (
                    (clone $base)
                        ->whereDate('posting_date', '>=', $context['date_from'])
                        ->whereDate('posting_date', '<=', $context['date_to'])
                        ->orderBy('posting_date')
                        ->orderBy('id')
                        ->cursor()
                    as $entry
                ) {
                    if (!$entry instanceof CustomerLedgerEntry) {
                        continue;
                    }

                    $normalized = $this->normalizeEntry(
                        entry: $entry,
                        runningBase: $runningBase,
                        runningCurrencies: $runningCurrencies,
                    );

                    yield [
                        'row_type' => 'entry',
                        'customer_code' => $customer->code,
                        'customer_name' => $customer->name,
                        'date_from' => $context['date_from'],
                        'date_to' => $context['date_to'],
                        ...$normalized,
                    ];
                }
            },
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function build(
        Customer $customer,
        User $actor,
        array $filters,
    ): array {
        $context = $this->context($customer, $actor, $filters);
        $base = $this->baseQuery($customer, $actor, $context);

        $openingQuery = (clone $base)
            ->whereDate('posting_date', '<', $context['date_from']);

        $periodQuery = (clone $base)
            ->whereDate('posting_date', '>=', $context['date_from'])
            ->whereDate('posting_date', '<=', $context['date_to']);

        $opening = $this->baseTotals(clone $openingQuery);
        $periodTotals = $this->baseTotals(clone $periodQuery);
        $currencySummary = $this->currencySummary(
            baseQuery: $base,
            dateFrom: (string) $context['date_from'],
            dateTo: (string) $context['date_to'],
        );

        /** @var LengthAwarePaginator<CustomerLedgerEntry> $paginator */
        $paginator = (clone $periodQuery)
            ->orderBy('posting_date')
            ->orderBy('id')
            ->paginate((int) $context['per_page'])
            ->withQueryString();

        $runningBase = $this->money($opening['balance']);
        $runningCurrencies = $this->currencyOpeningMap($currencySummary);

        $first = collect($paginator->items())->first();

        if ($first instanceof CustomerLedgerEntry) {
            $previous = (clone $periodQuery)
                ->where(
                    static function (Builder $query) use ($first): void {
                        $query
                            ->whereDate(
                                'posting_date',
                                '<',
                                $first->posting_date,
                            )
                            ->orWhere(
                                static function (Builder $query) use ($first): void {
                                    $query
                                        ->whereDate(
                                            'posting_date',
                                            '=',
                                            $first->posting_date,
                                        )
                                        ->where('id', '<', $first->getKey());
                                },
                            );
                    },
                );

            $previousBase = $this->baseTotals(clone $previous);
            $runningBase = $runningBase->plus(
                $this->money($previousBase['balance']),
            );

            foreach ($this->currencyTotals(clone $previous) as $currency => $totals) {
                $runningCurrencies[$currency] = (
                    $runningCurrencies[$currency]
                    ?? BigDecimal::zero()
                )->plus($this->money($totals['balance']));
            }
        }

        $entries = collect($paginator->items())
            ->map(
                function (CustomerLedgerEntry $entry) use (
                    &$runningBase,
                    &$runningCurrencies,
                ): array {
                    return $this->normalizeEntry(
                        entry: $entry,
                        runningBase: $runningBase,
                        runningCurrencies: $runningCurrencies,
                    );
                },
            )
            ->values()
            ->all();

        return [
            'customer' => $this->customerReference($customer),
            'filters' => $context,
            'base_currency_code' => $this->baseCurrencyCode(),
            'summary' => [
                'base' => [
                    'opening_balance' => $opening['balance'],
                    'period_debit' => $periodTotals['debit'],
                    'period_credit' => $periodTotals['credit'],
                    'closing_balance' => $this->decimal(
                        $this->money($opening['balance'])
                            ->plus($this->money($periodTotals['balance'])),
                    ),
                ],
                'currencies' => $currencySummary,
            ],
            'entries' => [
                'data' => $entries,
                'meta' => $this->paginationMeta($paginator),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildPrintable(
        Customer $customer,
        User $actor,
        array $filters,
    ): array {
        $context = $this->context($customer, $actor, $filters);
        $base = $this->baseQuery($customer, $actor, $context);
        $opening = $this->baseTotals(
            (clone $base)->whereDate('posting_date', '<', $context['date_from']),
        );
        $periodQuery = (clone $base)
            ->whereDate('posting_date', '>=', $context['date_from'])
            ->whereDate('posting_date', '<=', $context['date_to']);
        $periodTotals = $this->baseTotals(clone $periodQuery);
        $currencySummary = $this->currencySummary(
            baseQuery: $base,
            dateFrom: (string) $context['date_from'],
            dateTo: (string) $context['date_to'],
        );
        $runningBase = $this->money($opening['balance']);
        $runningCurrencies = $this->currencyOpeningMap($currencySummary);

        $entries = (clone $periodQuery)
            ->orderBy('posting_date')
            ->orderBy('id')
            ->cursor()
            ->map(
                function (CustomerLedgerEntry $entry) use (
                    &$runningBase,
                    &$runningCurrencies,
                ): array {
                    return $this->normalizeEntry(
                        entry: $entry,
                        runningBase: $runningBase,
                        runningCurrencies: $runningCurrencies,
                    );
                },
            )
            ->values()
            ->all();

        return [
            'customer' => $this->customerReference($customer),
            'filters' => $context,
            'base_currency_code' => $this->baseCurrencyCode(),
            'summary' => [
                'base' => [
                    'opening_balance' => $opening['balance'],
                    'period_debit' => $periodTotals['debit'],
                    'period_credit' => $periodTotals['credit'],
                    'closing_balance' => $this->decimal(
                        $this->money($opening['balance'])
                            ->plus($this->money($periodTotals['balance'])),
                    ),
                ],
                'currencies' => $currencySummary,
            ],
            'entries' => $entries,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function baseQuery(
        Customer $customer,
        User $actor,
        array $context,
    ): Builder {
        $query = CustomerLedgerEntry::query()
            ->with([
                'createdBy:id,name',
                'reversalOf:id,reference,entry_type,source_document_number',
            ])
            ->where('customer_id', $customer->getKey());

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn: 'customer_ledger_entries.branch_id',
        );

        return $query
            ->when(
                $context['branch_id'] !== null,
                static fn (Builder $query): Builder =>
                    $query->where(
                        'branch_id',
                        $context['branch_id'],
                    ),
            )
            ->when(
                $context['currency_code'] !== null,
                static fn (Builder $query): Builder =>
                    $query->where(
                        'currency_code',
                        $context['currency_code'],
                    ),
            );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function context(
        Customer $customer,
        User $actor,
        array $filters,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        if (
            (int) $customer->tenant_id !== $tenantId
            || (int) $actor->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'Customer Statement context crossed a tenant boundary.',
            );
        }

        $today = CarbonImmutable::now($tenant->timezone);
        $dateTo = $this->date(
            value: $filters['date_to'] ?? null,
            default: $today->toDateString(),
            field: 'date_to',
            timezone: $tenant->timezone,
        );
        $dateFrom = $this->date(
            value: $filters['date_from'] ?? null,
            default: CarbonImmutable::parse($dateTo, $tenant->timezone)
                ->startOfMonth()
                ->toDateString(),
            field: 'date_from',
            timezone: $tenant->timezone,
        );

        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => [
                    'The ending date must be on or after the starting date.',
                ],
            ]);
        }

        $branchId = $this->nullableId($filters['branch_id'] ?? null);

        if ($branchId !== null) {
            $branch = $this->branchAccessService->findAccessibleBranch(
                user: $actor,
                branchId: $branchId,
                requireActive: false,
            );

            if (!($branch instanceof Branch)) {
                throw ValidationException::withMessages([
                    'branch_id' => [
                        'The selected branch is unavailable or outside your access.',
                    ],
                ]);
            }
        }

        return [
            'customer_id' => (int) $customer->getKey(),
            'branch_id' => $branchId,
            'currency_code' => $this->currencyCode(
                $filters['currency_code'] ?? null,
            ),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => $this->perPage($filters['per_page'] ?? 25),
        ];
    }

    private function normalizeEntry(
        CustomerLedgerEntry $entry,
        BigDecimal &$runningBase,
        array &$runningCurrencies,
    ): array {
        $currency = mb_strtoupper((string) $entry->currency_code);
        $transactionChange = $this->money($entry->debit_amount)
            ->minus($this->money($entry->credit_amount));
        $baseChange = $this->money($entry->base_debit_amount)
            ->minus($this->money($entry->base_credit_amount));

        $runningCurrencies[$currency] = (
            $runningCurrencies[$currency]
            ?? BigDecimal::zero()
        )->plus($transactionChange);

        $runningBase = $runningBase->plus($baseChange);

        return [
            'id' => (int) $entry->getKey(),
            'reference' => $entry->reference,
            'journal_reference' => $entry->journal_reference,
            'entry_type' => $entry->entry_type,
            'entry_type_label' => $this->accountsReceivableRegistry
                ->ledgerEntryTypeLabel($entry->entry_type),
            'source_type' => $entry->source_type,
            'source_id' => (int) $entry->source_id,
            'source_document_number' => $entry->source_document_number,
            'document_date' => $entry->document_date->toDateString(),
            'posting_date' => $entry->posting_date->toDateString(),
            'due_date' => $entry->due_date?->toDateString(),
            'branch' => [
                'id' => (int) $entry->branch_id,
                'code' => $entry->branch?->code,
                'name' => $entry->branch?->name,
            ],
            'currency_code' => $currency,
            'exchange_rate' => (string) $entry->exchange_rate,
            'debit_amount' => (string) $entry->debit_amount,
            'credit_amount' => (string) $entry->credit_amount,
            'transaction_change' => $this->decimal($transactionChange),
            'currency_running_balance' => $this->decimal(
                $runningCurrencies[$currency],
            ),
            'base_debit_amount' => (string) $entry->base_debit_amount,
            'base_credit_amount' => (string) $entry->base_credit_amount,
            'base_change' => $this->decimal($baseChange),
            'base_running_balance' => $this->decimal($runningBase),
            'description' => (string) ($entry->description ?? ''),
            'created_by' => $entry->createdBy instanceof User
                ? [
                    'id' => (int) $entry->createdBy->getKey(),
                    'name' => $entry->createdBy->name,
                ]
                : null,
            'reversal_of' => $entry->reversalOf instanceof CustomerLedgerEntry
                ? [
                    'id' => (int) $entry->reversalOf->getKey(),
                    'reference' => $entry->reversalOf->reference,
                    'entry_type' => $entry->reversalOf->entry_type,
                    'source_document_number' => $entry->reversalOf->source_document_number,
                ]
                : null,
        ];
    }

    private function baseTotals(Builder $query): array
    {
        $row = $query
            ->selectRaw('COALESCE(SUM(base_debit_amount), 0) AS debit')
            ->selectRaw('COALESCE(SUM(base_credit_amount), 0) AS credit')
            ->selectRaw('COALESCE(SUM(base_debit_amount - base_credit_amount), 0) AS balance')
            ->first();

        return [
            'debit' => $this->decimalString($row?->debit),
            'credit' => $this->decimalString($row?->credit),
            'balance' => $this->decimalString($row?->balance),
        ];
    }

    /**
     * @return array<string, array{debit: string, credit: string, balance: string}>
     */
    private function currencyTotals(Builder $query): array
    {
        return $query
            ->select('currency_code')
            ->selectRaw('COALESCE(SUM(debit_amount), 0) AS debit')
            ->selectRaw('COALESCE(SUM(credit_amount), 0) AS credit')
            ->selectRaw('COALESCE(SUM(debit_amount - credit_amount), 0) AS balance')
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get()
            ->mapWithKeys(
                fn (CustomerLedgerEntry $row): array => [
                    mb_strtoupper((string) $row->currency_code) => [
                        'debit' => $this->decimalString($row->getAttribute('debit')),
                        'credit' => $this->decimalString($row->getAttribute('credit')),
                        'balance' => $this->decimalString($row->getAttribute('balance')),
                    ],
                ],
            )
            ->all();
    }

    /** @return list<array<string, string>> */
    private function currencySummary(
        Builder $baseQuery,
        string $dateFrom,
        string $dateTo,
    ): array {
        $opening = $this->currencyTotals(
            (clone $baseQuery)->whereDate('posting_date', '<', $dateFrom),
        );
        $period = $this->currencyTotals(
            (clone $baseQuery)
                ->whereDate('posting_date', '>=', $dateFrom)
                ->whereDate('posting_date', '<=', $dateTo),
        );
        $currencies = array_values(array_unique([
            ...array_keys($opening),
            ...array_keys($period),
        ]));
        sort($currencies);

        $rows = [];

        foreach ($currencies as $currency) {
            $openingBalance = $this->money($opening[$currency]['balance'] ?? 0);
            $periodDebit = $this->money($period[$currency]['debit'] ?? 0);
            $periodCredit = $this->money($period[$currency]['credit'] ?? 0);

            $rows[] = [
                'currency_code' => $currency,
                'opening_balance' => $this->decimal($openingBalance),
                'period_debit' => $this->decimal($periodDebit),
                'period_credit' => $this->decimal($periodCredit),
                'closing_balance' => $this->decimal(
                    $openingBalance
                        ->plus($periodDebit)
                        ->minus($periodCredit),
                ),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, string>> $currencySummary
     * @return array<string, BigDecimal>
     */
    private function currencyOpeningMap(array $currencySummary): array
    {
        $map = [];

        foreach ($currencySummary as $summary) {
            $map[$summary['currency_code']] = $this->money(
                $summary['opening_balance'],
            );
        }

        return $map;
    }

    /** @return array<string, mixed> */
    private function customerReference(Customer $customer): array
    {
        return [
            'id' => (int) $customer->getKey(),
            'code' => $customer->code,
            'name' => $customer->name,
            'status' => $customer->status,
            'customer_type' => $customer->customer_type,
            'contact_person' => $customer->contact_person,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'payment_terms_days' => (int) $customer->payment_terms_days,
            'credit_limit' => (string) $customer->credit_limit,
        ];
    }

    private function date(
        mixed $value,
        string $default,
        string $field,
        string $timezone,
    ): string {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        $value = trim($value);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        if (!$date instanceof CarbonImmutable || $date->toDateString() !== $value) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        return $value;
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return $id === false ? null : (int) $id;
    }

    private function currencyCode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $currencyCode = mb_strtoupper(trim((string) $value));

        if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw ValidationException::withMessages([
                'currency_code' => [
                    'The currency must be a valid three-letter code.',
                ],
            ]);
        }

        return $currencyCode;
    }

    private function perPage(mixed $value): int
    {
        $perPage = filter_var($value, FILTER_VALIDATE_INT);

        return in_array($perPage, [10, 15, 25, 50, 100], true)
            ? (int) $perPage
            : 25;
    }

    /** @return array<string, int|null> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    private function baseCurrencyCode(): string
    {
        return mb_strtoupper(
            (string) $this->tenantContext->tenant()->currency_code,
        );
    }

    private function money(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? '0'))
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );
    }

    private function decimal(BigDecimal $value): string
    {
        return $value
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();
    }

    private function decimalString(mixed $value): string
    {
        return $this->decimal($this->money($value));
    }
}