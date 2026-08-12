<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntryLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TreasuryAccountService
{
    private const SCALE = 6;

    public function lockCashOrBankAccount(int $accountId, string $field): Account
    {
        $account = Account::query()
            ->whereKey($accountId)
            ->lockForUpdate()
            ->first();

        if (
            !$account instanceof Account
            || !$account->isActive()
            || !$account->isPostingAccount()
            || !in_array($account->control_type, ['cash', 'bank'], true)
        ) {
            throw ValidationException::withMessages([
                $field => ['Select an active cash or bank posting account.'],
            ]);
        }

        return $account;
    }

    public function lockBankAccount(int $accountId, string $field = 'bank_account_id'): Account
    {
        $account = $this->lockCashOrBankAccount($accountId, $field);

        if ($account->control_type !== 'bank') {
            throw ValidationException::withMessages([
                $field => ['Select an active bank account.'],
            ]);
        }

        return $account;
    }

    public function lockOffsetAccount(int $accountId): Account
    {
        $account = Account::query()
            ->whereKey($accountId)
            ->lockForUpdate()
            ->first();

        if (
            !$account instanceof Account
            || !$account->isActive()
            || !$account->isPostingAccount()
            || $account->isControlAccount()
        ) {
            throw ValidationException::withMessages([
                'offset_account_id' => [
                    'Select an active non-control posting account.',
                ],
            ]);
        }

        return $account;
    }

    /** @return Collection<int, Account> */
    public function accounts(?string $controlType = null): Collection
    {
        return Account::query()
            ->where('status', 'active')
            ->where('is_group', false)
            ->whereIn('control_type', $controlType === null ? ['cash', 'bank'] : [$controlType])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'control_type']);
    }

    /** @return Collection<int, Account> */
    public function offsetAccounts(): Collection
    {
        return Account::query()
            ->where('status', 'active')
            ->where('is_group', false)
            ->whereNull('control_type')
            ->where(
                static fn (Builder $query): Builder => $query
                    ->where('allow_manual_posting', true)
                    ->orWhereIn('system_key', [
                        'bank_charges',
                        'bank_interest_income',
                    ]),
            )
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'name',
                'account_type',
                'account_subtype',
                'system_key',
            ]);
    }

    public function baseBalance(int $accountId, ?string $throughDate = null, ?int $branchId = null): string
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $accountId)
            ->where('journal_entries.status', 'posted');

        if ($throughDate !== null) {
            $query->whereDate('journal_entries.posting_date', '<=', $throughDate);
        }

        if ($branchId !== null) {
            $query->where('journal_entry_lines.branch_id', $branchId);
        }

        $debit = (string) (clone $query)->sum('journal_entry_lines.base_debit_amount');
        $credit = (string) (clone $query)->sum('journal_entry_lines.base_credit_amount');

        return BigDecimal::of($debit)
            ->minus(BigDecimal::of($credit))
            ->toScale(self::SCALE, RoundingMode::HalfUp)
            ->__toString();
    }

    public function postedBankLineQuery(int $accountId, int $branchId): Builder
    {
        return JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $accountId)
            ->where('journal_entry_lines.branch_id', $branchId)
            ->where('journal_entries.status', 'posted');
    }
}
