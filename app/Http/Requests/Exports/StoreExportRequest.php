<?php

declare(strict_types=1);

namespace App\Http\Requests\Exports;

use App\Support\Exports\ExportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'exports.create',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'export_type' => [
                'required',
                'string',
                Rule::in(
                    app(ExportRegistry::class)->keys(),
                ),
            ],
            'format' => [
                'required',
                'string',
                Rule::in([
                    'csv',
                ]),
            ],
            'filters' => [
                'nullable',
                'array',
                'max:20',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'export_type' => trim(
                (string) $this->input('export_type'),
            ),
            'format' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'format',
                        'csv',
                    ),
                ),
            ),
        ]);
    }
}