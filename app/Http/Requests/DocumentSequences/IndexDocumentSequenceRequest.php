<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentSequences;

use App\Support\DocumentNumbers\DocumentTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexDocumentSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'document_numbering.view',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)
            ->tenant()
            ->getKey();

        return [
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'scope' => [
                'nullable',
                'string',
                Rule::in([
                    'company',
                    'branch',
                ]),
            ],
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
            'document_type' => [
                'nullable',
                'string',
                Rule::in(
                    app(DocumentTypeRegistry::class)->keys(),
                ),
            ],
            'reset_policy' => [
                'nullable',
                'string',
                Rule::in([
                    'never',
                    'calendar_year',
                    'fiscal_year',
                ]),
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
                Rule::in([
                    'name',
                    'document_type',
                    'current_number',
                    'status',
                    'updated_at',
                ]),
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
            'scope' => trim(
                (string) $this->input('scope'),
            ),
            'document_type' => trim(
                (string) $this->input('document_type'),
            ),
            'reset_policy' => trim(
                (string) $this->input('reset_policy'),
            ),
            'status' => trim(
                (string) $this->input('status'),
            ),
            'sort' => trim(
                (string) $this->input('sort'),
            ),
            'direction' => trim(
                (string) $this->input('direction'),
            ),
            'branch_id' => $this->input('branch_id') === ''
                ? null
                : $this->input('branch_id'),
        ]);
    }
}