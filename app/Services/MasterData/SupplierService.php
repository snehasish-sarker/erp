<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\SupplierTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierService
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
        private readonly SupplierTypeRegistry $supplierTypeRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): Supplier {
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
            ): Supplier {
                /*
                 * Supplier identifiers are tenant-wide. Locking the tenant
                 * serializes concurrent code and statutory-ID writes.
                 */
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                );

                $this->ensureTaxNumberIsAvailable(
                    taxNumber:
                        $normalized['tax_number'],
                );

                $this->ensureRegistrationNumberIsAvailable(
                    registrationNumber:
                        $normalized['registration_number'],
                );

                return Supplier::query()->create(
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
        Supplier $supplier,
        array $data,
        User $actor,
    ): Supplier {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureSupplierBelongsToTenant(
            supplier: $supplier,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $supplier,
                $normalized,
            ): Supplier {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedSupplier = Supplier::query()
                    ->whereKey($supplier->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $supplierId =
                    (int) $lockedSupplier->getKey();

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                    exceptSupplierId: $supplierId,
                );

                $this->ensureTaxNumberIsAvailable(
                    taxNumber:
                        $normalized['tax_number'],
                    exceptSupplierId: $supplierId,
                );

                $this->ensureRegistrationNumberIsAvailable(
                    registrationNumber:
                        $normalized['registration_number'],
                    exceptSupplierId: $supplierId,
                );

                $lockedSupplier->fill($normalized);
                $lockedSupplier->save();

                return $lockedSupplier->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(
        Supplier $supplier,
        User $actor,
    ): void {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureSupplierBelongsToTenant(
            supplier: $supplier,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use ($supplier): void {
                $lockedSupplier = Supplier::query()
                    ->whereKey($supplier->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $hasPurchaseOrders = PurchaseOrder::query()
                    ->withTrashed()
                    ->where(
                        'supplier_id',
                        $lockedSupplier->getKey(),
                    )
                    ->exists();

                if ($hasPurchaseOrders) {
                    throw ValidationException::withMessages([
                        'supplier' => [
                            'The supplier cannot be deleted because it is referenced by one or more purchase orders.',
                        ],
                    ]);
                }

                /*
                 * Add goods-receipt, supplier-invoice, purchase-return,
                 * payable-ledger, and supplier-payment usage checks as
                 * those modules are introduced.
                 */
                $lockedSupplier->delete();
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
     *     supplier_type: string,
     *     contact_person: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     alternate_phone: string|null,
     *     tax_number: string|null,
     *     registration_number: string|null,
     *     address_line_1: string|null,
     *     address_line_2: string|null,
     *     city: string|null,
     *     state: string|null,
     *     postal_code: string|null,
     *     country_code: string|null,
     *     payment_terms_days: int,
     *     notes: string|null,
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

        $supplierType = mb_strtolower(
            trim(
                (string) (
                    $data['supplier_type']
                        ?? 'company'
                ),
            ),
        );

        $contactPerson =
            $this->nullableTrimmedString(
                $data['contact_person'] ?? null,
            );

        $email = $this->nullableTrimmedString(
            $data['email'] ?? null,
        );

        if ($email !== null) {
            $email = mb_strtolower($email);
        }

        $phone = $this->nullableTrimmedString(
            $data['phone'] ?? null,
        );

        $alternatePhone =
            $this->nullableTrimmedString(
                $data['alternate_phone'] ?? null,
            );

        $taxNumber =
            $this->nullableUppercaseString(
                $data['tax_number'] ?? null,
            );

        $registrationNumber =
            $this->nullableUppercaseString(
                $data['registration_number'] ?? null,
            );

        $addressLine1 =
            $this->nullableTrimmedString(
                $data['address_line_1'] ?? null,
            );

        $addressLine2 =
            $this->nullableTrimmedString(
                $data['address_line_2'] ?? null,
            );

        $city = $this->nullableTrimmedString(
            $data['city'] ?? null,
        );

        $state = $this->nullableTrimmedString(
            $data['state'] ?? null,
        );

        $postalCode =
            $this->nullableTrimmedString(
                $data['postal_code'] ?? null,
            );

        $countryCode =
            $this->nullableUppercaseString(
                $data['country_code'] ?? null,
            );

        $paymentTermsDays = (int) (
            $data['payment_terms_days'] ?? 0
        );

        $notes = $this->nullableTrimmedString(
            $data['notes'] ?? null,
        );

        $status = mb_strtolower(
            trim(
                (string) (
                    $data['status'] ?? 'active'
                ),
            ),
        );

        $errors = [];

        if (
            $name === ''
            || mb_strlen($name) > 160
        ) {
            $errors['name'] = [
                'The supplier name is required and may not exceed 160 characters.',
            ];
        }

        if (
            $code === ''
            || mb_strlen($code) > 60
            || preg_match(
                '/^[A-Z0-9][A-Z0-9._\/-]*$/',
                $code,
            ) !== 1
        ) {
            $errors['code'] = [
                'The supplier code may contain uppercase letters, numbers, periods, underscores, slashes, and hyphens only.',
            ];
        }

        if (
            !$this->supplierTypeRegistry
                ->exists($supplierType)
        ) {
            $errors['supplier_type'] = [
                'The selected supplier type is invalid.',
            ];
        }

        $this->validateNullableLength(
            errors: $errors,
            field: 'contact_person',
            value: $contactPerson,
            maximum: 120,
            message:
                'The contact person may not exceed 120 characters.',
        );

        if (
            $email !== null
            && (
                mb_strlen($email) > 255
                || filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL,
                ) === false
            )
        ) {
            $errors['email'] = [
                'The supplier email must be a valid email address and may not exceed 255 characters.',
            ];
        }

        $this->validatePhone(
            errors: $errors,
            field: 'phone',
            value: $phone,
            label: 'phone number',
        );

        $this->validatePhone(
            errors: $errors,
            field: 'alternate_phone',
            value: $alternatePhone,
            label: 'alternate phone number',
        );

        $this->validateIdentifier(
            errors: $errors,
            field: 'tax_number',
            value: $taxNumber,
            label: 'tax number',
        );

        $this->validateIdentifier(
            errors: $errors,
            field: 'registration_number',
            value: $registrationNumber,
            label: 'registration number',
        );

        $this->validateNullableLength(
            errors: $errors,
            field: 'address_line_1',
            value: $addressLine1,
            maximum: 255,
            message:
                'Address line 1 may not exceed 255 characters.',
        );

        $this->validateNullableLength(
            errors: $errors,
            field: 'address_line_2',
            value: $addressLine2,
            maximum: 255,
            message:
                'Address line 2 may not exceed 255 characters.',
        );

        $this->validateNullableLength(
            errors: $errors,
            field: 'city',
            value: $city,
            maximum: 100,
            message:
                'The city may not exceed 100 characters.',
        );

        $this->validateNullableLength(
            errors: $errors,
            field: 'state',
            value: $state,
            maximum: 100,
            message:
                'The state or region may not exceed 100 characters.',
        );

        $this->validateNullableLength(
            errors: $errors,
            field: 'postal_code',
            value: $postalCode,
            maximum: 30,
            message:
                'The postal code may not exceed 30 characters.',
        );

        if (
            $countryCode !== null
            && preg_match(
                '/^[A-Z]{2}$/',
                $countryCode,
            ) !== 1
        ) {
            $errors['country_code'] = [
                'The country code must contain exactly two letters.',
            ];
        }

        if (
            $paymentTermsDays < 0
            || $paymentTermsDays > 3650
        ) {
            $errors['payment_terms_days'] = [
                'Payment terms must be between 0 and 3,650 days.',
            ];
        }

        if (
            $notes !== null
            && mb_strlen($notes) > 4000
        ) {
            $errors['notes'] = [
                'The supplier notes may not exceed 4,000 characters.',
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
                'The selected supplier status is invalid.',
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(
                $errors,
            );
        }

        return [
            'name' => $name,
            'code' => $code,
            'supplier_type' => $supplierType,
            'contact_person' => $contactPerson,
            'email' => $email,
            'phone' => $phone,
            'alternate_phone' => $alternatePhone,
            'tax_number' => $taxNumber,

            'registration_number' =>
                $registrationNumber,

            'address_line_1' => $addressLine1,
            'address_line_2' => $addressLine2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'country_code' => $countryCode,

            'payment_terms_days' =>
                $paymentTermsDays,

            'notes' => $notes,
            'status' => $status,
        ];
    }

    private function ensureCodeIsAvailable(
        string $code,
        ?int $exceptSupplierId = null,
    ): void {
        $exists = Supplier::query()
            ->withTrashed()
            ->where('code', $code)
            ->when(
                $exceptSupplierId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptSupplierId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [
                'The supplier code has already been used.',
            ],
        ]);
    }

    private function ensureTaxNumberIsAvailable(
        ?string $taxNumber,
        ?int $exceptSupplierId = null,
    ): void {
        if ($taxNumber === null) {
            return;
        }

        $exists = Supplier::query()
            ->withTrashed()
            ->where('tax_number', $taxNumber)
            ->when(
                $exceptSupplierId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptSupplierId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'tax_number' => [
                'The supplier tax number has already been used.',
            ],
        ]);
    }

    private function ensureRegistrationNumberIsAvailable(
        ?string $registrationNumber,
        ?int $exceptSupplierId = null,
    ): void {
        if ($registrationNumber === null) {
            return;
        }

        $exists = Supplier::query()
            ->withTrashed()
            ->where(
                'registration_number',
                $registrationNumber,
            )
            ->when(
                $exceptSupplierId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptSupplierId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'registration_number' => [
                'The supplier registration number has already been used.',
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

    private function ensureSupplierBelongsToTenant(
        Supplier $supplier,
        int $tenantId,
    ): void {
        if (
            (int) $supplier->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The selected supplier belongs to another tenant.',
        );
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function validatePhone(
        array &$errors,
        string $field,
        ?string $value,
        string $label,
    ): void {
        if ($value === null) {
            return;
        }

        if (
            mb_strlen($value) > 40
            || preg_match(
                '/^[0-9+()\-\.\s]+$/',
                $value,
            ) !== 1
        ) {
            $errors[$field] = [
                "The supplier {$label} contains invalid characters or exceeds 40 characters.",
            ];
        }
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function validateIdentifier(
        array &$errors,
        string $field,
        ?string $value,
        string $label,
    ): void {
        if ($value === null) {
            return;
        }

        if (
            mb_strlen($value) > 100
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $value,
            ) === 1
        ) {
            $errors[$field] = [
                "The supplier {$label} may not exceed 100 characters or contain control characters.",
            ];
        }
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function validateNullableLength(
        array &$errors,
        string $field,
        ?string $value,
        int $maximum,
        string $message,
    ): void {
        if (
            $value !== null
            && mb_strlen($value) > $maximum
        ) {
            $errors[$field] = [$message];
        }
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

    private function nullableUppercaseString(
        mixed $value,
    ): ?string {
        $value = $this->nullableTrimmedString(
            $value,
        );

        return $value !== null
            ? mb_strtoupper($value)
            : null;
    }
}