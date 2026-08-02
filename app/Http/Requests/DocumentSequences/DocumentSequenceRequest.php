<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentSequences;

use App\Support\DocumentNumbers\DocumentTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

abstract class DocumentSequenceRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function sequenceRules(?int $ignoreId = null): array
    {
        $tenantId = app(TenantContext::class)
            ->tenant()
            ->getKey();

        $branchId = $this->integer('branch_id');

        $scopeKey = $branchId > 0
            ? "branch:{$branchId}"
            : 'company';

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query
                                ->where('tenant_id', $tenantId)
                                ->whereNull('deleted_at'),
                    ),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'document_type' => [
                'required',
                'string',
                Rule::in(
                    app(DocumentTypeRegistry::class)->keys(),
                ),
                $this->uniqueDocumentTypeRule(
                    tenantId: (int) $tenantId,
                    scopeKey: $scopeKey,
                    ignoreId: $ignoreId,
                ),
            ],
            'prefix' => [
                'nullable',
                'string',
                'max:60',
            ],
            'suffix' => [
                'nullable',
                'string',
                'max:60',
            ],
            'current_number' => [
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'number_padding' => [
                'required',
                'integer',
                'min:3',
                'max:12',
            ],
            'reset_policy' => [
                'required',
                'string',
                Rule::in([
                    'never',
                    'calendar_year',
                    'fiscal_year',
                ]),
            ],
            'fiscal_year_start_month' => [
                'nullable',
                'integer',
                'between:1,12',
                'required_if:reset_policy,fiscal_year',
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

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateTemplate(
                    validator: $validator,
                    field: 'prefix',
                );

                $this->validateTemplate(
                    validator: $validator,
                    field: 'suffix',
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $resetPolicy = trim(
            (string) $this->input('reset_policy'),
        );

        $branchId = $this->input('branch_id');

        $this->merge([
            'branch_id' => $branchId === ''
                ? null
                : $branchId,

            'name' => trim(
                (string) $this->input('name'),
            ),

            'document_type' => trim(
                (string) $this->input('document_type'),
            ),

            'prefix' => $this->nullableTrimmedString(
                'prefix',
            ),

            'suffix' => $this->nullableTrimmedString(
                'suffix',
            ),

            'reset_policy' => $resetPolicy,

            'fiscal_year_start_month' =>
                $resetPolicy === 'fiscal_year'
                    ? $this->input(
                        'fiscal_year_start_month',
                    )
                    : null,

            'status' => trim(
                (string) $this->input('status'),
            ),
        ]);
    }

    private function uniqueDocumentTypeRule(
        int $tenantId,
        string $scopeKey,
        ?int $ignoreId,
    ): Unique {
        $rule = Rule::unique(
            'document_sequences',
            'document_type',
        )->where(
            static fn (Builder $query): Builder =>
                $query
                    ->where('tenant_id', $tenantId)
                    ->where('scope_key', $scopeKey),
        );

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }

    private function nullableTrimmedString(
        string $field,
    ): ?string {
        $value = trim(
            (string) $this->input($field),
        );

        return $value === '' ? null : $value;
    }

    private function validateTemplate(
        Validator $validator,
        string $field,
    ): void {
        $value = $this->input($field);

        if (!is_string($value) || $value === '') {
            return;
        }

        $allowedTokens = [
            '{YYYY}',
            '{YY}',
            '{FY}',
            '{FY_SHORT}',
            '{BRANCH}',
            '{TYPE}',
        ];

        preg_match_all(
            '/\{[^{}]+\}/',
            $value,
            $matches,
        );

        foreach (array_unique($matches[0]) as $token) {
            if (!in_array($token, $allowedTokens, true)) {
                $validator->errors()->add(
                    $field,
                    "The {$field} contains the unsupported token {$token}.",
                );
            }
        }

        $withoutKnownTokens = str_replace(
            $allowedTokens,
            '',
            $value,
        );

        if (
            str_contains($withoutKnownTokens, '{')
            || str_contains($withoutKnownTokens, '}')
        ) {
            $validator->errors()->add(
                $field,
                "The {$field} contains an incomplete template token.",
            );
        }
    }
}