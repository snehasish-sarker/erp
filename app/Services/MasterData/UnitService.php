<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Product;
use App\Models\PurchaseOrderLine;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\MasterData\UnitCategoryRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class UnitService
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'active',
        'inactive',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly UnitCategoryRegistry $categoryRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): Unit {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $normalized,
            ): Unit {
                /*
                 * Unit codes are tenant-wide. Locking the tenant serializes
                 * concurrent creates that may attempt to reserve one code.
                 */
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                );

                return Unit::query()->create(
                    $normalized,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        Unit $unit,
        array $data,
        User $actor,
    ): Unit {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureUnitBelongsToTenant(
            unit: $unit,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $unit,
                $normalized,
            ): Unit {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedUnit = Unit::query()
                    ->whereKey($unit->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                    exceptUnitId:
                        (int) $lockedUnit->getKey(),
                );

                $this->ensureMeasurementConfigurationCanChange(
                    unit: $lockedUnit,
                    normalized: $normalized,
                );

                $lockedUnit->fill($normalized);
                $lockedUnit->save();

                return $lockedUnit->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(
        Unit $unit,
        User $actor,
    ): void {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureUnitBelongsToTenant(
            unit: $unit,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use ($unit): void {
                $lockedUnit = Unit::query()
                    ->whereKey($unit->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $hasProducts = Product::query()
                    ->withTrashed()
                    ->where(
                        'base_unit_id',
                        $lockedUnit->getKey(),
                    )
                    ->exists();

                if ($hasProducts) {
                    throw ValidationException::withMessages([
                        'unit' => [
                            'The unit cannot be deleted because it is used as the base unit of one or more products.',
                        ],
                    ]);
                }

                $hasPurchaseOrderLines =
                    PurchaseOrderLine::query()
                        ->where(
                            'unit_id',
                            $lockedUnit->getKey(),
                        )
                        ->exists();

                if ($hasPurchaseOrderLines) {
                    throw ValidationException::withMessages([
                        'unit' => [
                            'The unit cannot be deleted because it is referenced by one or more purchase orders.',
                        ],
                    ]);
                }

                /*
                 * Add goods-receipt, inventory-ledger, sales-document, and
                 * accounting usage checks as those modules are introduced.
                 */
                $lockedUnit->delete();
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     symbol: string|null,
     *     category: string,
     *     allow_decimal: bool,
     *     decimal_places: int,
     *     status: string
     * }
     */
    private function normalize(array $data): array
    {
        $name = trim(
            (string) ($data['name'] ?? ''),
        );

        $code = mb_strtoupper(
            trim(
                (string) ($data['code'] ?? ''),
            ),
        );

        $symbol = $this->nullableTrimmedString(
            $data['symbol'] ?? null,
        );

        $category = mb_strtolower(
            trim(
                (string) (
                    $data['category'] ?? 'count'
                ),
            ),
        );

        $status = mb_strtolower(
            trim(
                (string) (
                    $data['status'] ?? 'active'
                ),
            ),
        );

        $allowDecimal = $this->normalizeBoolean(
            $data['allow_decimal'] ?? false,
        );

        $decimalPlaces = (int) (
            $data['decimal_places'] ?? 0
        );

        $errors = [];

        if (
            $name === ''
            || mb_strlen($name) > 100
        ) {
            $errors['name'] = [
                'The unit name is required and may not exceed 100 characters.',
            ];
        }

        if (
            $code === ''
            || mb_strlen($code) > 30
            || preg_match(
                '/^[A-Z0-9_-]+$/',
                $code,
            ) !== 1
        ) {
            $errors['code'] = [
                'The unit code may contain uppercase letters, numbers, underscores, and hyphens only.',
            ];
        }

        if (
            $symbol !== null
            && mb_strlen($symbol) > 20
        ) {
            $errors['symbol'] = [
                'The unit symbol may not exceed 20 characters.',
            ];
        }

        if (
            !$this->categoryRegistry
                ->exists($category)
        ) {
            $errors['category'] = [
                'The selected unit category is invalid.',
            ];
        }

        if (
            !in_array(
                $status,
                self::STATUSES,
                true,
            )
        ) {
            $errors['status'] = [
                'The selected unit status is invalid.',
            ];
        }

        if (
            $decimalPlaces < 0
            || $decimalPlaces > 6
        ) {
            $errors['decimal_places'] = [
                'Decimal places must be between 0 and 6.',
            ];
        }

        if (!$allowDecimal) {
            $decimalPlaces = 0;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(
                $errors,
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'symbol' => $symbol,
            'category' => $category,
            'allow_decimal' => $allowDecimal,
            'decimal_places' => $decimalPlaces,
            'status' => $status,
        ];
    }

    /**
     * Measurement behavior becomes part of Product and Purchase Order
     * semantics. Once referenced, it must remain stable. Display fields
     * such as name, code, symbol, and status may still be updated because
     * transactional documents preserve their own snapshots.
     *
     * @param array{
     *     name: string,
     *     code: string,
     *     symbol: string|null,
     *     category: string,
     *     allow_decimal: bool,
     *     decimal_places: int,
     *     status: string
     * } $normalized
     */
    private function ensureMeasurementConfigurationCanChange(
        Unit $unit,
        array $normalized,
    ): void {
        $configurationChanged =
            $unit->category !== $normalized['category']
            || (bool) $unit->allow_decimal
                !== $normalized['allow_decimal']
            || (int) $unit->decimal_places
                !== $normalized['decimal_places'];

        if (!$configurationChanged) {
            return;
        }

        $hasProducts = Product::query()
            ->withTrashed()
            ->where(
                'base_unit_id',
                $unit->getKey(),
            )
            ->exists();

        if ($hasProducts) {
            throw ValidationException::withMessages([
                'category' => [
                    'The unit category and decimal configuration cannot be changed because the unit is assigned to one or more products.',
                ],
            ]);
        }

        $hasPurchaseOrderLines =
            PurchaseOrderLine::query()
                ->where(
                    'unit_id',
                    $unit->getKey(),
                )
                ->exists();

        if (!$hasPurchaseOrderLines) {
            return;
        }

        throw ValidationException::withMessages([
            'category' => [
                'The unit category and decimal configuration cannot be changed because the unit is referenced by one or more purchase orders.',
            ],
        ]);
    }

    private function ensureCodeIsAvailable(
        string $code,
        ?int $exceptUnitId = null,
    ): void {
        $exists = Unit::query()
            ->withTrashed()
            ->where('code', $code)
            ->when(
                $exceptUnitId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptUnitId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [
                'The unit code has already been used.',
            ],
        ]);
    }

    private function ensureActorBelongsToTenant(
        User $actor,
        int $tenantId,
    ): void {
        if ((int) $actor->tenant_id === $tenantId) {
            return;
        }

        throw new LogicException(
            'The selected user does not belong to the active tenant.',
        );
    }

    private function ensureUnitBelongsToTenant(
        Unit $unit,
        int $tenantId,
    ): void {
        if ((int) $unit->tenant_id === $tenantId) {
            return;
        }

        throw new LogicException(
            'The selected unit belongs to another tenant.',
        );
    }

    private function normalizeBoolean(
        mixed $value,
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return is_string($value)
            && in_array(
                mb_strtolower(trim($value)),
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                ],
                true,
            );
    }

    private function nullableTrimmedString(
        mixed $value,
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}