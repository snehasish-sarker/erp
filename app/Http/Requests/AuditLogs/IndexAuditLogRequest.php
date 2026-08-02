<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditLogs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('audit_logs.view') === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],
            'event' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-z0-9_.-]+$/',
            ],
            'subject_type' => [
                'nullable',
                'string',
                'max:255',
            ],
            'actor' => [
                'nullable',
                'string',
                'regex:/^(system|[1-9][0-9]*)$/',
            ],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'created_at',
                    'event',
                    'subject_label',
                    'actor_name',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                $dateFrom = $this->string(
                    'date_from',
                )->toString();

                $dateTo = $this->string(
                    'date_to',
                )->toString();

                if (
                    $dateFrom !== ''
                    && $dateTo !== ''
                    && $dateTo < $dateFrom
                ) {
                    $validator->errors()->add(
                        'date_to',
                        'The end date must be on or after the start date.',
                    );
                }
            },
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableTrimmedString(
                'search',
            ),
            'event' => $this->nullableTrimmedString(
                'event',
            ),
            'subject_type' => $this->nullableTrimmedString(
                'subject_type',
            ),
            'actor' => $this->nullableTrimmedString(
                'actor',
            ),
            'date_from' => $this->nullableTrimmedString(
                'date_from',
            ),
            'date_to' => $this->nullableTrimmedString(
                'date_to',
            ),
            'sort' => $this->nullableTrimmedString(
                'sort',
            ),
            'direction' => $this->nullableTrimmedString(
                'direction',
            ),
        ]);
    }

    private function nullableTrimmedString(
        string $key,
    ): ?string {
        $value = trim(
            $this->string($key)->toString(),
        );

        return $value === '' ? null : $value;
    }
}