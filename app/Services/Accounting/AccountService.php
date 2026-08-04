<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Support\Accounting\GeneralLedgerRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AccountService
{
    private const MAX_LEVEL = 10;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly GeneralLedgerRegistry $registry,
    ) {
    }

    /**
     * @param array{
     *     parent_account_id?: int|string|null,
     *     code: string,
     *     name: string,
     *     account_type: string,
     *     account_subtype?: string|null,
     *     control_type?: string|null,
     *     system_key?: string|null,
     *     is_group?: bool|int|string,
     *     allow_manual_posting?: bool|int|string,
     *     status?: string,
     *     description?: string|null
     * } $data
     */
    public function create(
        array $data,
        User $actor,
    ): Account {
        $this->ensureActorBelongsToTenant($actor);

        return DB::transaction(
            function () use (
                $data,
                $actor,
            ): Account {
                $normalized = $this->normalizeData(
                    data: $data,
                    currentAccount: null,
                );

                $parent = $this->resolveParent(
                    parentAccountId:
                        $normalized['parent_account_id'],
                    accountType:
                        $normalized['account_type'],
                    currentAccount: null,
                );

                $normalized['level'] = $parent === null
                    ? 1
                    : (int) $parent->level + 1;

                $this->ensureLevelIsSupported(
                    $normalized['level'],
                );

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                    exceptAccountId: null,
                );

                $this->ensureSystemKeyIsAvailable(
                    systemKey: $normalized['system_key'],
                    exceptAccountId: null,
                );

                $normalized['created_by_user_id'] =
                    $actor->getKey();

                $normalized['updated_by_user_id'] =
                    $actor->getKey();

                return Account::query()->create(
                    $normalized,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array{
     *     parent_account_id?: int|string|null,
     *     code: string,
     *     name: string,
     *     account_type: string,
     *     account_subtype?: string|null,
     *     control_type?: string|null,
     *     system_key?: string|null,
     *     is_group?: bool|int|string,
     *     allow_manual_posting?: bool|int|string,
     *     status?: string,
     *     description?: string|null
     * } $data
     */
    public function update(
        Account $account,
        array $data,
        User $actor,
    ): Account {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureAccountBelongsToTenant($account);

        return DB::transaction(
            function () use (
                $account,
                $data,
                $actor,
            ): Account {
                $lockedAccount = Account::query()
                    ->whereKey($account->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $normalized = $this->normalizeData(
                    data: $data,
                    currentAccount: $lockedAccount,
                );

                $parent = $this->resolveParent(
                    parentAccountId:
                        $normalized['parent_account_id'],
                    accountType:
                        $normalized['account_type'],
                    currentAccount: $lockedAccount,
                );

                $normalized['level'] = $parent === null
                    ? 1
                    : (int) $parent->level + 1;

                $this->ensureLevelIsSupported(
                    $normalized['level'],
                );

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                    exceptAccountId:
                        (int) $lockedAccount->getKey(),
                );

                $this->ensureSystemKeyIsAvailable(
                    systemKey: $normalized['system_key'],
                    exceptAccountId:
                        (int) $lockedAccount->getKey(),
                );

                $this->ensureUsedAccountStructureIsStable(
                    account: $lockedAccount,
                    normalized: $normalized,
                );

                $this->ensureAccountWithChildrenKeepsStructure(
                    account: $lockedAccount,
                    normalized: $normalized,
                );

                $normalized['updated_by_user_id'] =
                    $actor->getKey();

                $lockedAccount->fill($normalized);
                $lockedAccount->save();

                return $lockedAccount->refresh()->load([
                    'parent',
                    'children',
                    'createdBy',
                    'updatedBy',
                ]);
            },
            attempts: 5,
        );
    }

    public function activate(
        Account $account,
        User $actor,
    ): Account {
        return $this->changeStatus(
            account: $account,
            status: 'active',
            actor: $actor,
        );
    }

    public function deactivate(
        Account $account,
        User $actor,
    ): Account {
        return $this->changeStatus(
            account: $account,
            status: 'inactive',
            actor: $actor,
        );
    }

    public function delete(
        Account $account,
        User $actor,
    ): void {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureAccountBelongsToTenant($account);

        DB::transaction(
            function () use ($account): void {
                $lockedAccount = Account::query()
                    ->whereKey($account->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedAccount->isSystemAccount()) {
                    throw ValidationException::withMessages([
                        'account' => [
                            'A configured system account cannot be deleted.',
                        ],
                    ]);
                }

                if (
                    Account::query()
                        ->where(
                            'parent_account_id',
                            $lockedAccount->getKey(),
                        )
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'account' => [
                            'An account with child accounts cannot be deleted.',
                        ],
                    ]);
                }

                if (
                    JournalEntryLine::query()
                        ->where(
                            'account_id',
                            $lockedAccount->getKey(),
                        )
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'account' => [
                            'An account with journal activity cannot be deleted. Deactivate it instead.',
                        ],
                    ]);
                }

                $lockedAccount->delete();
            },
            attempts: 5,
        );
    }

    public function findSystemAccount(
        string $systemKey,
        bool $lockForUpdate = false,
    ): Account {
        $systemKey = mb_strtolower(trim($systemKey));

        if (!$this->registry->isSystemAccountKey($systemKey)) {
            throw new LogicException(
                "Unsupported system account key [{$systemKey}].",
            );
        }

        $query = Account::query()
            ->where('system_key', $systemKey);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $account = $query->first();

        if (!$account instanceof Account) {
            throw ValidationException::withMessages([
                'accounting' => [
                    "The required system account [{$systemKey}] is not configured.",
                ],
            ]);
        }

        if (
            !$account->isActive()
            || !$account->isPostingAccount()
        ) {
            throw ValidationException::withMessages([
                'accounting' => [
                    "The required system account [{$systemKey}] is not an active posting account.",
                ],
            ]);
        }

        return $account;
    }

    private function changeStatus(
        Account $account,
        string $status,
        User $actor,
    ): Account {
        $this->ensureActorBelongsToTenant($actor);
        $this->ensureAccountBelongsToTenant($account);

        return DB::transaction(
            function () use (
                $account,
                $status,
                $actor,
            ): Account {
                $lockedAccount = Account::query()
                    ->whereKey($account->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $status === 'inactive'
                    && $lockedAccount->isSystemAccount()
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'A configured system account cannot be deactivated while it remains assigned to a system key.',
                        ],
                    ]);
                }

                $lockedAccount->status = $status;
                $lockedAccount->updated_by_user_id =
                    $actor->getKey();

                $lockedAccount->save();

                return $lockedAccount->refresh();
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *     parent_account_id: int|null,
     *     code: string,
     *     name: string,
     *     account_type: string,
     *     account_subtype: string|null,
     *     normal_balance: string,
     *     control_type: string|null,
     *     system_key: string|null,
     *     is_group: bool,
     *     allow_manual_posting: bool,
     *     status: string,
     *     description: string|null
     * }
     */
    private function normalizeData(
        array $data,
        ?Account $currentAccount,
    ): array {
        $code = mb_strtoupper(trim(
            (string) ($data['code'] ?? ''),
        ));

        if (
            $code === ''
            || mb_strlen($code) > 50
            || preg_match(
                '/^[A-Z0-9][A-Z0-9._-]*$/',
                $code,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'code' => [
                    'The account code must contain only letters, numbers, dots, underscores, or hyphens and cannot exceed 50 characters.',
                ],
            ]);
        }

        $name = trim(
            (string) ($data['name'] ?? ''),
        );

        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages([
                'name' => [
                    'The account name is required and cannot exceed 160 characters.',
                ],
            ]);
        }

        $accountType = mb_strtolower(trim(
            (string) ($data['account_type'] ?? ''),
        ));

        if (!$this->registry->isAccountType($accountType)) {
            throw ValidationException::withMessages([
                'account_type' => [
                    'The selected account type is invalid.',
                ],
            ]);
        }

        $accountSubtype = $this->nullableLowercaseString(
            $data['account_subtype'] ?? null,
        );

        if (
            $accountSubtype !== null
            && !$this->registry->subtypeBelongsToType(
                accountSubtype: $accountSubtype,
                accountType: $accountType,
            )
        ) {
            throw ValidationException::withMessages([
                'account_subtype' => [
                    'The selected account subtype does not belong to the selected account type.',
                ],
            ]);
        }

        $controlType = $this->nullableLowercaseString(
            $data['control_type'] ?? null,
        );

        if (
            $controlType !== null
            && !$this->registry->isControlType($controlType)
        ) {
            throw ValidationException::withMessages([
                'control_type' => [
                    'The selected control-account type is invalid.',
                ],
            ]);
        }

        $systemKey = $this->nullableLowercaseString(
            $data['system_key'] ?? null,
        );

        if (
            $systemKey !== null
            && !$this->registry->isSystemAccountKey($systemKey)
        ) {
            throw ValidationException::withMessages([
                'system_key' => [
                    'The selected system-account key is invalid.',
                ],
            ]);
        }

        $isGroup = filter_var(
            $data['is_group'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        $allowManualPosting = filter_var(
            $data['allow_manual_posting'] ?? true,
            FILTER_VALIDATE_BOOL,
        );

        if ($systemKey !== null) {
            $definition = $this->registry
                ->systemAccountDefinition($systemKey);

            $accountType = $definition['account_type'];
            $accountSubtype = $definition['account_subtype'];
            $controlType = $definition['control_type'];
            $isGroup = false;
            $allowManualPosting = false;
        }

        $this->ensureControlTypeMatchesAccount(
            controlType: $controlType,
            accountType: $accountType,
            accountSubtype: $accountSubtype,
        );

        if ($isGroup) {
            if ($systemKey !== null || $controlType !== null) {
                throw ValidationException::withMessages([
                    'is_group' => [
                        'A group account cannot be assigned as a control or system account.',
                    ],
                ]);
            }

            $allowManualPosting = false;
        }

        if ($controlType !== null) {
            $allowManualPosting = false;
        }

        $status = mb_strtolower(trim(
            (string) ($data['status']
                ?? $currentAccount?->status
                ?? 'active'),
        ));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages([
                'status' => [
                    'The selected account status is invalid.',
                ],
            ]);
        }

        if ($systemKey !== null && $status !== 'active') {
            throw ValidationException::withMessages([
                'status' => [
                    'A configured system account must remain active.',
                ],
            ]);
        }

        $description = $this->nullableTrimmedString(
            $data['description'] ?? null,
        );

        if ($description !== null && mb_strlen($description) > 500) {
            throw ValidationException::withMessages([
                'description' => [
                    'The account description cannot exceed 500 characters.',
                ],
            ]);
        }

        return [
            'parent_account_id' => $this->nullablePositiveInt(
                $data['parent_account_id'] ?? null,
            ),
            'code' => $code,
            'name' => $name,
            'account_type' => $accountType,
            'account_subtype' => $accountSubtype,
            'normal_balance' => $this->registry->normalBalance(
                accountType: $accountType,
                accountSubtype: $accountSubtype,
            ),
            'control_type' => $controlType,
            'system_key' => $systemKey,
            'is_group' => $isGroup,
            'allow_manual_posting' => $allowManualPosting,
            'status' => $status,
            'description' => $description,
        ];
    }

    private function resolveParent(
        ?int $parentAccountId,
        string $accountType,
        ?Account $currentAccount,
    ): ?Account {
        if ($parentAccountId === null) {
            return null;
        }

        if (
            $currentAccount instanceof Account
            && $parentAccountId === (int) $currentAccount->getKey()
        ) {
            throw ValidationException::withMessages([
                'parent_account_id' => [
                    'An account cannot be its own parent.',
                ],
            ]);
        }

        $parent = Account::query()
            ->whereKey($parentAccountId)
            ->lockForUpdate()
            ->first();

        if (!$parent instanceof Account) {
            throw ValidationException::withMessages([
                'parent_account_id' => [
                    'The selected parent account is unavailable.',
                ],
            ]);
        }

        if (!$parent->isActive()) {
            throw ValidationException::withMessages([
                'parent_account_id' => [
                    'The selected parent account is inactive.',
                ],
            ]);
        }

        if (!$parent->isGroupAccount()) {
            throw ValidationException::withMessages([
                'parent_account_id' => [
                    'The selected parent must be a group account.',
                ],
            ]);
        }

        if ($parent->account_type !== $accountType) {
            throw ValidationException::withMessages([
                'parent_account_id' => [
                    'The parent and child accounts must use the same account type.',
                ],
            ]);
        }

        if (
            $currentAccount instanceof Account
            && $this->isDescendantOf(
                possibleDescendant: $parent,
                account: $currentAccount,
            )
        ) {
            throw ValidationException::withMessages([
                'parent_account_id' => [
                    'The selected parent would create an account hierarchy cycle.',
                ],
            ]);
        }

        return $parent;
    }

    private function isDescendantOf(
        Account $possibleDescendant,
        Account $account,
    ): bool {
        $current = $possibleDescendant;
        $visited = [];

        while ($current->parent_account_id !== null) {
            if (
                (int) $current->getKey()
                === (int) $account->getKey()
            ) {
                return true;
            }

            $currentId = (int) $current->getKey();

            if (isset($visited[$currentId])) {
                throw new LogicException(
                    'The account hierarchy already contains a cycle.',
                );
            }

            $visited[$currentId] = true;

            $parent = Account::query()
                ->whereKey($current->parent_account_id)
                ->first();

            if (!$parent instanceof Account) {
                return false;
            }

            $current = $parent;
        }

        return (int) $current->getKey()
            === (int) $account->getKey();
    }

    /**
     * @param array{
     *     parent_account_id: int|null,
     *     code: string,
     *     name: string,
     *     account_type: string,
     *     account_subtype: string|null,
     *     normal_balance: string,
     *     control_type: string|null,
     *     system_key: string|null,
     *     is_group: bool,
     *     allow_manual_posting: bool,
     *     status: string,
     *     description: string|null,
     *     level: int
     * } $normalized
     */
    private function ensureUsedAccountStructureIsStable(
        Account $account,
        array $normalized,
    ): void {
        $hasJournalActivity = JournalEntryLine::query()
            ->where('account_id', $account->getKey())
            ->exists();

        if (!$hasJournalActivity) {
            return;
        }

        $structuralFields = [
            'code',
            'parent_account_id',
            'account_type',
            'account_subtype',
            'normal_balance',
            'control_type',
            'system_key',
            'is_group',
        ];

        foreach ($structuralFields as $field) {
            if ($account->getAttribute($field) != $normalized[$field]) {
                throw ValidationException::withMessages([
                    $field => [
                        'This account has journal activity, so its structural accounting fields cannot be changed.',
                    ],
                ]);
            }
        }
    }

    /**
     * @param array{
     *     parent_account_id: int|null,
     *     code: string,
     *     name: string,
     *     account_type: string,
     *     account_subtype: string|null,
     *     normal_balance: string,
     *     control_type: string|null,
     *     system_key: string|null,
     *     is_group: bool,
     *     allow_manual_posting: bool,
     *     status: string,
     *     description: string|null,
     *     level: int
     * } $normalized
     */
    private function ensureAccountWithChildrenKeepsStructure(
        Account $account,
        array $normalized,
    ): void {
        $hasChildren = Account::query()
            ->where(
                'parent_account_id',
                $account->getKey(),
            )
            ->exists();

        if (!$hasChildren) {
            return;
        }

        if (
            $normalized['parent_account_id']
                !== $account->parent_account_id
            || $normalized['account_type']
                !== $account->account_type
            || $normalized['is_group'] !== true
        ) {
            throw ValidationException::withMessages([
                'account' => [
                    'An account with child accounts cannot change its parent, account type, or group-account status.',
                ],
            ]);
        }
    }

    private function ensureControlTypeMatchesAccount(
        ?string $controlType,
        string $accountType,
        ?string $accountSubtype,
    ): void {
        if ($controlType === null) {
            return;
        }

        $valid = match ($controlType) {
            'inventory', 'cash', 'bank' =>
                $accountType === 'asset',

            'accounts_receivable' =>
                $accountType === 'asset',

            'accounts_payable' =>
                $accountType === 'liability'
                || $accountSubtype === 'supplier_advances',

            'tax' => in_array(
                $accountType,
                [
                    'asset',
                    'liability',
                ],
                true,
            ),

            default => false,
        };

        if ($valid) {
            return;
        }

        throw ValidationException::withMessages([
            'control_type' => [
                'The selected control-account type is incompatible with the account classification.',
            ],
        ]);
    }

    private function ensureLevelIsSupported(int $level): void
    {
        if ($level >= 1 && $level <= self::MAX_LEVEL) {
            return;
        }

        throw ValidationException::withMessages([
            'parent_account_id' => [
                'The account hierarchy cannot exceed 10 levels.',
            ],
        ]);
    }

    private function ensureCodeIsAvailable(
        string $code,
        ?int $exceptAccountId,
    ): void {
        $exists = Account::query()
            ->where('code', $code)
            ->when(
                $exceptAccountId !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'id',
                    '!=',
                    $exceptAccountId,
                ),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => [
                    'The account code is already in use.',
                ],
            ]);
        }
    }

    private function ensureSystemKeyIsAvailable(
        ?string $systemKey,
        ?int $exceptAccountId,
    ): void {
        if ($systemKey === null) {
            return;
        }

        $exists = Account::query()
            ->where('system_key', $systemKey)
            ->when(
                $exceptAccountId !== null,
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'id',
                    '!=',
                    $exceptAccountId,
                ),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'system_key' => [
                    'The system-account key is already assigned to another account.',
                ],
            ]);
        }
    }

    private function ensureActorBelongsToTenant(User $actor): void
    {
        $tenantId = $this->tenantContext->id();

        if (
            $tenantId === null
            || (int) $actor->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The actor does not belong to the active tenant.',
            );
        }
    }

    private function ensureAccountBelongsToTenant(
        Account $account,
    ): void {
        $tenantId = $this->tenantContext->id();

        if (
            $tenantId === null
            || (int) $account->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'The account does not belong to the active tenant.',
            );
        }
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

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
                'parent_account_id' => [
                    'The selected parent account is invalid.',
                ],
            ]);
        }

        return (int) $integer;
    }

    private function nullableLowercaseString(mixed $value): ?string
    {
        $value = $this->nullableTrimmedString($value);

        return $value === null
            ? null
            : mb_strtolower($value);
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }
}