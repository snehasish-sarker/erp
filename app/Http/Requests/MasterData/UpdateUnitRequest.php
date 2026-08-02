<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Models\Unit;
use App\Support\MasterData\UnitCategoryRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit
            && $this->user()?->can(
                'update',
                $unit,
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
                'max:100',
            ],

            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],

            'symbol' => [
                'nullable',
                'string',
                'max:20',
            ],

            'category' => [
                'required',
                'string',
                Rule::in(
                    app(
                        UnitCategoryRegistry::class,
                    )->keys(),
                ),
            ],

            'allow_decimal' => [
                'required',
                'boolean',
            ],

            'decimal_places' => [
                'required',
                'integer',
                'between:0,6',
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

            'symbol' => $this->filled('symbol')
                ? trim(
                    (string) $this->input('symbol'),
                )
                : null,

            'category' => mb_strtolower(
                trim(
                    (string) $this->input('category'),
                ),
            ),

            'status' => mb_strtolower(
                trim(
                    (string) $this->input('status'),
                ),
            ),

            'decimal_places' => $this->boolean(
                'allow_decimal',
            )
                ? $this->input(
                    'decimal_places',
                    0,
                )
                : 0,
        ]);
    }
}