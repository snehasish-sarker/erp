<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BankStatementImportService
{
    private const SCALE = 6;
    private const MAX_LINES = 10000;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly TreasuryAccountService $accountService,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function import(array $data, UploadedFile $file, User $actor): BankStatementImport
    {
        $branchId = $this->positiveInteger($data['branch_id'] ?? null, 'branch_id');
        $accountId = $this->positiveInteger($data['bank_account_id'] ?? null, 'bank_account_id');
        $branch = Branch::query()->whereKey($branchId)->first();

        if (!$branch instanceof Branch) {
            throw ValidationException::withMessages(['branch_id' => ['The selected branch is unavailable.']]);
        }

        $this->branchAccessService->authorizeBranch($actor, $branch, true);
        $periodStart = $this->date($data['period_start'] ?? null, 'period_start');
        $periodEnd = $this->date($data['period_end'] ?? null, 'period_end');

        if ($periodEnd < $periodStart) {
            throw ValidationException::withMessages(['period_end' => ['The period end date must be on or after the start date.']]);
        }

        $currency = strtoupper(trim((string) ($data['currency_code'] ?? '')));
        $tenantCurrency = strtoupper((string) $this->tenantContext->tenant()->currency_code);

        if ($currency !== $tenantCurrency) {
            throw ValidationException::withMessages([
                'currency_code' => [
                    'Bank reconciliation currently requires the tenant base currency because treasury accounts do not yet carry separate account currencies.',
                ],
            ]);
        }

        $opening = $this->decimal($data['opening_balance'] ?? null, 'opening_balance', true);
        $closing = $this->decimal($data['closing_balance'] ?? null, 'closing_balance', true);
        $path = $file->getRealPath();

        if (!is_string($path) || !is_file($path)) {
            throw ValidationException::withMessages(['statement_file' => ['The uploaded statement file could not be read.']]);
        }

        $hash = hash_file('sha256', $path);

        if (!is_string($hash)) {
            throw new RuntimeException('Unable to hash the bank statement file.');
        }

        $rows = $this->parseCsv($path, $periodStart, $periodEnd);
        $movement = BigDecimal::zero();

        foreach ($rows as $row) {
            $movement = $movement->plus(BigDecimal::of($row['signed_amount']));
        }

        $calculatedClosing = BigDecimal::of($opening)
            ->plus($movement)
            ->toScale(self::SCALE, RoundingMode::HalfUp);

        if (!$calculatedClosing->isEqualTo(BigDecimal::of($closing))) {
            throw ValidationException::withMessages([
                'closing_balance' => [
                    sprintf(
                        'Opening balance plus imported movements equals %s, but the supplied closing balance is %s.',
                        $calculatedClosing->__toString(),
                        $closing,
                    ),
                ],
            ]);
        }

        return DB::transaction(function () use (
            $data,
            $file,
            $actor,
            $branchId,
            $accountId,
            $periodStart,
            $periodEnd,
            $currency,
            $opening,
            $closing,
            $hash,
            $rows,
        ): BankStatementImport {
            $this->accountService->lockBankAccount($accountId);

            $existing = BankStatementImport::query()
                ->where('bank_account_id', $accountId)
                ->where('source_sha256', $hash)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof BankStatementImport) {
                throw ValidationException::withMessages([
                    'statement_file' => ['This exact bank statement has already been imported for the selected account.'],
                ]);
            }

            $import = BankStatementImport::query()->create([
                'branch_id' => $branchId,
                'bank_account_id' => $accountId,
                'statement_reference' => $this->nullableText($data['statement_reference'] ?? null, 160),
                'source_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'source_sha256' => $hash,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'currency_code' => $currency,
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'line_count' => count($rows),
                'status' => 'imported',
                'imported_by_user_id' => $actor->getKey(),
                'imported_at' => now(),
            ]);

            foreach ($rows as $row) {
                $import->lines()->create([
                    ...$row,
                    'bank_account_id' => $accountId,
                ]);
            }

            return $import->load([
                'branch:id,name,code',
                'bankAccount:id,code,name,control_type',
                'importedBy:id,name',
                'lines',
            ]);
        }, attempts: 5);
    }

    public function delete(BankStatementImport $statement, User $actor): void
    {
        DB::transaction(function () use ($statement, $actor): void {
            $locked = BankStatementImport::query()
                ->whereKey($statement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $branch = Branch::query()->whereKey($locked->branch_id)->firstOrFail();
            $this->branchAccessService->authorizeBranch($actor, $branch, false);

            if ($locked->status !== 'imported' || $locked->reconciliations()->exists()) {
                throw ValidationException::withMessages([
                    'statement' => ['A statement with an active or completed reconciliation cannot be deleted.'],
                ]);
            }

            $locked->forceDelete();
        }, attempts: 5);
    }

    /**
     * Expected header:
     * transaction_date,value_date,reference,description,debit,credit,balance
     *
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $path, string $periodStart, string $periodEnd): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['statement_file' => ['Unable to open the uploaded CSV file.']]);
        }

        try {
            $header = fgetcsv($handle);

            if (!is_array($header)) {
                throw ValidationException::withMessages(['statement_file' => ['The CSV file does not contain a header row.']]);
            }

            $header = array_map(
                static fn (mixed $value): string => strtolower(trim((string) $value)),
                $header,
            );
            $required = ['transaction_date', 'description', 'debit', 'credit'];

            foreach ($required as $column) {
                if (!in_array($column, $header, true)) {
                    throw ValidationException::withMessages([
                        'statement_file' => [
                            'The CSV header must include transaction_date, description, debit, and credit. Optional columns are value_date, reference, and balance.',
                        ],
                    ]);
                }
            }

            $rows = [];
            $physicalLine = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $physicalLine++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) !== count($header)) {
                    throw ValidationException::withMessages([
                        'statement_file' => ["CSV row {$physicalLine} has a different number of columns than the header."],
                    ]);
                }

                $record = array_combine($header, $values);

                if (!is_array($record)) {
                    throw ValidationException::withMessages(['statement_file' => ["CSV row {$physicalLine} could not be read."]]);
                }

                $transactionDate = $this->csvDate($record['transaction_date'] ?? null, $physicalLine, 'transaction_date');

                if ($transactionDate < $periodStart || $transactionDate > $periodEnd) {
                    throw ValidationException::withMessages([
                        'statement_file' => ["CSV row {$physicalLine} falls outside the supplied statement period."],
                    ]);
                }

                $valueDate = trim((string) ($record['value_date'] ?? ''));
                $valueDate = $valueDate === '' ? null : $this->csvDate($valueDate, $physicalLine, 'value_date');
                $debit = $this->csvDecimal($record['debit'] ?? '0', $physicalLine, 'debit');
                $credit = $this->csvDecimal($record['credit'] ?? '0', $physicalLine, 'credit');

                if (($debit->isZero() && $credit->isZero()) || (!$debit->isZero() && !$credit->isZero())) {
                    throw ValidationException::withMessages([
                        'statement_file' => ["CSV row {$physicalLine} must contain a positive amount in exactly one of debit or credit."],
                    ]);
                }

                $description = trim((string) ($record['description'] ?? ''));

                if ($description === '') {
                    throw ValidationException::withMessages(['statement_file' => ["CSV row {$physicalLine} requires a description."]]);
                }

                $signed = $credit->minus($debit)->toScale(self::SCALE, RoundingMode::HalfUp);
                $running = trim((string) ($record['balance'] ?? ''));
                $runningBalance = $running === '' ? null : $this->csvDecimal($running, $physicalLine, 'balance', true)->__toString();
                $lineNumber = count($rows) + 1;
                $reference = $this->nullableText($record['reference'] ?? null, 190);
                $fingerprint = hash('sha256', implode('|', [
                    $lineNumber,
                    $transactionDate,
                    $valueDate ?? '',
                    $reference ?? '',
                    $description,
                    $debit->__toString(),
                    $credit->__toString(),
                    $runningBalance ?? '',
                ]));

                $rows[] = [
                    'line_number' => $lineNumber,
                    'transaction_date' => $transactionDate,
                    'value_date' => $valueDate,
                    'bank_reference' => $reference,
                    'description' => mb_substr($description, 0, 500),
                    'debit_amount' => $debit->__toString(),
                    'credit_amount' => $credit->__toString(),
                    'signed_amount' => $signed->__toString(),
                    'running_balance' => $runningBalance,
                    'matched_amount' => '0.000000',
                    'fingerprint' => $fingerprint,
                    'status' => 'unmatched',
                ];

                if (count($rows) > self::MAX_LINES) {
                    throw ValidationException::withMessages([
                        'statement_file' => ['A statement may not contain more than 10,000 transaction rows.'],
                    ]);
                }
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['statement_file' => ['The CSV file does not contain any transaction rows.']]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function csvDate(mixed $value, int $line, string $column): string
    {
        $value = trim((string) $value);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $this->tenantContext->tenant()->timezone);

        if (!$date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([
                'statement_file' => ["CSV row {$line} column {$column} must use YYYY-MM-DD."],
            ]);
        }

        return $value;
    }

    private function csvDecimal(mixed $value, int $line, string $column, bool $allowNegative = false): BigDecimal
    {
        try {
            $decimal = BigDecimal::of(trim((string) $value))->toScale(self::SCALE, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'statement_file' => ["CSV row {$line} column {$column} must be a number with no more than six decimals."],
            ]);
        }

        if (!$allowNegative && $decimal->isNegative()) {
            throw ValidationException::withMessages([
                'statement_file' => ["CSV row {$line} column {$column} cannot be negative."],
            ]);
        }

        return $decimal;
    }

    private function decimal(mixed $value, string $field, bool $allowNegative): string
    {
        try {
            $decimal = BigDecimal::of(trim((string) $value))->toScale(self::SCALE, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => ['Enter a number with no more than six decimal places.']]);
        }

        if (!$allowNegative && $decimal->isNegative()) {
            throw ValidationException::withMessages([$field => ['The value cannot be negative.']]);
        }

        return $decimal->__toString();
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            throw ValidationException::withMessages([$field => ['Select a valid value.']]);
        }

        return $integer;
    }

    private function date(mixed $value, string $field): string
    {
        return $this->csvDate($value, 0, $field);
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages(['statement_reference' => ["Text may not exceed {$max} characters."]]);
        }

        return $text;
    }
}
