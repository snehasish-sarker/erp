<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Models\Supplier;
use App\Support\MasterData\SupplierTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            && $this->user()?->can(
                'update',
                $supplier,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
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

            'supplier_type' => [
                'required',
                'string',
                Rule::in(
                    app(
                        SupplierTypeRegistry::class,
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

            'address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],

            'address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'state' => [
                'nullable',
                'string',
                'max:100',
            ],

            'postal_code' => [
                'nullable',
                'string',
                'max:30',
            ],

            'country_code' => [
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
        $this->merge(
            $this->normalizedInput(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedInput(): array
    {
        return [
            'name' => trim(
                (string) $this->input('name'),
            ),

            'code' => mb_strtoupper(
                trim(
                    (string) $this->input('code'),
                ),
            ),

            'supplier_type' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'supplier_type',
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

            'address_line_1' =>
                $this->nullableInput(
                    'address_line_1',
                ),

            'address_line_2' =>
                $this->nullableInput(
                    'address_line_2',
                ),

            'city' =>
                $this->nullableInput('city'),

            'state' =>
                $this->nullableInput('state'),

            'postal_code' =>
                $this->nullableInput(
                    'postal_code',
                ),

            'country_code' =>
                $this->nullableUppercaseInput(
                    'country_code',
                ),

            'payment_terms_days' =>
                $this->input(
                    'payment_terms_days',
                    0,
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
        ];
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