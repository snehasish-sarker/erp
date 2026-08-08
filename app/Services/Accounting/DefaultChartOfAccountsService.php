<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Accounting\DefaultChartOfAccounts;
use App\Support\Accounting\GeneralLedgerRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DefaultChartOfAccountsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly GeneralLedgerRegistry $registry,
        private readonly DefaultChartOfAccounts $defaultChart,
    ) {
    }

    public function provisionForTenant(Tenant $tenant): void
    {
        $previousTenant = $this->tenantContext->get();

        try {
            $this->tenantContext->set($tenant);

            DB::transaction(
                function () use ($tenant): void {
                    $definitions = $this->validatedDefinitions();
                    $actorId = $this->resolveActorId($tenant);

                    /**
                     * @var array<string, Account> $accountsByDefaultCode
                     */
                    $accountsByDefaultCode = [];

                    foreach ($definitions as $definition) {
                        $parent = null;

                        if ($definition['parent_code'] !== null) {
                            $parent = $accountsByDefaultCode[
                                $definition['parent_code']
                            ] ?? null;

                            if (!$parent instanceof Account) {
                                throw new LogicException(
                                    "The default parent account [{$definition['parent_code']}] must be provisioned before [{$definition['code']}].",
                                );
                            }
                        }

                        $account = $this->resolveOrCreateAccount(
                            tenant: $tenant,
                            definition: $definition,
                            parent: $parent,
                            actorId: $actorId,
                        );

                        $accountsByDefaultCode[$definition['code']] =
                            $account;
                    }

                    $this->ensureEveryRequiredSystemAccountExists();
                },
                attempts: 5,
            );
        } finally {
            if ($previousTenant instanceof Tenant) {
                $this->tenantContext->set($previousTenant);
            } else {
                $this->tenantContext->clear();
            }
        }
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     parent_code: string|null,
     *     account_type: string,
     *     account_subtype: string|null,
     *     system_key: string|null,
     *     is_group: bool,
     *     allow_manual_posting: bool,
     *     description: string
     * }>
     */
    private function validatedDefinitions(): array
    {
        $definitions = $this->defaultChart->definitions();
        $seenCodes = [];
        $seenSystemKeys = [];

        foreach ($definitions as $definition) {
            $code = $definition['code'];
            $accountType = $definition['account_type'];
            $accountSubtype = $definition['account_subtype'];
            $systemKey = $definition['system_key'];
            $parentCode = $definition['parent_code'];

            if (isset($seenCodes[$code])) {
                throw new LogicException(
                    "The default chart contains duplicate account code [{$code}].",
                );
            }

            if (!$this->registry->isAccountType($accountType)) {
                throw new LogicException(
                    "The default account [{$code}] uses unsupported account type [{$accountType}].",
                );
            }

            if (
                $accountSubtype !== null
                && !$this->registry->subtypeBelongsToType(
                    accountSubtype: $accountSubtype,
                    accountType: $accountType,
                )
            ) {
                throw new LogicException(
                    "The default account [{$code}] uses an incompatible account subtype [{$accountSubtype}].",
                );
            }

            if (
                $parentCode !== null
                && !isset($seenCodes[$parentCode])
            ) {
                throw new LogicException(
                    "The default parent account [{$parentCode}] must appear before child account [{$code}].",
                );
            }

            if ($systemKey !== null) {
                if (isset($seenSystemKeys[$systemKey])) {
                    throw new LogicException(
                        "The default chart assigns system key [{$systemKey}] more than once.",
                    );
                }

                $systemDefinition = $this->registry
                    ->systemAccountDefinition($systemKey);

                if (
                    $definition['is_group']
                    || $definition['allow_manual_posting']
                    || $systemDefinition['account_type']
                        !== $accountType
                    || $systemDefinition['account_subtype']
                        !== $accountSubtype
                ) {
                    throw new LogicException(
                        "The default account [{$code}] does not match system-account definition [{$systemKey}].",
                    );
                }

                $seenSystemKeys[$systemKey] = true;
            }

            if (
                $definition['is_group']
                && $definition['allow_manual_posting']
            ) {
                throw new LogicException(
                    "The default group account [{$code}] cannot allow manual posting.",
                );
            }

            $seenCodes[$code] = true;
        }

        $requiredKeys = $this->registry->systemAccountKeys();
        $definedKeys = array_keys($seenSystemKeys);

        sort($requiredKeys);
        sort($definedKeys);

        if ($requiredKeys !== $definedKeys) {
            $missingKeys = array_values(
                array_diff($requiredKeys, $definedKeys),
            );

            $unexpectedKeys = array_values(
                array_diff($definedKeys, $requiredKeys),
            );

            throw new LogicException(sprintf(
                'The default chart system-account coverage is invalid. Missing: [%s]. Unexpected: [%s].',
                implode(', ', $missingKeys),
                implode(', ', $unexpectedKeys),
            ));
        }

        return $definitions;
    }

    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     parent_code: string|null,
     *     account_type: string,
     *     account_subtype: string|null,
     *     system_key: string|null,
     *     is_group: bool,
     *     allow_manual_posting: bool,
     *     description: string
     * } $definition
     */
    private function resolveOrCreateAccount(
        Tenant $tenant,
        array $definition,
        ?Account $parent,
        ?int $actorId,
    ): Account {
        $accountByCode = Account::query()
            ->where('code', $definition['code'])
            ->lockForUpdate()
            ->first();

        $accountBySystemKey = null;

        if ($definition['system_key'] !== null) {
            $accountBySystemKey = Account::query()
                ->where(
                    'system_key',
                    $definition['system_key'],
                )
                ->lockForUpdate()
                ->first();
        }

        if (
            $accountByCode instanceof Account
            && $accountBySystemKey instanceof Account
            && (int) $accountByCode->getKey()
                !== (int) $accountBySystemKey->getKey()
        ) {
            throw new LogicException(
                "Default account code [{$definition['code']}] and system key [{$definition['system_key']}] are assigned to different accounts.",
            );
        }

        $account = $accountBySystemKey ?? $accountByCode;

        if (!$account instanceof Account) {
            return $this->createAccount(
                tenant: $tenant,
                definition: $definition,
                parent: $parent,
                actorId: $actorId,
            );
        }

        $this->ensureExistingAccountIsCompatible(
            account: $account,
            definition: $definition,
        );

        if (
            $definition['system_key'] !== null
            && $account->system_key === null
        ) {
            Account::withoutEvents(
                function () use (
                    $account,
                    $definition,
                    $actorId,
                ): void {
                    $systemDefinition = $this->registry
                        ->systemAccountDefinition(
                            $definition['system_key'],
                        );

                    $account->forceFill([
                        'system_key' => $definition['system_key'],
                        'control_type' => $systemDefinition[
                            'control_type'
                        ],
                        'allow_manual_posting' => false,
                        'status' => 'active',
                        'updated_by_user_id' => $actorId,
                    ])->save();
                },
            );
        }

        return $account->refresh();
    }

    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     parent_code: string|null,
     *     account_type: string,
     *     account_subtype: string|null,
     *     system_key: string|null,
     *     is_group: bool,
     *     allow_manual_posting: bool,
     *     description: string
     * } $definition
     */
    private function createAccount(
        Tenant $tenant,
        array $definition,
        ?Account $parent,
        ?int $actorId,
    ): Account {
        $controlType = null;

        if ($definition['system_key'] !== null) {
            $controlType = $this->registry
                ->systemAccountDefinition(
                    $definition['system_key'],
                )['control_type'];
        }

        $account = new Account();

        Account::withoutEvents(
            function () use (
                $account,
                $tenant,
                $definition,
                $parent,
                $actorId,
                $controlType,
            ): void {
                $account->forceFill([
                    'tenant_id' => $tenant->getKey(),
                    'parent_account_id' => $parent?->getKey(),
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'account_type' => $definition['account_type'],
                    'account_subtype' => $definition[
                        'account_subtype'
                    ],
                    'normal_balance' => $this->registry
                        ->normalBalance(
                            accountType: $definition[
                                'account_type'
                            ],
                            accountSubtype: $definition[
                                'account_subtype'
                            ],
                        ),
                    'control_type' => $controlType,
                    'system_key' => $definition['system_key'],
                    'level' => $parent instanceof Account
                        ? ((int) $parent->level) + 1
                        : 1,
                    'is_group' => $definition['is_group'],
                    'allow_manual_posting' => $definition[
                        'allow_manual_posting'
                    ],
                    'status' => 'active',
                    'description' => $definition['description'],
                    'created_by_user_id' => $actorId,
                    'updated_by_user_id' => $actorId,
                ])->save();
            },
        );

        return $account->refresh();
    }

    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     parent_code: string|null,
     *     account_type: string,
     *     account_subtype: string|null,
     *     system_key: string|null,
     *     is_group: bool,
     *     allow_manual_posting: bool,
     *     description: string
     * } $definition
     */
    private function ensureExistingAccountIsCompatible(
        Account $account,
        array $definition,
    ): void {
        if ($account->account_type !== $definition['account_type']) {
            throw new LogicException(
                "Existing account [{$account->code}] has account type [{$account->account_type}], but [{$definition['account_type']}] is required.",
            );
        }

        if (
            $account->account_subtype
            !== $definition['account_subtype']
        ) {
            throw new LogicException(
                "Existing account [{$account->code}] has an incompatible account subtype for default account [{$definition['code']}].",
            );
        }

        if ((bool) $account->is_group !== $definition['is_group']) {
            throw new LogicException(
                "Existing account [{$account->code}] has an incompatible group-account setting for default account [{$definition['code']}].",
            );
        }

        $expectedNormalBalance = $this->registry
            ->normalBalance(
                accountType: $definition['account_type'],
                accountSubtype: $definition['account_subtype'],
            );

        if ($account->normal_balance !== $expectedNormalBalance) {
            throw new LogicException(
                "Existing account [{$account->code}] has normal balance [{$account->normal_balance}], but [{$expectedNormalBalance}] is required.",
            );
        }

        $systemKey = $definition['system_key'];

        if ($systemKey === null) {
            if ($account->system_key !== null) {
                throw new LogicException(
                    "Existing account [{$account->code}] is assigned to system key [{$account->system_key}] and cannot be used as default account [{$definition['code']}].",
                );
            }

            return;
        }

        if (
            $account->system_key !== null
            && $account->system_key !== $systemKey
        ) {
            throw new LogicException(
                "Existing account [{$account->code}] is assigned to system key [{$account->system_key}], but [{$systemKey}] is required.",
            );
        }

        $systemDefinition = $this->registry
            ->systemAccountDefinition($systemKey);

        if (
            $account->control_type
            !== $systemDefinition['control_type']
        ) {
            throw new LogicException(
                "Existing system account [{$account->code}] has an incompatible control-account type.",
            );
        }

        if ((bool) $account->allow_manual_posting) {
            throw new LogicException(
                "Existing system account [{$account->code}] must not allow manual posting.",
            );
        }

        if ($account->status !== 'active') {
            throw new LogicException(
                "Existing system account [{$account->code}] must remain active.",
            );
        }
    }

    private function ensureEveryRequiredSystemAccountExists(): void
    {
        foreach ($this->registry->systemAccountKeys() as $systemKey) {
            $count = Account::query()
                ->where('system_key', $systemKey)
                ->count();

            if ($count !== 1) {
                throw new LogicException(
                    "System account [{$systemKey}] must be assigned exactly once for the active tenant.",
                );
            }
        }
    }

    private function resolveActorId(Tenant $tenant): ?int
    {
        $activeUserId = User::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->orderBy('id')
            ->value('id');

        if ($activeUserId !== null) {
            return (int) $activeUserId;
        }

        $anyUserId = User::withTrashed()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('id')
            ->value('id');

        return $anyUserId === null
            ? null
            : (int) $anyUserId;
    }
}