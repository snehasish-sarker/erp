<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\CustomerTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerService
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
        private readonly CustomerTypeRegistry $customerTypeRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): Customer {
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
            ): Customer {
                /*
                 * Customer identifiers are tenant-wide. Locking the tenant
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

                return Customer::query()->create(
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
        Customer $customer,
        array $data,
        User $actor,
    ): Customer {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureCustomerBelongsToTenant(
            customer: $customer,
            tenantId: $tenantId,
        );

        $normalized = $this->normalize($data);

        return DB::transaction(
            function () use (
                $tenant,
                $customer,
                $normalized,
            ): Customer {
                Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedCustomer = Customer::query()
                    ->whereKey($customer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $customerId =
                    (int) $lockedCustomer->getKey();

                $this->ensureCodeIsAvailable(
                    code: $normalized['code'],
                    exceptCustomerId: $customerId,
                );

                $this->ensureTaxNumberIsAvailable(
                    taxNumber:
                        $normalized['tax_number'],
                    exceptCustomerId: $customerId,
                );

                $this->ensureRegistrationNumberIsAvailable(
                    registrationNumber:
                        $normalized['registration_number'],
                    exceptCustomerId: $customerId,
                );

                $lockedCustomer->fill($normalized);
                $lockedCustomer->save();

                return $lockedCustomer->refresh();
            },
            attempts: 5,
        );
    }

    public function delete(
        Customer $customer,
        User $actor,
    ): void {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureActorBelongsToTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureCustomerBelongsToTenant(
            customer: $customer,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use ($customer): void {
                $lockedCustomer = Customer::query()
                    ->whereKey($customer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Add sales-order, dispatch, sales-invoice, sales-return,
                 * receivable-ledger, and customer-payment usage checks as
                 * those modules are introduced.
                 */
                $lockedCustomer->delete();
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
     *     customer_type: string,
     *     contact_person: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     alternate_phone: string|null,
     *     tax_number: string|null,
     *     registration_number: string|null,
     *     billing_address_line_1: string|null,
     *     billing_address_line_2: string|null,
     *     billing_city: string|null,
     *     billing_state: string|null,
     *     billing_postal_code: string|null,
     *     billing_country_code: string|null,
     *     shipping_address_line_1: string|null,
     *     shipping_address_line_2: string|null,
     *     shipping_city: string|null,
     *     shipping_state: string|null,
     *     shipping_postal_code: string|null,
     *     shipping_country_code: string|null,
     *     payment_terms_days: int,
     *     credit_limit: string,
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

        $customerType = mb_strtolower(
            trim(
                (string) (
                    $data['customer_type']
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

        $billingAddressLine1 =
            $this->nullableTrimmedString(
                $data['billing_address_line_1']
                    ?? null,
            );

        $billingAddressLine2 =
            $this->nullableTrimmedString(
                $data['billing_address_line_2']
                    ?? null,
            );

        $billingCity =
            $this->nullableTrimmedString(
                $data['billing_city'] ?? null,
            );

        $billingState =
            $this->nullableTrimmedString(
                $data['billing_state'] ?? null,
            );

        $billingPostalCode =
            $this->nullableTrimmedString(
                $data['billing_postal_code']
                    ?? null,
            );

        $billingCountryCode =
            $this->nullableUppercaseString(
                $data['billing_country_code']
                    ?? null,
            );

        $shippingAddressLine1 =
            $this->nullableTrimmedString(
                $data['shipping_address_line_1']
                    ?? null,
            );

        $shippingAddressLine2 =
            $this->nullableTrimmedString(
                $data['shipping_address_line_2']
                    ?? null,
            );

        $shippingCity =
            $this->nullableTrimmedString(
                $data['shipping_city'] ?? null,
            );

        $shippingState =
            $this->nullableTrimmedString(
                $data['shipping_state'] ?? null,
            );

        $shippingPostalCode =
            $this->nullableTrimmedString(
                $data['shipping_postal_code']
                    ?? null,
            );

        $shippingCountryCode =
            $this->nullableUppercaseString(
                $data['shipping_country_code']
                    ?? null,
            );

        $paymentTermsDays = (int) (
            $data['payment_terms_days'] ?? 0
        );

        $creditLimit = $this->normalizeDecimal(
            value: $data['credit_limit'] ?? 0,
            field: 'credit_limit',
            label: 'credit limit',
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
                'The customer name is required and may not exceed 160 characters.',
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
                'The customer code may contain uppercase letters, numbers, periods, underscores, slashes, and hyphens only.',
            ];
        }

        if (
            !$this->customerTypeRegistry
                ->exists($customerType)
        ) {
            $errors['customer_type'] = [
                'The selected customer type is invalid.',
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
                'The customer email must be a valid email address and may not exceed 255 characters.',
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

        $this->validateAddress(
            errors: $errors,
            prefix: 'billing',
            addressLine1: $billingAddressLine1,
            addressLine2: $billingAddressLine2,
            city: $billingCity,
            state: $billingState,
            postalCode: $billingPostalCode,
            countryCode: $billingCountryCode,
        );

        $this->validateAddress(
            errors: $errors,
            prefix: 'shipping',
            addressLine1: $shippingAddressLine1,
            addressLine2: $shippingAddressLine2,
            city: $shippingCity,
            state: $shippingState,
            postalCode: $shippingPostalCode,
            countryCode: $shippingCountryCode,
        );

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
                'The customer notes may not exceed 4,000 characters.',
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
                'The selected customer status is invalid.',
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
            'customer_type' => $customerType,
            'contact_person' => $contactPerson,
            'email' => $email,
            'phone' => $phone,
            'alternate_phone' => $alternatePhone,
            'tax_number' => $taxNumber,

            'registration_number' =>
                $registrationNumber,

            'billing_address_line_1' =>
                $billingAddressLine1,

            'billing_address_line_2' =>
                $billingAddressLine2,

            'billing_city' => $billingCity,
            'billing_state' => $billingState,

            'billing_postal_code' =>
                $billingPostalCode,

            'billing_country_code' =>
                $billingCountryCode,

            'shipping_address_line_1' =>
                $shippingAddressLine1,

            'shipping_address_line_2' =>
                $shippingAddressLine2,

            'shipping_city' => $shippingCity,
            'shipping_state' => $shippingState,

            'shipping_postal_code' =>
                $shippingPostalCode,

            'shipping_country_code' =>
                $shippingCountryCode,

            'payment_terms_days' =>
                $paymentTermsDays,

            'credit_limit' => $creditLimit,
            'notes' => $notes,
            'status' => $status,
        ];
    }

    private function ensureCodeIsAvailable(
        string $code,
        ?int $exceptCustomerId = null,
    ): void {
        $exists = Customer::query()
            ->withTrashed()
            ->where('code', $code)
            ->when(
                $exceptCustomerId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptCustomerId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [
                'The customer code has already been used.',
            ],
        ]);
    }

    private function ensureTaxNumberIsAvailable(
        ?string $taxNumber,
        ?int $exceptCustomerId = null,
    ): void {
        if ($taxNumber === null) {
            return;
        }

        $exists = Customer::query()
            ->withTrashed()
            ->where('tax_number', $taxNumber)
            ->when(
                $exceptCustomerId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptCustomerId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'tax_number' => [
                'The customer tax number has already been used.',
            ],
        ]);
    }

    private function ensureRegistrationNumberIsAvailable(
        ?string $registrationNumber,
        ?int $exceptCustomerId = null,
    ): void {
        if ($registrationNumber === null) {
            return;
        }

        $exists = Customer::query()
            ->withTrashed()
            ->where(
                'registration_number',
                $registrationNumber,
            )
            ->when(
                $exceptCustomerId !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptCustomerId,
                ),
            )
            ->exists();

        if (!$exists) {
            return;
        }

        throw ValidationException::withMessages([
            'registration_number' => [
                'The customer registration number has already been used.',
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

    private function ensureCustomerBelongsToTenant(
        Customer $customer,
        int $tenantId,
    ): void {
        if (
            (int) $customer->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw new LogicException(
            'The selected customer belongs to another tenant.',
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
                "The customer {$label} contains invalid characters or exceeds 40 characters.",
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
                "The customer {$label} may not exceed 100 characters or contain control characters.",
            ];
        }
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function validateAddress(
        array &$errors,
        string $prefix,
        ?string $addressLine1,
        ?string $addressLine2,
        ?string $city,
        ?string $state,
        ?string $postalCode,
        ?string $countryCode,
    ): void {
        $label = ucfirst($prefix);

        $this->validateNullableLength(
            errors: $errors,
            field: "{$prefix}_address_line_1",
            value: $addressLine1,
            maximum: 255,
            message:
                "{$label} address line 1 may not exceed 255 characters.",
        );

        $this->validateNullableLength(
            errors: $errors,
            field: "{$prefix}_address_line_2",
            value: $addressLine2,
            maximum: 255,
            message:
                "{$label} address line 2 may not exceed 255 characters.",
        );

        $this->validateNullableLength(
            errors: $errors,
            field: "{$prefix}_city",
            value: $city,
            maximum: 100,
            message:
                "The {$prefix} city may not exceed 100 characters.",
        );

        $this->validateNullableLength(
            errors: $errors,
            field: "{$prefix}_state",
            value: $state,
            maximum: 100,
            message:
                "The {$prefix} state or region may not exceed 100 characters.",
        );

        $this->validateNullableLength(
            errors: $errors,
            field: "{$prefix}_postal_code",
            value: $postalCode,
            maximum: 30,
            message:
                "The {$prefix} postal code may not exceed 30 characters.",
        );

        if (
            $countryCode !== null
            && preg_match(
                '/^[A-Z]{2}$/',
                $countryCode,
            ) !== 1
        ) {
            $errors["{$prefix}_country_code"] = [
                "The {$prefix} country code must contain exactly two letters.",
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

    private function normalizeDecimal(
        mixed $value,
        string $field,
        string $label,
    ): string {
        if (is_int($value)) {
            $value = (string) $value;
        } elseif (
            is_float($value)
            && is_finite($value)
        ) {
            $value = number_format(
                $value,
                6,
                '.',
                '',
            );
        } elseif (is_string($value)) {
            $value = trim($value);
        } else {
            $value = '';
        }

        if (
            preg_match(
                '/^\d+(?:\.\d{1,6})?$/',
                $value,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} must be a non-negative amount with no more than 6 decimal places.",
                ],
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '',
        );

        $whole = ltrim($whole, '0');

        $whole = $whole === ''
            ? '0'
            : $whole;

        if (mb_strlen($whole) > 14) {
            throw ValidationException::withMessages([
                $field => [
                    "The {$label} may not exceed 99,999,999,999,999.999999.",
                ],
            ]);
        }

        return $whole . '.' . str_pad(
            $fraction,
            6,
            '0',
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