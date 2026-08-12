<?php

declare(strict_types=1);

namespace App\Services\Management;

use App\Models\Account;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Models\ManagementBudget;
use App\Models\ManagementBudgetLine;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ManagementBudgetService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): ManagementBudget
    {
        return DB::transaction(function () use ($data, $actor): ManagementBudget {
            $branch = $this->branch((int) $data['branch_id'], $actor, true);
            $fiscalYear = $this->fiscalYear((int) $data['fiscal_year_id']);
            $this->assertUniqueBudget((int) $branch->getKey(), (int) $fiscalYear->getKey(), null);
            $lines = $this->normalizeLines((array) $data['lines']);

            $budget = ManagementBudget::query()->create([
                'branch_id' => $branch->getKey(),
                'fiscal_year_id' => $fiscalYear->getKey(),
                'name' => trim((string) $data['name']),
                'currency_code' => strtoupper((string) $this->tenantContext->tenant()->currency_code),
                'status' => 'draft',
                'notes' => $this->nullableString($data['notes'] ?? null),
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            $this->replaceLines($budget, $lines);

            return $this->load($budget->refresh());
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(ManagementBudget $budget, array $data, User $actor): ManagementBudget
    {
        return DB::transaction(function () use ($budget, $data, $actor): ManagementBudget {
            $locked = ManagementBudget::query()->whereKey($budget->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureBudgetTenant($locked);

            if (!$locked->isDraft()) {
                throw ValidationException::withMessages(['status' => ['Only a draft management budget can be edited.']]);
            }

            $branch = $this->branch((int) $data['branch_id'], $actor, true);
            $fiscalYear = $this->fiscalYear((int) $data['fiscal_year_id']);
            $this->assertUniqueBudget((int) $branch->getKey(), (int) $fiscalYear->getKey(), (int) $locked->getKey());
            $lines = $this->normalizeLines((array) $data['lines']);

            $locked->fill([
                'branch_id' => $branch->getKey(),
                'fiscal_year_id' => $fiscalYear->getKey(),
                'name' => trim((string) $data['name']),
                'currency_code' => strtoupper((string) $this->tenantContext->tenant()->currency_code),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $locked->save();
            $this->replaceLines($locked, $lines);

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function approve(ManagementBudget $budget, User $actor): ManagementBudget
    {
        return DB::transaction(function () use ($budget, $actor): ManagementBudget {
            $locked = ManagementBudget::query()->whereKey($budget->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureBudgetTenant($locked);
            $branch = $this->branch((int) $locked->branch_id, $actor, true);

            if (!$locked->isDraft()) {
                throw ValidationException::withMessages(['status' => ['Only a draft management budget can be approved.']]);
            }

            if (!ManagementBudgetLine::query()->where('management_budget_id', $locked->getKey())->exists()) {
                throw ValidationException::withMessages(['lines' => ['The budget must contain at least one line before approval.']]);
            }

            $locked->status = 'approved';
            $locked->approved_by_user_id = $actor->getKey();
            $locked->approved_at = now();
            $locked->updated_by_user_id = $actor->getKey();
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function reopen(ManagementBudget $budget, User $actor): ManagementBudget
    {
        return DB::transaction(function () use ($budget, $actor): ManagementBudget {
            $locked = ManagementBudget::query()->whereKey($budget->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureBudgetTenant($locked);
            $this->branch((int) $locked->branch_id, $actor, true);

            if (!$locked->isApproved()) {
                throw ValidationException::withMessages(['status' => ['Only an approved management budget can be reopened.']]);
            }

            $locked->status = 'draft';
            $locked->approved_by_user_id = null;
            $locked->approved_at = null;
            $locked->updated_by_user_id = $actor->getKey();
            $locked->save();

            return $this->load($locked->refresh());
        }, attempts: 5);
    }

    public function delete(ManagementBudget $budget, User $actor): void
    {
        DB::transaction(function () use ($budget, $actor): void {
            $locked = ManagementBudget::query()->whereKey($budget->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureBudgetTenant($locked);
            $this->branch((int) $locked->branch_id, $actor, false);

            if (!$locked->isDraft()) {
                throw ValidationException::withMessages(['status' => ['Only a draft management budget can be deleted.']]);
            }

            $locked->forceDelete();
        }, attempts: 5);
    }

    /** @return array<string, mixed> */
    public function formOptions(User $actor): array
    {
        $accounts = Account::query()
            ->where('status', 'active')
            ->where('is_group', false)
            ->whereIn('account_type', ['revenue', 'expense'])
            ->orderBy('account_type')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type']);

        return [
            'branches' => $this->branchAccessService->accessibleBranches($actor, true)
                ->map(static fn (Branch $branch): array => [
                    'id' => (int) $branch->getKey(),
                    'code' => $branch->code,
                    'name' => $branch->name,
                ])->values()->all(),
            'fiscal_years' => FiscalYear::query()->orderByDesc('start_date')->get()
                ->map(static fn (FiscalYear $year): array => [
                    'id' => (int) $year->getKey(),
                    'name' => $year->name,
                    'code' => $year->code,
                    'start_date' => $year->start_date?->format('Y-m-d'),
                    'end_date' => $year->end_date?->format('Y-m-d'),
                    'status' => $year->status,
                ])->values()->all(),
            'accounts' => $accounts->map(static fn (Account $account): array => [
                'id' => (int) $account->getKey(),
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
            ])->values()->all(),
            'currency_code' => strtoupper((string) $this->tenantContext->tenant()->currency_code),
        ];
    }

    public function load(ManagementBudget $budget): ManagementBudget
    {
        return $budget->load([
            'branch:id,code,name,status',
            'fiscalYear:id,name,code,start_date,end_date,status',
            'createdBy:id,name,email',
            'approvedBy:id,name,email',
            'lines.account:id,code,name,account_type',
        ]);
    }

    /** @param list<array<string, mixed>> $lines @return list<array<string, mixed>> */
    private function normalizeLines(array $lines): array
    {
        $seen = [];
        $normalized = [];
        $accountIds = [];

        foreach ($lines as $index => $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $month = (int) ($line['month_number'] ?? 0);
            $amount = BigDecimal::of((string) ($line['amount'] ?? '0'))->toScale(self::SCALE, RoundingMode::HalfUp);
            $key = $accountId.':'.$month;

            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["lines.{$index}" => ['An account may appear only once in each budget month.']]);
            }

            $seen[$key] = true;
            $accountIds[$accountId] = true;
            $normalized[] = [
                'account_id' => $accountId,
                'month_number' => $month,
                'amount' => $amount->__toString(),
                'notes' => $this->nullableString($line['notes'] ?? null),
            ];
        }

        /** @var Collection<int, Account> $accounts */
        $accounts = Account::query()->whereIn('id', array_keys($accountIds))->get()->keyBy('id');

        foreach ($normalized as $index => $line) {
            $account = $accounts->get($line['account_id']);

            if (!$account instanceof Account || !$account->isActive() || $account->isGroupAccount() || !in_array($account->account_type, ['revenue', 'expense'], true)) {
                throw ValidationException::withMessages(["lines.{$index}.account_id" => ['Budget lines require active revenue or expense posting accounts.']]);
            }
        }

        return $normalized;
    }

    /** @param list<array<string, mixed>> $lines */
    private function replaceLines(ManagementBudget $budget, array $lines): void
    {
        ManagementBudgetLine::query()->where('management_budget_id', $budget->getKey())->delete();

        foreach ($lines as $line) {
            $budget->lines()->create($line);
        }
    }

    private function branch(int $branchId, User $actor, bool $active): Branch
    {
        $branch = $this->branchAccessService->findAccessibleBranch($actor, $branchId, $active);

        if (!$branch instanceof Branch) {
            throw ValidationException::withMessages(['branch_id' => ['The selected branch is unavailable or outside your access.']]);
        }

        return $branch;
    }

    private function fiscalYear(int $id): FiscalYear
    {
        $year = FiscalYear::query()->whereKey($id)->first();

        if (!$year instanceof FiscalYear) {
            throw ValidationException::withMessages(['fiscal_year_id' => ['The selected fiscal year is unavailable.']]);
        }

        return $year;
    }

    private function assertUniqueBudget(int $branchId, int $fiscalYearId, ?int $ignoreId): void
    {
        $exists = ManagementBudget::query()
            ->where('branch_id', $branchId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->when(
                $ignoreId !== null,
                static fn ($query) => $query->where('id', '!=', $ignoreId),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['fiscal_year_id' => ['A management budget already exists for this branch and fiscal year.']]);
        }
    }

    private function ensureBudgetTenant(ManagementBudget $budget): void
    {
        if ((int) $budget->tenant_id !== (int) $this->tenantContext->id()) {
            throw new LogicException('The management budget belongs to another tenant.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}