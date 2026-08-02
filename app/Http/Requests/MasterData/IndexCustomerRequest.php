<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Support\MasterData\CustomerTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'customers.view',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $sortOptions = [
            'name',
            'code',
            'customer_type',
            'payment_terms_days',
            'status',
            'created_at',
        ];

        if (
            $this->user()?->can(
                'customers.override_credit_limit',
            ) === true
        ) {
            $sortOptions[] = 'credit_limit';
        }

        return [
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],

            'customer_type' => [
                'nullable',
                'string',
                Rule::in(
                    app(
                        CustomerTypeRegistry::class,
                    )->keys(),
                ),
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            'sort' => [
                'nullable',
                'string',
                Rule::in($sortOptions),
            ],

            'direction' => [
                'nullable',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    10,
                    25,
                    50,
                    100,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim(
                (string) $this->input('search'),
            ),

            'customer_type' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'customer_type',
                    ),
                ),
            ),

            'status' => mb_strtolower(
                trim(
                    (string) $this->input('status'),
                ),
            ),

            'sort' => trim(
                (string) $this->input('sort'),
            ),

            'direction' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'direction',
                    ),
                ),
            ),

            'per_page' => $this->filled('per_page')
                ? $this->input('per_page')
                : null,
        ]);
    }
}