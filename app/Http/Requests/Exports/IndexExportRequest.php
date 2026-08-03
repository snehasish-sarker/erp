<?php

declare(strict_types=1);

namespace App\Http\Requests\Exports;

use App\Support\Exports\ExportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexExportRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'queued',
        'processing',
        'completed',
        'failed',
        'cancelled',
        'expired',
    ];

    /**
     * @var list<string>
     */
    private const SORTS = [
        'name',
        'export_type',
        'status',
        'progress_percent',
        'rows_exported',
        'created_at',
        'completed_at',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(
            'exports.view',
        ) === true;
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
            'export_type' => [
                'nullable',
                'string',
                Rule::in(
                    app(ExportRegistry::class)->keys(),
                ),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(self::STATUSES),
            ],
            'sort' => [
                'required',
                'string',
                Rule::in(self::SORTS),
            ],
            'direction' => [
                'required',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],
            'per_page' => [
                'required',
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
            'search' => $this->nullableTrimmedString(
                'search',
            ),
            'export_type' => $this->nullableLowercaseString(
                'export_type',
            ),
            'status' => $this->nullableLowercaseString(
                'status',
            ),
            'sort' => $this->filled('sort')
                ? mb_strtolower(
                    trim(
                        (string) $this->input('sort'),
                    ),
                )
                : 'created_at',
            'direction' => $this->filled('direction')
                ? mb_strtolower(
                    trim(
                        (string) $this->input('direction'),
                    ),
                )
                : 'desc',
            'per_page' => $this->filled('per_page')
                ? $this->input('per_page')
                : 25,
        ]);
    }

    private function nullableTrimmedString(
        string $key,
    ): ?string {
        if (!$this->filled($key)) {
            return null;
        }

        $value = trim(
            (string) $this->input($key),
        );

        return $value === ''
            ? null
            : $value;
    }

    private function nullableLowercaseString(
        string $key,
    ): ?string {
        $value = $this->nullableTrimmedString(
            $key,
        );

        return $value === null
            ? null
            : mb_strtolower($value);
    }
}