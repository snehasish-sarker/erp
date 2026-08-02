<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Models\Customer;
use App\Support\MasterData\CustomerTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && $this->user()?->can(
                'update',
                $customer,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $canManageCreditLimit = $this->user()?->can(
            'customers.override_credit_limit',
        ) === true;

        return [
            'name' => [
                'required',
                'string',
                'max:160',
            ],

            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
            ],

            'customer_type' => [
                'required',
                'string',
                Rule::in(
                    app(
                        CustomerTypeRegistry::class,
                    )->keys(),
                ),
            ],

            'contact_person' => [
                'nullable',
                'string',
                'max:120',
            ],

            'email' => [
                'nullable',
                'email:rfc',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[0-9+()\-\.\s]+$/',
            ],

            'alternate_phone' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[0-9+()\-\.\s]+$/',
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[^\x00-\x1F\x7F]+$/u',
            ],

            'registration_number' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[^\x00-\x1F\x7F]+$/u',
            ],

            'billing_address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],

            'billing_address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'billing_city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'billing_state' => [
                'nullable',
                'string',
                'max:100',
            ],

            'billing_postal_code' => [
                'nullable',
                'string',
                'max:30',
            ],

            'billing_country_code' => [
                'nullable',
                'string',
                'size:2',
                'regex:/^[A-Za-z]{2}$/',
            ],

            'shipping_address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],

            'shipping_address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'shipping_city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'shipping_state' => [
                'nullable',
                'string',
                'max:100',
            ],

            'shipping_postal_code' => [
                'nullable',
                'string',
                'max:30',
            ],

            'shipping_country_code' => [
                'nullable',
                'string',
                'size:2',
                'regex:/^[A-Za-z]{2}$/',
            ],

            'payment_terms_days' => [
                'required',
                'integer',
                'between:0,3650',
            ],

            'credit_limit' => [
                Rule::excludeIf(
                    !$canManageCreditLimit,
                ),
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input('name'),
            ),

            'code' => mb_strtoupper(
                trim(
                    (string) $this->input('code'),
                ),
            ),

            'customer_type' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'customer_type',
                        'company',
                    ),
                ),
            ),

            'contact_person' =>
                $this->nullableInput(
                    'contact_person',
                ),

            'email' => $this->filled('email')
                ? mb_strtolower(
                    trim(
                        (string) $this->input('email'),
                    ),
                )
                : null,

            'phone' =>
                $this->nullableInput('phone'),

            'alternate_phone' =>
                $this->nullableInput(
                    'alternate_phone',
                ),

            'tax_number' =>
                $this->nullableUppercaseInput(
                    'tax_number',
                ),

            'registration_number' =>
                $this->nullableUppercaseInput(
                    'registration_number',
                ),

            'billing_address_line_1' =>
                $this->nullableInput(
                    'billing_address_line_1',
                ),

            'billing_address_line_2' =>
                $this->nullableInput(
                    'billing_address_line_2',
                ),

            'billing_city' =>
                $this->nullableInput(
                    'billing_city',
                ),

            'billing_state' =>
                $this->nullableInput(
                    'billing_state',
                ),

            'billing_postal_code' =>
                $this->nullableInput(
                    'billing_postal_code',
                ),

            'billing_country_code' =>
                $this->nullableUppercaseInput(
                    'billing_country_code',
                ),

            'shipping_address_line_1' =>
                $this->nullableInput(
                    'shipping_address_line_1',
                ),

            'shipping_address_line_2' =>
                $this->nullableInput(
                    'shipping_address_line_2',
                ),

            'shipping_city' =>
                $this->nullableInput(
                    'shipping_city',
                ),

            'shipping_state' =>
                $this->nullableInput(
                    'shipping_state',
                ),

            'shipping_postal_code' =>
                $this->nullableInput(
                    'shipping_postal_code',
                ),

            'shipping_country_code' =>
                $this->nullableUppercaseInput(
                    'shipping_country_code',
                ),

            'payment_terms_days' =>
                $this->input(
                    'payment_terms_days',
                    0,
                ),

            'credit_limit' =>
                $this->input(
                    'credit_limit',
                    '0',
                ),

            'notes' =>
                $this->nullableInput('notes'),

            'status' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'status',
                        'active',
                    ),
                ),
            ),
        ]);
    }

    private function nullableInput(
        string $field,
    ): ?string {
        return $this->filled($field)
            ? trim(
                (string) $this->input($field),
            )
            : null;
    }

    private function nullableUppercaseInput(
        string $field,
    ): ?string {
        $value = $this->nullableInput($field);

        return $value !== null
            ? mb_strtoupper($value)
            : null;
    }
}