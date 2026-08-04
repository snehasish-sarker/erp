<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class JournalEntryValidationService
{
    private const MONEY_SCALE = 6;

    private const RATE_SCALE = 8;

    private const MAX_LINES = 500;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array{
     *     currency_code: string,
     *     exchange_rate: string,
     *     lines: list<array{
     *         line_number: int,
     *         account_id: int,
     *         branch_id: int,
     *         supplier_id: int|null,
     *         customer_id: int|null,
     *         reference: string|null,
     *         description: string,
     *         due_date: string|null,
     *         currency_code: string,
     *         exchange_rate: string,
     *         debit_amount: string,
     *         credit_amount: string,
     *         base_debit_amount: string,
     *         base_credit_amount: string
     *     }>,
     *     total_debit: string,
     *     total_credit: string,
     *     base_total_debit: string,
     *     base_total_credit: string
     * }
     */
    public function validateAndNormalize(
        array $lines,
        Branch $branch,
        string $currencyCode,
        string $exchangeRate,
        bool $manualPosting,
        bool $requireActiveAccounts = true,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        if ((int) $branch->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'The selected branch does not belong to the active tenant.',
                ],
            ]);
        }

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A journal entry requires at least two lines.',
                ],
            ]);
        }

        if (count($lines) > self::MAX_LINES) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A journal entry may not contain more than 500 lines.',
                ],
            ]);
        }

        $currencyCode = $this->normalizeCurrencyCode(
            $currencyCode,
        );

        $exchangeRateDecimal = $this->positiveDecimal(
            value: $exchangeRate,
            scale: self::RATE_SCALE,
            field: 'exchange_rate',
            label: 'exchange rate',
        );

        if (
            $currencyCode === mb_strtoupper(
                (string) $tenant->currency_code,
            )
            && !$exchangeRateDecimal->isEqualTo(
                BigDecimal::one()->toScale(
                    self::RATE_SCALE,
                ),
            )
        ) {
            throw ValidationException::withMessages([
                'exchange_rate' => [
                    'The exchange rate must be 1.00000000 for the tenant base currency.',
                ],
            ]);
        }

        $accountIds = [];
        $supplierIds = [];
        $customerIds = [];

        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each journal line must be an object.',
                    ],
                ]);
            }

            $accountIds[] = $this->positiveInteger(
                value: $line['account_id'] ?? null,
                field: "lines.{$index}.account_id",
                label: 'account',
            );

            $supplierId = $this->nullablePositiveInteger(
                value: $line['supplier_id'] ?? null,
                field: "lines.{$index}.supplier_id",
                label: 'supplier',
            );

            $customerId = $this->nullablePositiveInteger(
                value: $line['customer_id'] ?? null,
                field: "lines.{$index}.customer_id",
                label: 'customer',
            );

            if ($supplierId !== null) {
                $supplierIds[] = $supplierId;
            }

            if ($customerId !== null) {
                $customerIds[] = $customerId;
            }
        }

        /** @var Collection<int, Account> $accounts */
        $accounts = Account::query()
            ->whereIn(
                'id',
                array_values(
                    array_unique($accountIds),
                ),
            )
            ->get()
            ->keyBy('id');

        /** @var Collection<int, Supplier> $suppliers */
        $suppliers = Supplier::query()
            ->whereIn(
                'id',
                array_values(
                    array_unique($supplierIds),
                ),
            )
            ->get()
            ->keyBy('id');

        /** @var Collection<int, Customer> $customers */
        $customers = Customer::query()
            ->whereIn(
                'id',
                array_values(
                    array_unique($customerIds),
                ),
            )
            ->get()
            ->keyBy('id');

        $normalizedLines = [];
        $totalDebit = $this->zeroMoney();
        $totalCredit = $this->zeroMoney();
        $baseTotalDebit = $this->zeroMoney();
        $baseTotalCredit = $this->zeroMoney();

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $accountId = $accountIds[$index];
            $account = $accounts->get($accountId);

            if (!$account instanceof Account) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => [
                        'The selected account is unavailable.',
                    ],
                ]);
            }

            if (
                $requireActiveAccounts
                && !$account->isActive()
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => [
                        'The selected account is inactive.',
                    ],
                ]);
            }

            if (!$account->isPostingAccount()) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => [
                        'Group accounts cannot receive journal postings.',
                    ],
                ]);
            }

            if (
                $manualPosting
                && !$account->allowsManualPosting()
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => [
                        'The selected account does not allow manual posting.',
                    ],
                ]);
            }

            $lineBranchId = $this->nullablePositiveInteger(
                value: $line['branch_id'] ?? null,
                field: "lines.{$index}.branch_id",
                label: 'branch',
            ) ?? (int) $branch->getKey();

            if (
                $lineBranchId
                !== (int) $branch->getKey()
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.branch_id" => [
                        'Cross-branch journal lines are not supported. Every line must use the journal branch.',
                    ],
                ]);
            }

            $supplierId = $this->nullablePositiveInteger(
                value: $line['supplier_id'] ?? null,
                field: "lines.{$index}.supplier_id",
                label: 'supplier',
            );

            $customerId = $this->nullablePositiveInteger(
                value: $line['customer_id'] ?? null,
                field: "lines.{$index}.customer_id",
                label: 'customer',
            );

            if (
                $supplierId !== null
                && $customerId !== null
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.supplier_id" => [
                        'A journal line cannot reference both a supplier and a customer.',
                    ],
                    "lines.{$index}.customer_id" => [
                        'A journal line cannot reference both a supplier and a customer.',
                    ],
                ]);
            }

            if (
                $supplierId !== null
                && !$suppliers->get(
                    $supplierId,
                ) instanceof Supplier
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.supplier_id" => [
                        'The selected supplier is unavailable.',
                    ],
                ]);
            }

            if (
                $customerId !== null
                && !$customers->get(
                    $customerId,
                ) instanceof Customer
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.customer_id" => [
                        'The selected customer is unavailable.',
                    ],
                ]);
            }

            if (
                $account->control_type
                === 'accounts_payable'
            ) {
                if (
                    $supplierId === null
                    || $customerId !== null
                ) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.supplier_id" => [
                            'An Accounts Payable control line requires a supplier and cannot reference a customer.',
                        ],
                    ]);
                }
            }

            if (
                $account->control_type
                === 'accounts_receivable'
            ) {
                if (
                    $customerId === null
                    || $supplierId !== null
                ) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.customer_id" => [
                            'An Accounts Receivable control line requires a customer and cannot reference a supplier.',
                        ],
                    ]);
                }
            }

            $lineCurrencyCode = isset(
                $line['currency_code'],
            )
                && trim(
                    (string) $line['currency_code'],
                ) !== ''
                    ? $this->normalizeCurrencyCode(
                        (string) $line[
                            'currency_code'
                        ],
                        "lines.{$index}.currency_code",
                    )
                    : $currencyCode;

            if (
                $lineCurrencyCode
                !== $currencyCode
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.currency_code" => [
                        'Every journal line must use the journal currency.',
                    ],
                ]);
            }

            $lineExchangeRate = isset(
                $line['exchange_rate'],
            )
                && trim(
                    (string) $line['exchange_rate'],
                ) !== ''
                    ? $this->positiveDecimal(
                        value: (string) $line[
                            'exchange_rate'
                        ],
                        scale: self::RATE_SCALE,
                        field:
                            "lines.{$index}.exchange_rate",
                        label: 'exchange rate',
                    )
                    : $exchangeRateDecimal;

            if (
                !$lineExchangeRate->isEqualTo(
                    $exchangeRateDecimal,
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.exchange_rate" => [
                        'Every journal line must use the journal exchange rate.',
                    ],
                ]);
            }

            $debit = $this->nonNegativeDecimal(
                value:
                    $line['debit_amount'] ?? '0',
                field:
                    "lines.{$index}.debit_amount",
                label: 'debit amount',
            );

            $credit = $this->nonNegativeDecimal(
                value:
                    $line['credit_amount'] ?? '0',
                field:
                    "lines.{$index}.credit_amount",
                label: 'credit amount',
            );

            if (
                !$debit->isZero()
                && !$credit->isZero()
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.debit_amount" => [
                        'A journal line cannot contain both a transaction-currency debit and credit.',
                    ],
                    "lines.{$index}.credit_amount" => [
                        'A journal line cannot contain both a transaction-currency debit and credit.',
                    ],
                ]);
            }

            $hasBaseDebit = array_key_exists(
                'base_debit_amount',
                $line,
            );

            $hasBaseCredit = array_key_exists(
                'base_credit_amount',
                $line,
            );

            if (
                $hasBaseDebit
                !== $hasBaseCredit
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.base_debit_amount" => [
                        'System base-currency overrides must provide both base debit and base credit amounts.',
                    ],
                    "lines.{$index}.base_credit_amount" => [
                        'System base-currency overrides must provide both base debit and base credit amounts.',
                    ],
                ]);
            }

            $hasExplicitBaseAmounts =
                $hasBaseDebit
                && $hasBaseCredit;

            if (
                $manualPosting
                && $hasExplicitBaseAmounts
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.base_debit_amount" => [
                        'Manual journals cannot override calculated base-currency amounts.',
                    ],
                ]);
            }

            if ($hasExplicitBaseAmounts) {
                $baseDebit =
                    $this->nonNegativeDecimal(
                        value: $line[
                            'base_debit_amount'
                        ],
                        field:
                            "lines.{$index}.base_debit_amount",
                        label: 'base debit amount',
                    );

                $baseCredit =
                    $this->nonNegativeDecimal(
                        value: $line[
                            'base_credit_amount'
                        ],
                        field:
                            "lines.{$index}.base_credit_amount",
                        label: 'base credit amount',
                    );

                $this->validateExplicitBaseAmounts(
                    account: $account,
                    debit: $debit,
                    credit: $credit,
                    baseDebit: $baseDebit,
                    baseCredit: $baseCredit,
                    currencyCode: $currencyCode,
                    baseCurrencyCode:
                        mb_strtoupper(
                            (string) $tenant
                                ->currency_code,
                        ),
                    index: $index,
                );
            } else {
                if (
                    $debit->isZero()
                    && $credit->isZero()
                ) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.debit_amount" => [
                            'Each journal line must contain a transaction-currency debit or credit unless it is a system base-only exchange-difference line.',
                        ],
                        "lines.{$index}.credit_amount" => [
                            'Each journal line must contain a transaction-currency debit or credit unless it is a system base-only exchange-difference line.',
                        ],
                    ]);
                }

                $baseDebit = $debit
                    ->multipliedBy(
                        $lineExchangeRate,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HALF_UP,
                    );

                $baseCredit = $credit
                    ->multipliedBy(
                        $lineExchangeRate,
                    )
                    ->toScale(
                        self::MONEY_SCALE,
                        RoundingMode::HALF_UP,
                    );
            }

            $description = trim(
                (string) (
                    $line['description'] ?? ''
                ),
            );

            if (
                $description === ''
                || mb_strlen($description) > 500
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.description" => [
                        'A line description is required and may not exceed 500 characters.',
                    ],
                ]);
            }

            $reference = $this->nullableString(
                value:
                    $line['reference'] ?? null,
                maxLength: 160,
                field:
                    "lines.{$index}.reference",
                label: 'reference',
            );

            $dueDate = $this->nullableDate(
                value:
                    $line['due_date'] ?? null,
                field:
                    "lines.{$index}.due_date",
            );

            $normalizedLines[] = [
                'line_number' => $lineNumber,
                'account_id' => $accountId,
                'branch_id' => $lineBranchId,
                'supplier_id' => $supplierId,
                'customer_id' => $customerId,
                'reference' => $reference,
                'description' => $description,
                'due_date' => $dueDate,
                'currency_code' => $currencyCode,
                'exchange_rate' =>
                    (string) $lineExchangeRate,
                'debit_amount' =>
                    (string) $debit,
                'credit_amount' =>
                    (string) $credit,
                'base_debit_amount' =>
                    (string) $baseDebit,
                'base_credit_amount' =>
                    (string) $baseCredit,
            ];

            $totalDebit =
                $totalDebit->plus($debit);

            $totalCredit =
                $totalCredit->plus($credit);

            $baseTotalDebit =
                $baseTotalDebit->plus(
                    $baseDebit,
                );

            $baseTotalCredit =
                $baseTotalCredit->plus(
                    $baseCredit,
                );
        }

        if (
            $totalDebit->isZero()
            || $totalCredit->isZero()
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A journal entry must contain at least one debit and one credit line.',
                ],
            ]);
        }

        if (
            !$totalDebit->isEqualTo(
                $totalCredit,
            )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    sprintf(
                        'The journal is not balanced in transaction currency. Debits are %s and credits are %s.',
                        (string) $totalDebit,
                        (string) $totalCredit,
                    ),
                ],
            ]);
        }

        if (
            !$baseTotalDebit->isEqualTo(
                $baseTotalCredit,
            )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    sprintf(
                        'The journal is not balanced in base currency. Debits are %s and credits are %s.',
                        (string) $baseTotalDebit,
                        (string) $baseTotalCredit,
                    ),
                ],
            ]);
        }

        return [
            'currency_code' =>
                $currencyCode,

            'exchange_rate' =>
                (string) $exchangeRateDecimal,

            'lines' =>
                $normalizedLines,

            'total_debit' =>
                (string) $totalDebit,

            'total_credit' =>
                (string) $totalCredit,

            'base_total_debit' =>
                (string) $baseTotalDebit,

            'base_total_credit' =>
                (string) $baseTotalCredit,
        ];
    }

    private function validateExplicitBaseAmounts(
        Account $account,
        BigDecimal $debit,
        BigDecimal $credit,
        BigDecimal $baseDebit,
        BigDecimal $baseCredit,
        string $currencyCode,
        string $baseCurrencyCode,
        int $index,
    ): void {
        if (
            !$baseDebit->isZero()
            && !$baseCredit->isZero()
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.base_debit_amount" => [
                    'A journal line cannot contain both a base-currency debit and credit.',
                ],
                "lines.{$index}.base_credit_amount" => [
                    'A journal line cannot contain both a base-currency debit and credit.',
                ],
            ]);
        }

        $hasTransactionDebit =
            !$debit->isZero();

        $hasTransactionCredit =
            !$credit->isZero();

        $hasBaseDebit =
            !$baseDebit->isZero();

        $hasBaseCredit =
            !$baseCredit->isZero();

        if (
            !$hasTransactionDebit
            && !$hasTransactionCredit
            && !$hasBaseDebit
            && !$hasBaseCredit
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.base_debit_amount" => [
                    'A system journal line must contain a transaction-currency or base-currency amount.',
                ],
            ]);
        }

        if (
            $hasTransactionDebit
            && (
                !$hasBaseDebit
                || $hasBaseCredit
            )
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.base_debit_amount" => [
                    'A transaction-currency debit requires a positive base-currency debit on the same line.',
                ],
            ]);
        }

        if (
            $hasTransactionCredit
            && (
                !$hasBaseCredit
                || $hasBaseDebit
            )
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.base_credit_amount" => [
                    'A transaction-currency credit requires a positive base-currency credit on the same line.',
                ],
            ]);
        }

        if (
            !$hasTransactionDebit
            && !$hasTransactionCredit
        ) {
            if (
                !in_array(
                    $account->system_key,
                    [
                        'realized_exchange_gain',
                        'realized_exchange_loss',
                        'rounding_difference',
                    ],
                    true,
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => [
                        'Base-only lines are restricted to configured exchange-difference or rounding system accounts.',
                    ],
                ]);
            }

            if (
                $account->system_key
                    === 'realized_exchange_gain'
                && !$hasBaseCredit
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.base_credit_amount" => [
                        'A realized exchange gain must be posted as a base-currency credit.',
                    ],
                ]);
            }

            if (
                $account->system_key
                    === 'realized_exchange_loss'
                && !$hasBaseDebit
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.base_debit_amount" => [
                        'A realized exchange loss must be posted as a base-currency debit.',
                    ],
                ]);
            }
        }

        if (
            !$hasTransactionDebit
            && !$hasTransactionCredit
            && $currencyCode
                === $baseCurrencyCode
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.base_debit_amount" => [
                    'A base-only journal line is not valid when the journal currency is already the tenant base currency.',
                ],
            ]);
        }

        if (
            $currencyCode === $baseCurrencyCode
            && (
                !$baseDebit->isEqualTo(
                    $debit,
                )
                || !$baseCredit->isEqualTo(
                    $credit,
                )
            )
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.base_debit_amount" => [
                    'Base-currency journal lines must have identical transaction and base amounts.',
                ],
                "lines.{$index}.base_credit_amount" => [
                    'Base-currency journal lines must have identical transaction and base amounts.',
                ],
            ]);
        }
    }

    public function normalizeCurrencyCode(
        string $currencyCode,
        string $field = 'currency_code',
    ): string {
        $currencyCode = mb_strtoupper(
            trim($currencyCode),
        );

        if (
            preg_match(
                '/^[A-Z]{3}$/',
                $currencyCode,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The currency code must contain exactly three letters.',
                ],
            ]);
        }

        return $currencyCode;
    }

    public function normalizeExchangeRate(
        string $exchangeRate,
    ): string {
        return (string) $this->positiveDecimal(
            value: $exchangeRate,
            scale: self::RATE_SCALE,
            field: 'exchange_rate',
            label: 'exchange rate',
        );
    }

    private function zeroMoney(): BigDecimal
    {
        return BigDecimal::zero()->toScale(
            self::MONEY_SCALE,
        );
    }

    private function nonNegativeDecimal(
        mixed $value,
        string $field,
        string $label,
    ): BigDecimal {
        $decimal = $this->decimal(
            value: $value,
            scale: self::MONEY_SCALE,
            field: $field,
            label: $label,
        );

        if ($decimal->isNegative()) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} cannot be negative.",
                ],
            ]);
        }

        return $decimal;
    }

    private function positiveDecimal(
        mixed $value,
        int $scale,
        string $field,
        string $label,
    ): BigDecimal {
        $decimal = $this->decimal(
            value: $value,
            scale: $scale,
            field: $field,
            label: $label,
        );

        if (!$decimal->isPositive()) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be greater than zero.",
                ],
            ]);
        }

        return $decimal;
    }

    private function decimal(
        mixed $value,
        int $scale,
        string $field,
        string $label,
    ): BigDecimal {
        try {
            return BigDecimal::of(
                (string) $value,
            )->toScale(
                $scale,
                RoundingMode::HALF_UP,
            );
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a valid number.",
                ],
            ]);
        }
    }

    private function positiveInteger(
        mixed $value,
        string $field,
        string $label,
    ): int {
        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        if ($integer === false) {
            throw ValidationException::withMessages([
                $field => [
                    "The selected {$label} is invalid.",
                ],
            ]);
        }

        return (int) $integer;
    }

    private function nullablePositiveInteger(
        mixed $value,
        string $field,
        string $label,
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->positiveInteger(
            value: $value,
            field: $field,
            label: $label,
        );
    }

    private function nullableString(
        mixed $value,
        int $maxLength,
        string $field,
        string $label,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value,
        );

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
            > $maxLength
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} may not exceed {$maxLength} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function nullableDate(
        mixed $value,
        string $field,
    ): ?string {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        try {
            return $value
                instanceof DateTimeInterface
                    ? CarbonImmutable::instance(
                        $value,
                    )->toDateString()
                    : CarbonImmutable::parse(
                        (string) $value,
                    )->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => [
                    'The due date must be a valid date.',
                ],
            ]);
        }
    }
}